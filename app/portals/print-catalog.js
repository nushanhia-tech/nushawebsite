import { useEffect, useState } from "react";

export default function PrintCatalog({ db }) {
  const [items, setItems] = useState([]);
  const [editing, setEditing] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  async function refresh() {
    const { data, error } = await db.from("print_catalog").select("*").order("name");
    if (error) throw error;
    setItems(data);
  }
  useEffect(() => { if (db) refresh().catch((error) => setMessage(error.message)); }, [db]);
  async function save(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    setBusy(true); setMessage("");
    try {
      const item = { name: values.name.trim(), category: values.category.trim(), rate: Number(values.rate) };
      const { error } = editing ? await db.from("print_catalog").update(item).eq("id", editing.id) : await db.from("print_catalog").insert(item);
      if (error) throw error;
      form.reset(); setEditing(null); await refresh(); setMessage("Print item saved.");
    } catch (error) { setMessage(error.message); } finally { setBusy(false); }
  }
  async function setActive(item) {
    setBusy(true); setMessage("");
    try { const { error } = await db.from("print_catalog").update({ active: !item.active }).eq("id", item.id); if (error) throw error; await refresh(); } catch(error) { setMessage(error.message); } finally { setBusy(false); }
  }
  return <section className="panel site-content"><h2>Print items and rates</h2><p>Manage the items available for printer billing.</p>
    {!db ? <p>Connect Supabase to manage print items.</p> : <><form key={editing?.id || "new"} className="account-form" onSubmit={save}>
      <label>Item name<input name="name" defaultValue={editing?.name || ""} maxLength={180} required /></label>
      <label>Category<input name="category" defaultValue={editing?.category || "Printing"} maxLength={100} required /></label>
      <label>Rate (₹)<input name="rate" type="number" min="0" step="0.01" defaultValue={editing?.rate ?? ""} required /></label>
      <button className="primary" disabled={busy}>{editing ? "Save item" : "Add item"}</button>{editing && <button type="button" className="secondary" onClick={() => setEditing(null)}>Cancel</button>}
    </form>{items.map((item) => <div className="content-row" key={item.id}><div><strong>{item.name}</strong><p>{item.category} · ₹{Number(item.rate).toFixed(2)} · {item.active ? "Active" : "Inactive"}</p></div><div className="dialog-actions"><button className="secondary" disabled={busy} onClick={() => setEditing(item)}>Edit</button><button className="secondary" disabled={busy} onClick={() => setActive(item)}>{item.active ? "Deactivate" : "Restore"}</button></div></div>)}</>}
    {message && <p role="status">{message}</p>}
  </section>;
}
