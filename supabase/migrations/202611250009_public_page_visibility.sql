begin;
create table if not exists public.public_page_visibility (
  page text primary key check (page in ('index','about','events','calendar','leadership','ranking','outreach','contact')),
  visible boolean not null default true
);
alter table public.public_page_visibility enable row level security;
revoke all on public.public_page_visibility from anon, authenticated;
grant select, insert, update on public.public_page_visibility to authenticated;
create policy page_visibility_admin_select on public.public_page_visibility for select to authenticated using (public.portal_role() = 'admin');
create policy page_visibility_admin_insert on public.public_page_visibility for insert to authenticated with check (public.portal_role() = 'admin');
create policy page_visibility_admin_update on public.public_page_visibility for update to authenticated using (public.portal_role() = 'admin') with check (public.portal_role() = 'admin');
create function public.visible_public_pages() returns jsonb language sql stable security definer set search_path = '' as $$
  select coalesce(jsonb_object_agg(page,visible),'{}'::jsonb) from public.public_page_visibility;
$$;
revoke all on function public.visible_public_pages() from public;
grant execute on function public.visible_public_pages() to anon, authenticated;
notify pgrst, 'reload schema';
commit;
