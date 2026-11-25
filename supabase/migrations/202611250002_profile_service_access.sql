begin;

grant select, insert, update on table public.profiles to service_role;

commit;

notify pgrst, 'reload schema';
