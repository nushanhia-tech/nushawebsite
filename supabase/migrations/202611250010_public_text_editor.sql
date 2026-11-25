begin;
create table public.public_text_overrides (
  id bigint generated always as identity primary key,
  page text not null check (page in ('index','about','events','calendar','leadership','ranking','outreach','contact')),
  original text not null check (length(trim(original)) between 1 and 1000),
  replacement text not null check (length(replacement) <= 2000),
  unique(page,original)
);
alter table public.public_text_overrides enable row level security;
revoke all on public.public_text_overrides from anon, authenticated;
grant select,insert,update,delete on public.public_text_overrides to authenticated;
grant usage,select on sequence public.public_text_overrides_id_seq to authenticated;
create policy public_text_admin on public.public_text_overrides for all to authenticated using (public.portal_role() = 'admin') with check (public.portal_role() = 'admin');
create function public.public_text_for_page(page_name text) returns jsonb language sql stable security definer set search_path = '' as $$
  select coalesce(jsonb_agg(jsonb_build_object('original',original,'replacement',replacement) order by id),'[]'::jsonb) from public.public_text_overrides where page = page_name;
$$;
revoke all on function public.public_text_for_page(text) from public;
grant execute on function public.public_text_for_page(text) to anon, authenticated;
notify pgrst, 'reload schema';
commit;
