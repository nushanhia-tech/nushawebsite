begin;

alter table public.profiles
  add column username text unique,
  add column photo_url text,
  add column upload_access boolean not null default true,
  add column active boolean not null default true;

update public.profiles p set username = split_part(u.email, '@', 1)
from auth.users u where u.id = p.id and u.email like '%@accounts.nusha.invalid';

alter table public.work_items
  add column target_role text check (target_role is null or (target_role in ('student','class','wing','printer','admin') and kind = 'notice')),
  add column admin_details jsonb not null default '{}' check (jsonb_typeof(admin_details) = 'object');

grant insert (target_role, admin_details) on public.work_items to authenticated;

create or replace function public.portal_role() returns text
language sql stable security definer set search_path = ''
as $$ select role from public.profiles where id = auth.uid() and active $$;

drop policy own_profile on public.profiles;
create policy own_profile on public.profiles for select to authenticated
using (id = (select auth.uid()) and active);

drop policy read_work on public.work_items;
create policy read_work on public.work_items for select to authenticated using (
  public.portal_role() is not null and (
    owner_id = (select auth.uid()) or recipient_id = (select auth.uid()) or
    public.portal_role() = 'admin' or
    (public.portal_role() = 'printer' and kind = 'print') or
    (kind = 'notice' and status = 'Published' and (target_role is null or target_role = public.portal_role()))
  )
);

create function public.portal_upload_enabled() returns boolean
language sql stable security definer set search_path = ''
as $$
  select coalesce((select upload_access and active from public.profiles where id = auth.uid()), false)
    and not exists (select 1 from public.site_content where section = 'settings' and content->>'upload_status' = 'closed');
$$;

create function public.guard_programme_submission() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if new.kind = 'activity' and public.portal_role() in ('class','wing') and not public.portal_upload_enabled() then
    raise exception 'Programme submissions are closed for this account.';
  end if;
  return new;
end;
$$;

create trigger guard_programme_submission before insert on public.work_items
for each row execute function public.guard_programme_submission();

create function public.admin_manage_item(item_id uuid, operation text, fields jsonb default '{}'::jsonb)
returns void language plpgsql security definer set search_path = '' as $$
declare
  item public.work_items%rowtype;
  points integer;
begin
  if public.portal_role() is distinct from 'admin' then raise exception 'Administrator access is required.'; end if;
  select * into item from public.work_items where id = item_id for update;
  if not found then raise exception 'Item unavailable.'; end if;

  if operation = 'delete' and item.kind in ('activity','booking','due','notice','result','support','outreach') then
    if item.kind = 'due' then
      delete from public.work_items where related_id = item_id and kind = 'payment';
    end if;
    delete from public.work_items where id = item_id;
  elsif operation = 'mark_paid' and item.kind = 'due' and item.status = 'Pending' then
    update public.work_items set status = 'Paid' where id = item_id;
  elsif operation = 'edit' and item.kind = 'activity' then
    points := nullif(fields->>'score','')::integer;
    update public.work_items set title = trim(fields->>'title'), detail = trim(fields->>'detail'),
      scheduled_on = nullif(fields->>'scheduled_on','')::date,
      score = points, status = case when points is null then 'Pending' else 'Evaluated' end,
      admin_details = jsonb_build_object('time', fields->>'time', 'category', fields->>'category',
        'stage', fields->>'stage', 'month', fields->>'month', 'file', fields->>'file')
    where id = item_id;
  elsif operation = 'edit' and item.kind = 'outreach' and fields->>'category' in ('Article','Poem','Story') then
    update public.work_items set title = trim(fields->>'title'), detail = trim(fields->>'detail'),
      public_details = public_details || jsonb_build_object('category', fields->>'category',
        'author_name', fields->>'author_name', 'author_role', fields->>'author_role',
        'thumbnail', fields->>'thumbnail', 'author_photo', fields->>'author_photo')
    where id = item_id;
  else
    raise exception 'That action cannot be applied to this item.';
  end if;
end;
$$;

create function public.admin_assign_dues(entries jsonb)
returns integer language plpgsql security definer set search_path = '' as $$
declare
  entry jsonb;
  student_id uuid;
begin
  if public.portal_role() is distinct from 'admin' then raise exception 'Administrator access is required.'; end if;
  if jsonb_typeof(entries) <> 'array' or jsonb_array_length(entries) not between 1 and 250 then
    raise exception 'Provide between 1 and 250 valid dues rows.';
  end if;
  for entry in select value from jsonb_array_elements(entries) loop
    select id into student_id from public.profiles
      where role = 'student' and active and member_number = trim(entry->>'admission');
    if not found then raise exception 'Admission number % did not match an active student.', entry->>'admission'; end if;
    insert into public.work_items (kind, title, recipient_id, amount, admin_details)
      values ('due', trim(entry->>'title'), student_id, (entry->>'amount')::numeric,
        jsonb_build_object('category', trim(entry->>'category'), 'admission', trim(entry->>'admission')));
  end loop;
  return jsonb_array_length(entries);
end;
$$;

create or replace function public.prepare_outreach_details() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if new.kind = 'outreach' and new.public_details is null then
    if coalesce(new.admin_details->>'category','Article') not in ('Article','Story','Poem') then
      raise exception 'Choose an outreach category.';
    end if;
    new.public_details := jsonb_build_object('id', nextval('public.outreach_public_ids'),
      'category', coalesce(new.admin_details->>'category','Article'),
      'author_name', coalesce(nullif(new.admin_details->>'author_name',''),'NUS’HA'),
      'author_role', coalesce(nullif(new.admin_details->>'author_role',''),'Student Union'),
      'thumbnail', new.admin_details->>'thumbnail', 'author_photo', new.admin_details->>'author_photo');
    if public.portal_role() = 'admin' and new.admin_details->>'publish' = 'true' then
      new.status := 'Published';
    end if;
  end if;
  return new;
end;
$$;

create function public.admin_set_option(option_name text, value jsonb)
returns void language plpgsql security definer set search_path = '' as $$
begin
  if public.portal_role() is distinct from 'admin' then raise exception 'Administrator access is required.'; end if;
  if option_name = 'upload_status' then
    if value is null or value not in ('"open"'::jsonb,'"closed"'::jsonb) then raise exception 'Choose open or closed.'; end if;
  elsif option_name = 'card_design' then
    if value is null or jsonb_typeof(value) <> 'object' or octet_length(value::text) > 525000 then
      raise exception 'The card design exceeds the allowed size.';
    end if;
  else
    raise exception 'Unknown setting.';
  end if;
  perform pg_advisory_xact_lock(hashtext('nusha:' || option_name)::bigint);
  update public.site_content set content = content || jsonb_build_object(option_name, value)
    where section = 'settings' and content ? option_name;
  if not found then
    insert into public.site_content(section,content) values ('settings',jsonb_build_object(option_name,value));
  end if;
end;
$$;

create function public.portal_card_design() returns jsonb
language sql stable security definer set search_path = ''
as $$
  select case when public.portal_role() is not null then (
    select content->'card_design' from public.site_content
    where section = 'settings' and content ? 'card_design' order by id desc limit 1
  ) end;
$$;

revoke execute on function public.portal_upload_enabled(), public.guard_programme_submission(),
  public.admin_manage_item(uuid,text,jsonb), public.admin_assign_dues(jsonb), public.admin_set_option(text,jsonb), public.portal_card_design() from public, anon;
grant execute on function public.portal_upload_enabled(), public.admin_manage_item(uuid,text,jsonb),
  public.admin_assign_dues(jsonb), public.admin_set_option(text,jsonb), public.portal_card_design() to authenticated;

create table public.programme_standings (
  id uuid primary key default gen_random_uuid(),
  wing_id uuid not null default auth.uid() references public.profiles,
  programme_name text not null check (length(trim(programme_name)) between 1 and 160),
  category text check (length(category) <= 120),
  first_place text check (length(first_place) <= 300),
  second_place text check (length(second_place) <= 300),
  third_place text check (length(third_place) <= 300),
  grade_a text check (length(grade_a) <= 300),
  grade_b text check (length(grade_b) <= 300),
  grade_c text check (length(grade_c) <= 300),
  created_at timestamptz not null default now()
);
create index programme_standings_wing on public.programme_standings(wing_id);
alter table public.programme_standings enable row level security;
revoke all on public.programme_standings from anon, authenticated;
grant select, delete on public.programme_standings to authenticated;
grant insert (programme_name,category,first_place,second_place,third_place,grade_a,grade_b,grade_c) on public.programme_standings to authenticated;
create policy read_programme_standings on public.programme_standings for select to authenticated
  using (public.portal_role() = 'admin' or (public.portal_role() = 'wing' and wing_id = (select auth.uid())));
create policy publish_programme_standings on public.programme_standings for insert to authenticated
  with check (public.portal_role() = 'wing' and wing_id = (select auth.uid()));
create policy delete_programme_standings on public.programme_standings for delete to authenticated
  using (public.portal_role() = 'admin' or (public.portal_role() = 'wing' and wing_id = (select auth.uid())));

create function public.admin_programme_standings()
returns table (
  id uuid, programme_name text, category text, first_place text, second_place text, third_place text,
  grade_a text, grade_b text, grade_c text, created_at timestamptz, wing_name text
) language sql stable security definer set search_path = ''
as $$
  select s.id,s.programme_name,s.category,s.first_place,s.second_place,s.third_place,
    s.grade_a,s.grade_b,s.grade_c,s.created_at,p.display_name
  from public.programme_standings s join public.profiles p on p.id = s.wing_id
  where public.portal_role() = 'admin'
  order by s.created_at desc, s.id desc
  limit 1000;
$$;

revoke execute on function public.admin_programme_standings() from public, anon;
grant execute on function public.admin_programme_standings() to authenticated;

create function public.admin_record_people(ids uuid[])
returns table (id uuid, display_name text, member_number text)
language sql stable security definer set search_path = ''
as $$
  select p.id, p.display_name, p.member_number
  from public.profiles p
  where public.portal_role() = 'admin' and p.id = any(ids);
$$;

revoke execute on function public.admin_record_people(uuid[]) from public, anon;
grant execute on function public.admin_record_people(uuid[]) to authenticated;

commit;
