import { useEffect, useState } from "react";

const fields = {
  programme_name: "Programme",
  category: "Category",
  first_place: "First place",
  second_place: "Second place",
  third_place: "Third place",
  grade_a: "A grade",
  grade_b: "B grade",
  grade_c: "C grade",
};

export function standingsDetail(row) {
  return Object.keys(fields)
    .slice(2)
    .filter((key) => row[key])
    .map((key) => fields[key] + ": " + row[key])
    .join(" · ");
}

export default function ProgrammeStandings({ db, role }) {
  const [opened, setOpened] = useState(false);
  const [items, setItems] = useState([]);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [search, setSearch] = useState("");

  async function refresh() {
    const result =
      role === "admin"
        ? await db.rpc("admin_programme_standings")
        : await db
            .from("programme_standings")
            .select("*")
            .order("created_at", { ascending: false });
    if (result.error) throw result.error;
    setItems(result.data);
  }

  async function run(task) {
    setBusy(true);
    setMessage("");
    try {
      await task();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    if (opened && db) run(refresh);
  }, [opened, db]);

  function publish(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    run(async () => {
      const { error } = await db.from("programme_standings").insert(values);
      if (error) throw error;
      form.reset();
      await refresh();
      setMessage("Programme standings published.");
    });
  }

  const visible = items.filter((item) =>
    [item.programme_name, item.category, item.wing_name].some((value) =>
      value?.toLowerCase().includes(search.trim().toLowerCase()),
    ),
  );
  return (
    <section className="panel site-content">
      <details
        className="admin-disclosure"
        onToggle={(event) => setOpened(event.currentTarget.open)}
      >
        <summary>
          {role === "wing"
            ? "Publish programme placements and grades"
            : "Programme placements and grades"}
        </summary>
        {!db && <p>Connect Supabase to manage programme standings.</p>}
        {db && role === "wing" && (
          <form className="account-form" onSubmit={publish}>
            {Object.entries(fields).map(([key, label], index) => (
              <label key={key}>
                {label}
                <input
                  name={key}
                  required={key === "programme_name"}
                  maxLength={index === 0 ? 160 : index === 1 ? 120 : 300}
                />
              </label>
            ))}
            <button className="primary" disabled={busy}>
              Publish standings
            </button>
          </form>
        )}
        {items.length > 0 && (
          <label>
            Search programmes
            <input
              className="admin-input"
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </label>
        )}
        {visible.map((item) => (
          <div className="content-row" key={item.id}>
            <div>
              <strong>{item.programme_name}</strong>
              <p>
                {[item.wing_name, item.category, standingsDetail(item)]
                  .filter(Boolean)
                  .join(" · ")}
              </p>
            </div>
            <button
              className="secondary"
              disabled={busy}
              onClick={() => {
                if (!window.confirm("Delete these programme standings?"))
                  return;
                run(async () => {
                  const { error } = await db
                    .from("programme_standings")
                    .delete()
                    .eq("id", item.id);
                  if (error) throw error;
                  await refresh();
                  setMessage("Programme standings deleted.");
                });
              }}
            >
              Delete
            </button>
          </div>
        ))}
        {db && !busy && !visible.length && <p>No programme standings found.</p>}
        <p role="status">{message}</p>
      </details>
    </section>
  );
}
