begin;
create table public.printer_bills (
 id uuid primary key default gen_random_uuid(), printer_id uuid not null references public.profiles(id),
 student_id uuid not null references public.profiles(id), due_id uuid not null unique references public.work_items(id),
 item_id uuid not null references public.print_catalog(id), item_name text not null, rate numeric(10,2) not null,
 quantity integer not null check(quantity between 1 and 1000), total numeric(10,2) not null check(total > 0),
 created_at timestamptz not null default now()
);
alter table public.printer_bills enable row level security;
revoke all on public.printer_bills from anon, authenticated;
grant select on public.printer_bills to authenticated;
create policy printer_bills_access on public.printer_bills for select to authenticated using (public.portal_role() in ('printer','admin') or (public.portal_role()='student' and student_id=auth.uid()));
create function public.printer_create_bill(admission text, catalog_id uuid, units integer) returns uuid
language plpgsql security definer set search_path = '' as $$
declare student public.profiles%rowtype; item public.print_catalog%rowtype; due uuid; bill uuid; cost numeric(10,2);
begin
 if public.portal_role() is distinct from 'printer' then raise exception 'Printer access is required.'; end if;
 if units is null or units not between 1 and 1000 then raise exception 'Quantity must be between 1 and 1000.'; end if;
 select * into student from public.profiles where role='student' and active and member_number=trim(admission);
 if not found then raise exception 'No active student matches that admission number.'; end if;
 select * into item from public.print_catalog where id=catalog_id and active;
 if not found then raise exception 'Choose an active print item.'; end if;
 cost := item.rate * units;
 if cost <= 0 then raise exception 'Bill amount must be greater than zero.'; end if;
 insert into public.work_items(owner_id,recipient_id,kind,title,detail,amount)
 values(auth.uid(),student.id,'due','Printing: ' || left(item.name,140),units || ' × ' || item.name || ' at ₹' || item.rate,cost) returning id into due;
 insert into public.printer_bills(printer_id,student_id,due_id,item_id,item_name,rate,quantity,total)
 values(auth.uid(),student.id,due,item.id,item.name,item.rate,units,cost) returning id into bill;
 return bill;
end; $$;
create function public.printer_mark_bill_paid(bill_id uuid) returns void language plpgsql security definer set search_path = '' as $$
begin
 if public.portal_role() is distinct from 'printer' then raise exception 'Printer access is required.'; end if;
 update public.work_items set status='Paid' where id = (select due_id from public.printer_bills where id=bill_id) and kind='due' and status='Pending';
 if not found then raise exception 'The bill is already paid or unavailable.'; end if;
end; $$;
create function public.printer_list_bills() returns table(id uuid, student_name text, admission text, item_name text, quantity integer, total numeric, status text, created_at timestamptz)
language sql stable security definer set search_path = '' as $$
 select b.id,p.display_name,p.member_number,b.item_name,b.quantity,b.total,w.status,b.created_at
 from public.printer_bills b join public.profiles p on p.id=b.student_id join public.work_items w on w.id=b.due_id
 where public.portal_role() in ('printer','admin') order by b.created_at desc limit 1000;
$$;
revoke all on function public.printer_create_bill(text,uuid,integer),public.printer_mark_bill_paid(uuid),public.printer_list_bills() from public,anon;
grant execute on function public.printer_create_bill(text,uuid,integer),public.printer_mark_bill_paid(uuid),public.printer_list_bills() to authenticated;
notify pgrst, 'reload schema';
commit;
