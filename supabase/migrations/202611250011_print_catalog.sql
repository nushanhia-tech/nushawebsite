begin;
create table public.print_catalog (
 id uuid primary key default gen_random_uuid(),
 name text not null check (length(trim(name)) between 1 and 180),
 category text not null default 'Printing' check (length(category) <= 100),
 rate numeric(10,2) not null check (rate >= 0),
 active boolean not null default true,
 created_at timestamptz not null default now()
);
alter table public.print_catalog enable row level security;
revoke all on public.print_catalog from anon, authenticated;
grant select,insert,update on public.print_catalog to authenticated;
create policy print_catalog_read on public.print_catalog for select to authenticated using (public.portal_role() in ('printer','admin'));
create policy print_catalog_add on public.print_catalog for insert to authenticated with check (public.portal_role() = 'printer');
create policy print_catalog_edit on public.print_catalog for update to authenticated using (public.portal_role() = 'printer') with check (public.portal_role() = 'printer');
notify pgrst, 'reload schema';
commit;
