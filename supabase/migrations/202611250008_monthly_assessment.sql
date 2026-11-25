begin;
create or replace function public.assessment_status() returns jsonb
language sql stable security definer set search_path = '' as $$
  select jsonb_build_object('enabled', public.portal_upload_enabled() and coalesce((select content->>'assessment_month' from public.site_content where section='settings' and content ? 'assessment_month' order by id desc limit 1), '') ~ '^[0-9]{4}-(0[1-9]|1[0-2])$',
    'month', (select content->>'assessment_month' from public.site_content where section='settings' and content ? 'assessment_month' order by id desc limit 1));
$$;
create or replace function public.set_assessment_window(month_value text, is_open boolean) returns void
language plpgsql security definer set search_path = '' as $$
begin
  if public.portal_role() is distinct from 'admin' then raise exception 'Administrator access is required.'; end if;
  if is_open and (month_value is null or month_value !~ '^[0-9]{4}-(0[1-9]|1[0-2])$') then raise exception 'Choose a valid month.'; end if;
  perform pg_advisory_xact_lock(hashtext('nusha:assessment_window')::bigint);
  update public.site_content set content = content || jsonb_build_object('assessment_month',coalesce(month_value,''),'upload_status',case when is_open then 'open' else 'closed' end) where section='settings' and content ? 'assessment_month';
  if not found then insert into public.site_content(section,content) values ('settings',jsonb_build_object('assessment_month',coalesce(month_value,''),'upload_status',case when is_open then 'open' else 'closed' end)); end if;
  update public.site_content set content = content || jsonb_build_object('upload_status',case when is_open then 'open' else 'closed' end) where section='settings' and content ? 'upload_status';
end; $$;
create or replace function public.guard_programme_submission() returns trigger
language plpgsql security definer set search_path = '' as $$
declare active_month text;
begin
  if new.kind = 'activity' and public.portal_role() in ('class','wing') then
    active_month := (public.assessment_status()->>'month');
    if (public.assessment_status()->>'enabled')::boolean is not true then raise exception 'Monthly assessments are closed.'; end if;
    if new.admin_details->>'month' is distinct from active_month then raise exception 'Only the open assessment month can be submitted.'; end if;
  end if;
  return new;
end; $$;
create trigger guard_programme_assessment_update before update on public.work_items
for each row execute function public.guard_programme_submission();
create function public.admin_assessment_owners() returns table(id uuid, display_name text, role text)
language sql stable security definer set search_path = '' as $$
  select p.id,p.display_name,p.role from public.profiles p where public.portal_role() = 'admin' and p.role in ('class','wing') order by p.display_name;
$$;
revoke all on function public.assessment_status(), public.set_assessment_window(text,boolean) from public, anon;
grant execute on function public.assessment_status(), public.set_assessment_window(text,boolean) to authenticated;
revoke all on function public.admin_assessment_owners() from public, anon;
grant execute on function public.admin_assessment_owners() to authenticated;
notify pgrst, 'reload schema';
commit;
