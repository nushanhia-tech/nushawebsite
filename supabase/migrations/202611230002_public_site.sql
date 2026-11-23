begin;

create table public.site_content (
  id bigint generated always as identity primary key,
  section text not null check (section in ('events', 'highlights', 'updates', 'leadership', 'settings')),
  content jsonb not null check (jsonb_typeof(content) = 'object')
);
alter table public.site_content enable row level security;
revoke all on public.site_content from anon, authenticated;
grant select, insert, update, delete on public.site_content to authenticated;
create policy manage_site_content on public.site_content for all to authenticated
  using (public.portal_role() = 'admin') with check (public.portal_role() = 'admin');

alter table public.work_items add column public_details jsonb;
alter table public.work_items alter column owner_id drop not null;
alter table public.work_items add check (owner_id is not null or (kind = 'outreach' and public_details is not null and recipient_id is null));
create unique index outreach_public_id on public.work_items ((public_details->>'id')) where kind = 'outreach' and public_details is not null;
create sequence public.outreach_public_ids;

create function public.prepare_outreach_details() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if new.kind = 'outreach' and new.public_details is null then
    new.public_details := jsonb_build_object('id', nextval('public.outreach_public_ids'),
      'category', 'Article', 'author_name', 'NUS’HA', 'author_role', 'Student Union');
  end if;
  return new;
end;
$$;
revoke execute on function public.prepare_outreach_details() from public;
create trigger prepare_outreach_details before insert on public.work_items
for each row execute function public.prepare_outreach_details();

create function public.submit_public_outreach(title text, category text, content text, thumbnail text, author_name text, author_role text, author_photo text)
returns void language plpgsql security definer set search_path = '' as $$
begin
  if exists (select 1 from public.site_content settings where settings.section = 'settings' and settings.content->>'outreach_status' = 'closed') then
    raise exception 'Outreach is closed.';
  end if;
  if category is null or category not in ('Article','Story','Poem') or length(trim(coalesce(author_name,''))) not between 1 and 160
    or length(coalesce(author_role,'')) > 160 or length(coalesce(thumbnail,'')) > 1000
    or length(coalesce(author_photo,'')) > 1000 then
    raise exception 'Check the outreach form fields.';
  end if;
  insert into public.work_items (kind, owner_id, title, detail, public_details)
    values ('outreach', null, trim(title), trim(content),
      jsonb_build_object('id', nextval('public.outreach_public_ids'), 'category', category, 'thumbnail', thumbnail,
        'author_name', trim(author_name), 'author_role', trim(author_role), 'author_photo', author_photo));
end;
$$;

create function public.public_site_data(page_name text) returns jsonb
language sql stable security definer set search_path = '' as $$
  select jsonb_build_object(
    'outreach_status', case when exists (
      select 1 from public.site_content settings where settings.section = 'settings' and settings.content->>'outreach_status' = 'closed'
    ) then 'closed' else 'open' end,
    'content', coalesce((select jsonb_object_agg(section, rows) from (
      select section, jsonb_agg(content order by id desc) as rows
      from public.site_content where
        (page_name = 'index' and section in ('highlights','updates','leadership'))
        or (page_name = 'events' and section = 'events')
        or (page_name = 'leadership' and section = 'leadership')
      group by section
    ) sections), '{}'::jsonb),
    'bookings', case when page_name = 'calendar' then coalesce((
      select jsonb_agg(jsonb_build_object('title', w.title, 'prog_desc', w.detail, 'category', 'Programme', 'stage_type', '', 'session_time', w.detail, 'prog_date', w.scheduled_on,
        'prog_time', '', 'venue', '', 'status', w.status, 'wing_name', p.display_name) order by w.scheduled_on)
      from public.work_items w join public.profiles p on p.id = w.owner_id
      where w.kind = 'booking' and w.status = 'Approved' and w.scheduled_on is not null
    ), '[]'::jsonb) end,
    'ranked', case when page_name = 'ranking' then coalesce((
      select jsonb_agg(jsonb_build_object('name', p.display_name, 'type', initcap(p.role),
        'group_id', md5(p.id::text), 'month', totals.month, 'points', totals.points, 'entries', totals.entries) order by p.id)
      from public.profiles p left join lateral (
        select to_char(w.created_at at time zone 'Asia/Kolkata','YYYY-MM') as month, sum(w.score) as points, count(*) as entries
        from public.work_items w where w.owner_id = p.id and w.kind = 'activity' and w.status = 'Evaluated'
        group by 1
      ) totals on true where p.role in ('wing','class')
    ), '[]'::jsonb) end,
    'outreach', case when page_name = 'outreach' then coalesce((
      select jsonb_agg(w.public_details || jsonb_build_object('title', w.title, 'content', w.detail) order by w.created_at desc)
      from public.work_items w where w.kind = 'outreach' and w.status = 'Published' and w.public_details is not null
    ), '[]'::jsonb) end
  );
$$;

revoke execute on function public.public_site_data(text), public.submit_public_outreach(text,text,text,text,text,text,text) from public;
grant execute on function public.public_site_data(text), public.submit_public_outreach(text,text,text,text,text,text,text) to anon, authenticated;
commit;
