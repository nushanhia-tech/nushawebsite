begin;
create policy portal_photos_programme_insert on storage.objects for insert to authenticated
with check (bucket_id = 'portal-photos' and public.portal_role() in ('class','wing')
  and public.portal_upload_enabled() and (storage.foldername(name))[1] = (select auth.uid())::text);
commit;
