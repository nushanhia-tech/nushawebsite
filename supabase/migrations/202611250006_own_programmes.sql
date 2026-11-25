begin;
create function public.manage_own_programme(item_id uuid, operation text, fields jsonb default '{}'::jsonb)
returns void language plpgsql security definer set search_path = '' as $$
declare item public.work_items%rowtype; actor text := public.portal_role();
begin
  if actor not in ('class','wing') or actor is null then raise exception 'Action not permitted.'; end if;
  select * into item from public.work_items where id = item_id and owner_id = auth.uid() for update;
  if not found or item.kind not in ('activity','booking') or (item.kind = 'booking' and actor <> 'wing') then raise exception 'Item unavailable.'; end if;
  if item.kind = 'activity' and (item.status <> 'Pending' or not public.portal_upload_enabled()) then raise exception 'Only pending reports with upload access enabled can be changed.'; end if;
  if operation = 'delete' then
    if item.kind = 'booking' and item.status not in ('Pending','Rejected') then raise exception 'Only pending or rejected bookings can be deleted.'; end if;
    delete from public.work_items where id = item_id;
  elsif operation = 'edit' and item.kind = 'activity' then
    update public.work_items set title = trim(fields->>'title'), detail = trim(fields->>'detail'),
      scheduled_on = nullif(fields->>'scheduled_on','')::date,
      admin_details = item.admin_details || jsonb_build_object('time',fields->>'time','category',fields->>'category','stage',fields->>'stage','month',fields->>'month')
    where id = item_id;
  elsif operation = 'conducted' and item.kind = 'booking' and item.status = 'Approved' then
    update public.work_items set status = 'Conducted' where id = item_id;
  else raise exception 'Action is unavailable for this status.'; end if;
end;
$$;
revoke all on function public.manage_own_programme(uuid,text,jsonb) from public, anon;
grant execute on function public.manage_own_programme(uuid,text,jsonb) to authenticated;
commit;
