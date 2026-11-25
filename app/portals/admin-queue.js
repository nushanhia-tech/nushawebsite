import { useEffect, useState } from "react";
import { ArrowUpRight } from "lucide-react";
import { kinds, portals } from "../data";

const PAGE_SIZE = 12;

export default function AdminQueue({ records, initialFilter, open, loading }) {
  const [kind, setKind] = useState(initialFilter);
  const [scope, setScope] = useState(
    initialFilter === "all" ? "pending" : "all",
  );
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [month, setMonth] = useState("");

  useEffect(() => {
    setKind(initialFilter);
    setScope(initialFilter === "all" ? "pending" : "all");
    setQuery("");
    setMonth("");
    setPage(1);
  }, [initialFilter]);

  const shown = records.filter((item) => {
    const term = query.trim().toLowerCase();
    return (
      (kind === "all" || item.kind === kind) &&
      (scope === "all" || item.status === "Pending") &&
      (!term ||
        [
          item.title,
          item.sender,
          item.recipient?.display_name,
          item.recipient?.member_number,
          item.admin_details?.admission,
        ].some((value) => value?.toLowerCase().includes(term))) &&
      (!month ||
        (
          item.admin_details?.month ||
          item.scheduled_on ||
          item.created_at ||
          ""
        ).slice(0, 7) === month)
    );
  });
  const pages = Math.max(1, Math.ceil(shown.length / PAGE_SIZE));
  const visiblePage = Math.min(page, pages);
  const pendingCount = records.filter(
    (item) => item.status === "Pending",
  ).length;

  return (
    <section
      className="panel admin-queue"
      aria-labelledby="queue-heading"
      aria-busy={loading}
    >
      <div className="admin-section-head">
        <div>
          <h2 id="queue-heading">Work queue</h2>
          <p>{loading ? "Loading items…" : pendingCount + " pending"}</p>
        </div>
        <label className="admin-control">
          <span>New item</span>
          <select
            value=""
            onChange={(event) => {
              if (event.target.value)
                open({ type: "create", kind: event.target.value });
            }}
          >
            <option value="">Choose an action</option>
            {portals.admin.create.map((value) => (
              <option key={value} value={value}>
                {kinds[value].action}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="admin-queue-filters">
        <label className="admin-control">
          Status
          <select
            value={scope}
            onChange={(event) => {
              setScope(event.target.value);
              setPage(1);
            }}
          >
            <option value="pending">Pending</option>
            <option value="all">All statuses</option>
          </select>
        </label>
        <label className="admin-control">
          Type
          <select
            value={kind}
            onChange={(event) => {
              setKind(event.target.value);
              setPage(1);
            }}
          >
            <option value="all">All types</option>
            {portals.admin.kinds.map((value) => (
              <option key={value} value={value}>
                {kinds[value].label}
              </option>
            ))}
          </select>
        </label>
        <label className="admin-control">
          Search
          <input
            type="search"
            value={query}
            placeholder="Title, name, or admission number"
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
          />
        </label>
      </div>

      <label className="admin-control" style={{ maxWidth: 190 }}>
        Month
        <input
          type="month"
          value={month}
          onChange={(event) => {
            setMonth(event.target.value);
            setPage(1);
          }}
        />
      </label>
      {shown.length > 0 ? (
        <ul className="admin-queue-list">
          {shown
            .slice((visiblePage - 1) * PAGE_SIZE, visiblePage * PAGE_SIZE)
            .map((item) => (
              <li key={item.id}>
                <button onClick={() => open({ type: "detail", item })}>
                  <span className="admin-item-title">
                    <strong>{item.title}</strong>
                    <small>
                      {[
                        kinds[item.kind].label,
                        item.recipient?.display_name || item.sender,
                        item.recipient?.member_number,
                      ]
                        .filter(Boolean)
                        .join(" · ")}
                    </small>
                  </span>
                  <span
                    className={
                      "tag" + (item.status === "Pending" ? " tag-due" : "")
                    }
                  >
                    {item.status}
                  </span>
                  <ArrowUpRight size={16} aria-hidden="true" />
                </button>
              </li>
            ))}
        </ul>
      ) : (
        <p className="admin-empty">
          {loading
            ? "Getting your work queue…"
            : scope === "pending" && kind === "all" && !query && !month
              ? "You’re all caught up."
              : "No matching items."}
        </p>
      )}

      {pages > 1 && (
        <nav className="admin-pagination" aria-label="Work queue pages">
          <button
            className="secondary"
            disabled={visiblePage === 1}
            onClick={() => setPage(visiblePage - 1)}
          >
            Previous
          </button>
          <span>
            Page {visiblePage} of {pages}
          </span>
          <button
            className="secondary"
            disabled={visiblePage === pages}
            onClick={() => setPage(visiblePage + 1)}
          >
            Next
          </button>
        </nav>
      )}
    </section>
  );
}
