import { useEffect, useState } from "react";

const pages = { index: "Home", about: "About", events: "Events", calendar: "Event calendar", leadership: "Leadership", ranking: "Ranking", outreach: "Outreach", contact: "Contact" };

export default function PageVisibility({ db }) {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  useEffect(() => {
    let active = true;
    db.from("public_page_visibility").select("page,visible").then(({ data, error }) => {
      if (!active) return;
      if (error) setMessage("Page controls are unavailable. Apply the public page visibility migration in Supabase.");
      else setSettings(Object.fromEntries(data.map((row) => [row.page, row.visible])));
      setLoading(false);
    });
    return () => { active = false; };
  }, [db]);
  async function toggle(page, visible) {
    setBusy(true); setMessage("");
    const { error } = await db.from("public_page_visibility").upsert({ page, visible }, { onConflict: "page" });
    if (error) setMessage(error.message);
    else { setSettings((current) => ({ ...current, [page]: visible })); setMessage("Visibility saved. The public site may take a few minutes to refresh."); }
    setBusy(false);
  }
  return <section className="panel site-content"><h2>Public page visibility</h2><p>Turn website pages on or off. Hidden pages are removed from public navigation and cannot be opened directly.</p>
    {loading ? <p role="status">Loading page controls…</p> : Object.entries(pages).map(([page,label]) => <div className="content-row" key={page}><span><strong>{label}</strong><br /><small>{settings[page] === false ? "Hidden" : "Visible"}</small></span><button className="secondary" disabled={busy || Boolean(message && message.startsWith("Page controls are unavailable"))} aria-label={`${settings[page] === false ? "Show" : "Hide"} ${label}`} onClick={() => toggle(page, settings[page] === false)}>{settings[page] === false ? "Show" : "Hide"}</button></div>)}
    {message && <p role="status">{message}</p>}
  </section>;
}
