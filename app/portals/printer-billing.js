import { useEffect, useState } from "react";

export default function PrinterBilling({ db }) {
  const [items, setItems] = useState([]);
  const [bills, setBills] = useState([]);
  const [query, setQuery] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  async function refresh() {
    const [catalog, list] = await Promise.all([db.from("print_catalog").select("id,name,rate").eq("active",true).order("name"), db.rpc("printer_list_bills")]);
    if (catalog.error || list.error) throw catalog.error || list.error;
    setItems(catalog.data); setBills(list.data);
  }
  useEffect(() => { if (db) refresh().catch((error) => setMessage(error.message)); }, [db]);
  async function create(event) {
    event.preventDefault(); const form = event.currentTarget; const values = new FormData(form);
    setBusy(true); setMessage("");
    try { const { error } = await db.rpc("printer_create_bill", { admission: values.get("admission"), catalog_id: values.get("item"), units: Number(values.get("quantity")) }); if (error) throw error; form.reset(); await refresh(); setMessage("Bill created and assigned to the student."); } catch(error) { setMessage(error.message); } finally { setBusy(false); }
  }
  async function paid(id) {
    if (!window.confirm("Mark this bill paid?")) return;
    setBusy(true); setMessage("");
    try { const { error } = await db.rpc("printer_mark_bill_paid", { bill_id: id }); if (error) throw error; await refresh(); setMessage("Bill marked paid."); } catch(error) { setMessage(error.message); } finally { setBusy(false); }
  }
  const visible = bills.filter((bill) => `${bill.student_name} ${bill.admission} ${bill.item_name}`.toLowerCase().includes(query.toLowerCase()));
  return <section className="panel site-content"><h2>Student printing bills</h2><p>Create a charge for a student using their admission number.</p>
    {!db ? <p>Connect Supabase to manage bills.</p> : <><form className="account-form" onSubmit={create}>
      <label>Admission number<input name="admission" maxLength={80} required /></label>
      <label>Print item<select name="item" required defaultValue=""><option value="">Choose an item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.name} · ₹{Number(item.rate).toFixed(2)}</option>)}</select></label>
      <label>Quantity<input name="quantity" type="number" min="1" max="1000" defaultValue="1" required /></label>
      <button className="primary" disabled={busy || !items.length}>Create bill</button>
    </form><label>Search bills<input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Student, admission number or item" /></label>
    {visible.map((bill) => <div className="content-row" key={bill.id}><div><strong>{bill.student_name} · {bill.admission}</strong><p>{bill.item_name} × {bill.quantity} · ₹{Number(bill.total).toFixed(2)} · {bill.status}</p></div>{bill.status === "Pending" && <button className="secondary" disabled={busy} onClick={() => paid(bill.id)}>Mark paid</button>}</div>)}
    {!visible.length && <p>No bills found.</p>}</>}{message && <p role="status">{message}</p>}
  </section>;
}
