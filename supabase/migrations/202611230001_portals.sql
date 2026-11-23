begin;

create table public.profiles (
  id uuid primary key references auth.users on delete cascade,
  role text not null check (role in ('student', 'wing', 'class', 'printer', 'admin')),
  display_name text not null,
  member_number text unique
);

create table public.work_items (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references public.profiles,
  recipient_id uuid references public.profiles,
  related_id uuid references public.work_items,
  kind text not null check (kind in ('booking','activity','result','due','payment','print','notice','outreach','support')),
  title text not null check (length(trim(title)) between 1 and 160),
  detail text check (length(detail) <= 2000),
  status text not null default 'Pending',
  amount numeric(10,2),
  score integer check (score between 0 and 100),
  scheduled_on date,
  created_at timestamptz not null default now(),
  check ((kind = 'due' and amount is not null and amount > 0) or (kind <> 'due' and amount is null)),
  check ((kind in ('due','result')) = (recipient_id is not null)),
  check ((kind = 'payment') = (related_id is not null)),
  check (kind not in ('payment','result','notice','support','print','booking','activity','outreach') or length(trim(coalesce(detail,''))) > 0)
);

create index work_owner on public.work_items(owner_id);
create index work_recipient on public.work_items(recipient_id);
create unique index pending_reference_per_due on public.work_items(related_id)
  where kind = 'payment' and status in ('Pending','Verified');
create unique index unique_upi_reference on public.work_items(lower(trim(detail)))
  where kind = 'payment' and status in ('Pending','Verified');

alter table public.profiles enable row level security;
alter table public.work_items enable row level security;
revoke all on public.profiles, public.work_items from anon, authenticated;
grant select on public.profiles, public.work_items to authenticated;
grant insert (recipient_id, related_id, kind, title, detail, amount, scheduled_on) on public.work_items to authenticated;

create function public.portal_role() returns text
language sql stable security definer set search_path = ''
as $$ select role from public.profiles where id = auth.uid() $$;

create policy own_profile on public.profiles for select to authenticated using (id = (select auth.uid()));

create policy read_work on public.work_items for select to authenticated using (
  public.portal_role() is not null and (
    owner_id = (select auth.uid()) or recipient_id = (select auth.uid()) or
    public.portal_role() = 'admin' or
    (public.portal_role() = 'printer' and kind = 'print') or
    (kind = 'notice' and status = 'Published')
  )
);

create policy create_work on public.work_items for insert to authenticated with check (
  owner_id = (select auth.uid()) and case public.portal_role()
    when 'admin' then kind in ('notice','due','outreach')
    when 'wing' then kind in ('booking','activity','result','print')
    when 'class' then kind in ('activity','print','support')
    when 'student' then kind in ('payment','support')
    else false end
);

create function public.prepare_work() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if new.kind = 'due' and not exists (
    select 1 from public.profiles where id = new.recipient_id and role = 'student'
  ) then raise exception 'Choose a student account.'; end if;
  if new.kind = 'result' and not exists (
    select 1 from public.profiles where id = new.recipient_id and role in ('student','class')
  ) then raise exception 'Choose a student or class account.'; end if;
  if new.kind = 'payment' and not exists (
    select 1 from public.work_items where id = new.related_id and kind = 'due'
      and recipient_id = auth.uid() and status = 'Pending'
  ) then raise exception 'Choose one of your outstanding dues.'; end if;
  if new.kind in ('notice','result') then new.status := 'Published'; end if;
  return new;
end;
$$;

create trigger prepare_work before insert on public.work_items
for each row execute function public.prepare_work();

create function public.advance_item(item_id uuid, next_status text, points integer default null) returns void
language plpgsql security definer set search_path = '' as $$
declare
  item public.work_items%rowtype;
  actor text := public.portal_role();
  allowed boolean := false;
begin
  if actor is null or actor not in ('admin','printer') then raise exception 'Action not permitted.'; end if;
  select * into item from public.work_items where id = item_id for update;
  if not found then raise exception 'Item unavailable.'; end if;
  if actor = 'printer' and item.kind = 'print' then
    allowed := (item.status = 'Pending' and next_status = 'In progress') or
      (item.status = 'In progress' and next_status = 'Ready') or
      (item.status = 'Ready' and next_status = 'Collected');
  elsif actor = 'admin' and item.status = 'Pending' then
    allowed := case item.kind
      when 'booking' then next_status in ('Approved','Rejected')
      when 'activity' then next_status = 'Evaluated' and points is not null and points between 0 and 100
      when 'payment' then next_status in ('Verified','Rejected')
      when 'outreach' then next_status in ('Published','Rejected')
      when 'support' then next_status = 'Resolved'
      else false end;
  end if;
  if allowed is distinct from true then raise exception 'Action not permitted for the current status.'; end if;
  if item.kind = 'payment' and next_status = 'Verified' then
    update public.work_items set status = 'Paid'
    where id = item.related_id and kind = 'due' and status = 'Pending' and recipient_id = item.owner_id;
    if not found then raise exception 'The related dues record is no longer outstanding.'; end if;
  end if;
  update public.work_items set status = next_status, score = case when next_status = 'Evaluated' then points end
  where id = item_id;
end;
$$;

revoke execute on function public.portal_role(), public.prepare_work(), public.advance_item(uuid,text,integer) from public, anon;
grant execute on function public.portal_role(), public.advance_item(uuid,text,integer) to authenticated;

commit;
