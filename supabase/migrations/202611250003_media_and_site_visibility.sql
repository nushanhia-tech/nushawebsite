begin;

-- Public read access keeps uploaded website images available to visitors;
-- only active administrators can add, replace, or remove objects.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('portal-photos', 'portal-photos', true, 8388608,
  array['image/webp','image/jpeg','image/png','image/avif'])
on conflict (id) do update set
  public = excluded.public,
  file_size_limit = excluded.file_size_limit,
  allowed_mime_types = excluded.allowed_mime_types;

create policy portal_photos_admin_insert on storage.objects
  for insert to authenticated
  with check (bucket_id = 'portal-photos' and public.portal_role() = 'admin');
create policy portal_photos_admin_update on storage.objects
  for update to authenticated
  using (bucket_id = 'portal-photos' and public.portal_role() = 'admin')
  with check (bucket_id = 'portal-photos' and public.portal_role() = 'admin');
create policy portal_photos_admin_delete on storage.objects
  for delete to authenticated
  using (bucket_id = 'portal-photos' and public.portal_role() = 'admin');

create or replace function public.public_site_data(page_name text) returns jsonb
language sql stable security definer set search_path = '' as $$
  select jsonb_build_object(
    'website_visible', not exists (
      select 1 from public.site_content settings
      where settings.section = 'settings' and settings.content->>'website_status' = 'off'
    ),
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

commit;
