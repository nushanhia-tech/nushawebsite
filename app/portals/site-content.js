import { useEffect, useState } from "react";

const fields = {
  events: [
    ["title", "Title"],
    ["event_date", "Date label"],
    ["description", "Description"],
    ["image", "Image URL", "url"],
  ],
  highlights: [
    ["title", "Title"],
    ["event_date", "Date label"],
    ["description", "Description"],
    ["image", "Image URL", "url"],
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
    ["photo", "Photo URL", "url"],
  ],
  settings: [["outreach_status", "Outreach access"]],
};

export default function SiteContent({ db }) {
  const [rows, setRows] = useState([]);
  const [section, setSection] = useState("events");
  const [draft, setDraft] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  async function refresh() {
    const { data, error } = await db
      .from("site_content")
      .select("*")
      .order("id", { ascending: false });
    if (error) setMessage(error.message);
    else setRows(data);
  }

  useEffect(() => {
    refresh();
  }, []);

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
    const content = Object.fromEntries(new FormData(event.currentTarget));
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

  const current = rows.filter((row) => row.section === section);
  return (
    <section className="panel site-content">
      <h2>Public website content</h2>
      <label>
        Section{" "}
        <select
          value={section}
          disabled={busy}
          onChange={(event) => {
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
      {current.map((row) => (
        <div className="content-row" key={row.id}>
          <span>{row.content[fields[section][0][0]]}</span>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => setDraft(row)}
          >
            Edit
          </button>
        </div>
      ))}
      {!current.length && <p className="empty">No entries in this section.</p>}
      {!draft && (section !== "settings" || current.length === 0) && (
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
              {name === "outreach_status" ? (
                <select
                  name={name}
                  defaultValue={draft.content[name] || "open"}
                >
                  <option value="open">Open</option>
                  <option value="closed">Closed</option>
                </select>
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
