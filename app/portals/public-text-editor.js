import { useEffect, useState } from "react";

const pages = { index: "Home", about: "About", events: "Events", calendar: "Event calendar", leadership: "Leadership", ranking: "Ranking", outreach: "Outreach", contact: "Contact" };

export default function PublicTextEditor({ db }) {
  const [page, setPage] = useState("index");
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [edit, setEdit] = useState(null);
  async function refresh(selected) {
    const { data, error } = await db.from("public_text_overrides").select("id,original,replacement").eq("page", selected).order("id", { ascending: false });
    if (error) throw error;
    setRows(data);
  }
  useEffect(() => {
    let live = true;
    setLoading(true); setRows([]); setEdit(null); setMessage("");
    db.from("public_text_overrides").select("id,original,replacement").eq("page", page).order("id", { ascending: false }).then(({data,error}) => {
      if (!live) return;
      if (error) setMessage("Text editor is unavailable. Apply the public text editor migration in Supabase.");
      else setRows(data);
      setLoading(false);
    });
    return () => { live = false; };
  }, [db,page]);
  async function save(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    setBusy(true); setMessage("");
    try {
      const request = edit ? db.from("public_text_overrides").update({ original: values.original.trim(), replacement: values.replacement }).eq("id", edit.id) : db.from("public_text_overrides").upsert({ page, original: values.original.trim(), replacement: values.replacement }, { onConflict: "page,original" });
      const { error } = await request;
      if (error) throw error;
      event.target.reset(); setEdit(null); await refresh(page); setMessage("Saved. The public site may take a few minutes to refresh.");
    } catch(error) { setMessage(error.message); } finally { setBusy(false); }
  }
  async function remove(id) {
    if (!window.confirm("Restore the original text?")) return;
    setBusy(true); setMessage("");
    try { const { error } = await db.from("public_text_overrides").delete().eq("id",id); if (error) throw error; setEdit(null); await refresh(page); setMessage("Original text restored."); } catch(error) { setMessage(error.message); } finally { setBusy(false); }
  }
  return <section className="panel site-content"><h2>Edit website text</h2><p>Choose a page and enter the exact original text shown on the website, then enter the replacement. Text inside separate formatting elements must be edited separately.</p>
    <label>Page<select value={page} onChange={(event) => setPage(event.target.value)}>{Object.entries(pages).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label>
    <form key={edit?.id || page} onSubmit={save}>
      <label>Original text<textarea name="original" required maxLength={1000} defaultValue={edit?.original || ""} rows={3} /></label>
      <label>Replacement text<textarea name="replacement" maxLength={2000} defaultValue={edit?.replacement || ""} rows={3} /></label>
      <div className="dialog-actions"><button className="primary" disabled={busy || loading}>Save text</button>{edit && <button type="button" className="secondary" onClick={() => setEdit(null)}>Cancel</button>}</div>
    </form>
    {loading ? <p>Loading…</p> : rows.map((row) => <div className="content-row" key={row.id}><div><strong>{row.original}</strong><p>{row.replacement || "(empty)"}</p></div><div className="dialog-actions"><button className="secondary" disabled={busy} onClick={() => setEdit(row)}>Edit</button><button className="secondary" disabled={busy} onClick={() => remove(row.id)}>Restore</button></div></div>)}
    {message && <p role="status">{message}</p>}
  </section>;
}
