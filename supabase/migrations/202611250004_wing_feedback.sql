begin;
drop policy if exists create_work on public.work_items;
create policy create_work on public.work_items for insert to authenticated with check (
  owner_id = (select auth.uid()) and case public.portal_role()
    when 'admin' then kind in ('notice','due','outreach')
    when 'wing' then kind in ('booking','activity','result','print','support')
    when 'class' then kind in ('activity','print','support')
    when 'student' then kind in ('payment','support')
    else false end
);
commit;
