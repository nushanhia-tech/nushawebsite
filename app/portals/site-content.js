import { useEffect, useState } from "react";
import PhotoUpload from "./photo-upload";

const fields = {
  events: [
    ["title", "Title"],
    ["event_date", "Date label"],
    ["description", "Description"],
    ["image", "Photo"],
  ],
  highlights: [
    ["title", "Title"],
    ["event_date", "Date label"],
    ["description", "Description"],
    ["image", "Photo"],
  ],
  updates: [
    ["content", "Update"],
    ["tag", "Tag"],
    ["time_label", "Date label"],
  ],
  leadership: [
    ["name", "Name"],
    ["position", "Position"],
    ["committee_year", "Committee year", "number"],
    ["photo", "Photo"],
  ],
  settings: [
    ["outreach_status", "Outreach access"],
    ["website_status", "Main website visibility"],
  ],
};

export default function SiteContent({ db, outreachOnly = false }) {
  const [rows, setRows] = useState([]);
  const [section, setSection] = useState(outreachOnly ? "settings" : "events");
  const [draft, setDraft] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);

  async function refresh(signal) {
    let request = db
      .from("site_content")
      .select("id,content")
      .eq("section", section)
      .order("id", { ascending: false });
    if (signal) request = request.abortSignal(signal);
    const { data, error } = await request;
    if (signal?.aborted) return;
    setLoading(false);
    if (error) setMessage(error.message);
    else setRows(data);
  }

  useEffect(() => {
    const controller = new AbortController();
    refresh(controller.signal);
    return () => controller.abort();
  }, [section]);

  async function mutate(operation, success) {
    setBusy(true);
    setMessage("");
    try {
      const { error } = await operation;
      if (error) throw error;
      setDraft(null);
      setMessage(success);
      await refresh();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }

  function save(event) {
    event.preventDefault();
    const content = {
      ...draft.content,
      ...Object.fromEntries(new FormData(event.currentTarget)),
    };
    if (section === "leadership")
      content.committee_year = content.committee_year
        ? Number(content.committee_year)
        : null;
    const table = db.from("site_content");
    const operation = draft.id
      ? table.update({ content }).eq("id", draft.id)
      : table.insert({ section, content });
    mutate(operation, "Website content saved.");
  }

  const current = section === "settings"
    ? rows.filter((row) => row.content.outreach_status || row.content.website_status)
    : rows;
  return (
    <section className="panel site-content">
      <h2>{outreachOnly ? "Outreach visibility" : "Public website content"}</h2>
      {!outreachOnly && (
        <label>
          Section{" "}
          <select
            value={section}
            disabled={busy}
            onChange={(event) => {
              setRows([]);
              setLoading(true);
              setSection(event.target.value);
              setDraft(null);
              setMessage("");
            }}
          >
            {Object.keys(fields).map((name) => (
              <option key={name} value={name}>
                {name[0].toUpperCase() + name.slice(1)}
              </option>
            ))}
          </select>
        </label>
      )}
      {current.map((row) => (
        <div className="content-row" key={row.id}>
          <span>
            {section === "settings"
              ? `Website ${row.content.website_status || "on"} · outreach ${row.content.outreach_status || "open"}`
              : row.content[fields[section][0][0]]}
          </span>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => setDraft(row)}
          >
            Edit
          </button>
          {section !== "settings" && (
            <button
              className="secondary"
              disabled={busy}
              onClick={() => {
                if (window.confirm("Delete this website entry?")) {
                  if (draft?.id === row.id) setDraft(null);
                  mutate(
                    db.from("site_content").delete().eq("id", row.id),
                    "Website entry deleted.",
                  );
                }
              }}
            >
              Delete
            </button>
          )}
        </div>
      ))}
      {loading && (
        <p className="empty" role="status">
          Loading entries…
        </p>
      )}
      {!loading && !current.length && (
        <p className="empty">No entries in this section.</p>
      )}
      {!loading &&
        !draft &&
        (section !== "settings" || current.length === 0) && (
          <button
            className="primary"
            disabled={busy}
            onClick={() => setDraft({ content: {} })}
          >
            Add entry
          </button>
        )}
      {draft && (
        <form key={draft.id || "new"} onSubmit={save}>
          {fields[section].map(([name, label, type]) => (
            <label key={name}>
              {label}
              {name.endsWith("_status") ? (
                <select
                  name={name}
                  defaultValue={draft.content[name] || (name === "website_status" ? "on" : "open")}
                >
                  {name === "website_status" ? (
                    <><option value="on">On — visible to visitors</option><option value="off">Off — hidden from visitors</option></>
                  ) : (
                    <><option value="open">Open</option><option value="closed">Closed</option></>
                  )}
                </select>
              ) : name === "image" || name === "photo" ? (
                <PhotoUpload db={db} name={name} label="Attach image" value={draft.content[name] || ""} />
              ) : (
                <input
                  name={name}
                  defaultValue={draft.content[name] ?? ""}
                  type={type || "text"}
                  min={type === "number" ? 1900 : undefined}
                  max={type === "number" ? 2100 : undefined}
                  maxLength={2000}
                  required={!type}
                />
              )}
            </label>
          ))}
          <div className="dialog-actions">
            <button className="primary" disabled={busy}>
              Save
            </button>
            <button
              className="secondary"
              type="button"
              disabled={busy}
              onClick={() => setDraft(null)}
            >
              Cancel
            </button>
          </div>
        </form>
      )}
      {message && <p role="status">{message}</p>}
    </section>
  );
}
