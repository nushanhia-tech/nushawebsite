begin;
alter table public.profiles add column if not exists class_id uuid references public.profiles(id) on delete set null;
alter table public.work_items add column if not exists standings_id uuid references public.programme_standings(id) on delete cascade;

create function public.admin_set_student_class(student_id uuid, selected_class uuid) returns void
language plpgsql security definer set search_path = '' as $$
begin
  if public.portal_role() is distinct from 'admin' then raise exception 'Administrator access is required.'; end if;
  if selected_class is not null and not exists (select 1 from public.profiles where id = selected_class and role = 'class' and active) then raise exception 'Choose an active class.'; end if;
  update public.profiles set class_id = selected_class where id = student_id and role = 'student';
  if not found then raise exception 'Student not found.'; end if;
end; $$;

create function public.publish_shared_standings(programme text, programme_category text, placements jsonb) returns uuid
language plpgsql security definer set search_path = '' as $$
declare entry jsonb; student public.profiles%rowtype; standings uuid; label text; admission text; detail_text text; seen text[] := '{}';
begin
  if public.portal_role() is distinct from 'wing' then raise exception 'Wing access is required.'; end if;
  if length(trim(coalesce(programme,''))) not between 1 and 160 or length(coalesce(programme_category,'')) > 120 or jsonb_typeof(placements) is distinct from 'array' or jsonb_array_length(placements) not between 1 and 6 then raise exception 'Check programme and placements.'; end if;
  insert into public.programme_standings(wing_id,programme_name,category) values(auth.uid(),trim(programme),trim(programme_category)) returning id into standings;
  for entry in select * from jsonb_array_elements(placements) loop
    label := entry->>'label'; admission := trim(entry->>'admission');
    if label not in ('First place','Second place','Third place','A grade','B grade','C grade') or label = any(seen) or admission is null or admission = '' then raise exception 'Check placement and admission number.'; end if;
    seen := array_append(seen,label);
    select * into student from public.profiles where role='student' and active and member_number = admission;
    if not found then raise exception 'Admission number % does not match an active student.', admission; end if;
    if student.class_id is null or not exists (select 1 from public.profiles where id=student.class_id and role='class' and active) then raise exception 'Assign an active class to admission number % first.', admission; end if;
    detail_text := label || ' · ' || student.display_name || ' (' || admission || ')' || case when coalesce(trim(programme_category),'') <> '' then ' · ' || trim(programme_category) else '' end;
    insert into public.work_items(owner_id,recipient_id,kind,title,detail,standings_id) values
      (auth.uid(),student.id,'result',trim(programme),detail_text,standings),
      (auth.uid(),student.class_id,'result',trim(programme),detail_text,standings);
    update public.programme_standings set
      first_place = case when label='First place' then student.display_name || ' (' || admission || ')' else first_place end,
      second_place = case when label='Second place' then student.display_name || ' (' || admission || ')' else second_place end,
      third_place = case when label='Third place' then student.display_name || ' (' || admission || ')' else third_place end,
      grade_a = case when label='A grade' then student.display_name || ' (' || admission || ')' else grade_a end,
      grade_b = case when label='B grade' then student.display_name || ' (' || admission || ')' else grade_b end,
      grade_c = case when label='C grade' then student.display_name || ' (' || admission || ')' else grade_c end
      where id=standings;
  end loop;
  return standings;
end; $$;
revoke all on function public.admin_set_student_class(uuid,uuid), public.publish_shared_standings(text,text,jsonb) from public, anon;
grant execute on function public.admin_set_student_class(uuid,uuid), public.publish_shared_standings(text,text,jsonb) to authenticated;
notify pgrst, 'reload schema';
commit;
