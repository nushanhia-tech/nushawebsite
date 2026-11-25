"use client";

import { useEffect, useRef, useState } from "react";
import dynamic from "next/dynamic";
import AdminQueue from "./admin-queue";
import { AdminEditFields, OutreachFields } from "./admin-item-fields";
import { CardPreview, defaultCard } from "./id-card";
import { supabaseProjectUrl } from "../../lib/supabase-url";

const SiteContent = dynamic(() => import("./site-content"), {
  loading: () => <p role="status">Loading website editor…</p>,
});
const Accounts = dynamic(() => import("./accounts"), {
  loading: () => <p role="status">Loading accounts…</p>,
});
const AdminTools = dynamic(() => import("./admin-tools"), {
  loading: () => <p role="status">Loading tools…</p>,
});
const CardDesigner = dynamic(() => import("./card-designer"), {
  loading: () => <p role="status">Loading card designer…</p>,
});
const ProgrammeStandings = dynamic(() => import("./programme-standings"), {
  loading: () => <p role="status">Loading programme results…</p>,
});
import { createClient } from "@supabase/supabase-js";
import {
  ArrowDownToLine,
  ArrowRight,
  ArrowUpRight,
  Bell,
  Calendar,
  Check,
  ChevronDown,
  CircleHelp,
  Clock,
  GraduationCap,
  Layers3,
  LayoutGrid,
  List,
  LogOut,
  Plus,
  Printer,
  Search,
  ShieldCheck,
  Sparkles,
  Users,
  X,
} from "lucide-react";
import { demoItems, kinds, nextStatuses, portals, stages } from "../data";

const url = supabaseProjectUrl(process.env.NEXT_PUBLIC_SUPABASE_URL);
const key = process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY;
const db = url && key ? createClient(url, key) : null;
const roleIcons = {
  student: GraduationCap,
  wing: Layers3,
  class: Users,
  printer: Printer,
  admin: ShieldCheck,
};
const stageIcons = { review: Clock, progress: Layers3, done: Check };
const dateFormatter = new Intl.DateTimeFormat("en-IN", {
  day: "numeric",
  month: "short",
  timeZone: "Asia/Kolkata",
});
const dateLabel = (value) => dateFormatter.format(new Date(value));
const initialDemo = Object.fromEntries(
  Object.keys(portals).map((role) => [role, demoItems(role)]),
);

export default function Home() {
  const [role, setRole] = useState("admin");
  const [records, setRecords] = useState([]);
  const [recordsLoading, setRecordsLoading] = useState(Boolean(db));
  const [adminSection, setAdminSection] = useState("queue");
  const [queueVersion, setQueueVersion] = useState(0);
  const [cardDesign, setCardDesign] = useState(defaultCard);
  const [demo, setDemo] = useState(initialDemo);
  const [session, setSession] = useState(null);
  const [profile, setProfile] = useState(null);
  const [loaded, setLoaded] = useState(!db);
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [view, setView] = useState("board");
  const [modal, setModal] = useState(null);
  const [notice, setNotice] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const dialog = useRef(null);

  async function refresh(signal, profileRole = profile?.role) {
    let query = db
      .from("work_items")
      .select("*")
      .order("created_at", { ascending: false });
    if (signal) query = query.abortSignal(signal);
    const result = await query;
    if (signal?.aborted) return;
    setRecordsLoading(false);
    if (result.error) setNotice(result.error.message);
    else {
      setRecords(result.data);
      if (profileRole === "admin") {
        const ids = [
          ...new Set(
            result.data
              .flatMap((item) => [item.owner_id, item.recipient_id])
              .filter(Boolean),
          ),
        ];
        if (ids.length) {
          const { data } = await db.rpc("admin_record_people", { ids });
          if (data && !signal?.aborted) {
            const people = new Map(data.map((person) => [person.id, person]));
            setRecords((current) =>
              current.map((item) => ({
                ...item,
                sender: people.get(item.owner_id)?.display_name,
                recipient: people.get(item.recipient_id),
              })),
            );
          }
        }
      }
    }
  }

  useEffect(() => {
    const requested = new URLSearchParams(window.location.search).get("role");
    if (requested && Object.hasOwn(portals, requested)) setRole(requested);
  }, []);

  useEffect(() => {
    if (!db) return;
    let handoff;
    try {
      handoff = sessionStorage.getItem("nusha-portal-session");
      sessionStorage.removeItem("nusha-portal-session");
    } catch {
      // Storage may be disabled by the browser.
    }
    const { data } = db.auth.onAuthStateChange((_event, current) => {
      setSession(current);
      if (!handoff) setLoaded(true);
    });
    if (handoff) {
      let tokens;
      try {
        tokens = JSON.parse(handoff);
      } catch {
        tokens = null;
      }
      if (!tokens) setLoaded(true);
      else {
        db.auth
          .setSession(tokens)
          .then(({ error }) => {
            if (error) setNotice("Sign-in expired. Please try again.");
          })
          .catch(() => setNotice("Sign-in was interrupted. Please try again."))
          .finally(() => setLoaded(true));
      }
    }
    return () => data.subscription.unsubscribe();
  }, []);

  const userId = session?.user.id;
  useEffect(() => {
    if (!db || !userId) return;
    let active = true;
    const controller = new AbortController();
    setRecords([]);
    setRecordsLoading(true);
    async function load() {
      const result = await db
        .from("profiles")
        .select("*")
        .eq("id", userId)
        .abortSignal(controller.signal)
        .single();
      if (!active) return;
      if (result.error)
        setNotice(
          "Your account needs a portal role. Contact the union administrator.",
        );
      else {
        setRole(result.data.role);
        setProfile(result.data);
        await refresh(controller.signal, result.data.role);
      }
    }
    load();
    return () => {
      active = false;
      controller.abort();
    };
  }, [userId]);

  useEffect(() => {
    if (!db || !profile?.id || profile.role !== "student") return;
    let active = true;
    db.rpc("portal_card_design").then(({ data }) => {
      if (active && data) setCardDesign({ ...defaultCard, ...data });
    });
    return () => {
      active = false;
    };
  }, [profile?.id, profile?.role]);

  useEffect(() => {
    if (modal) dialog.current?.showModal();
    else dialog.current?.close();
  }, [modal]);

  const portal = portals[role];
  const RoleIcon = roleIcons[role];
  const name = profile?.display_name || portal.identity;
  const recordsForRole = (db ? records : demo[role]).filter((item) =>
    portal.kinds.includes(item.kind),
  );
  const shown = recordsForRole.filter(
    (item) =>
      (filter === "all" || item.kind === filter) &&
      [item.title, item.detail, kinds[item.kind].label, item.status].some(
        (value) => value?.toLowerCase().includes(search.toLowerCase()),
      ),
  );
  const outstanding = recordsForRole.filter(
    (item) => item.kind === "due" && item.status === "Pending",
  );
  const upiId = process.env.NEXT_PUBLIC_UPI_ID;

  function selectRole(value) {
    setRole(value);
    setFilter("all");
    setSearch("");
    setNotice("");
  }

  function open(value) {
    setError("");
    setModal(value);
  }

  async function signIn(event) {
    event.preventDefault();
    setBusy(true);
    setNotice("");
    const values = new FormData(event.currentTarget);
    try {
      const response = await fetch("/api/portal/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: values.get("username"),
          password: values.get("password"),
          role: new URLSearchParams(window.location.search).get("role"),
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error);
      const { error } = await db.auth.setSession(result.session);
      if (error) throw error;
    } catch (error) {
      setNotice(
        error.message || "Sign-in is temporarily unavailable. Try again.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function create(event) {
    event.preventDefault();
    setBusy(true);
    setError("");
    const values = Object.fromEntries(new FormData(event.currentTarget));
    delete values.kind;
    const {
      target_role,
      category,
      author_name,
      author_role,
      thumbnail,
      author_photo,
      publish,
    } = values;
    for (const key of [
      "target_role",
      "category",
      "author_name",
      "author_role",
      "thumbnail",
      "author_photo",
      "publish",
    ])
      delete values[key];
    const item = {
      ...values,
      title: values.title.trim(),
      kind: modal.kind,
      amount: values.amount ? Number(values.amount) : null,
    };
    if (target_role) item.target_role = target_role;
    if (
      modal.kind === "outreach" &&
      (category !== "Article" ||
        author_name ||
        author_role ||
        thumbnail ||
        author_photo ||
        publish === "true")
    ) {
      item.admin_details = {
        category,
        author_name,
        author_role,
        thumbnail,
        author_photo,
        publish,
      };
    }
    try {
      if (!item.title) throw new Error("Enter a title.");
      if (db) {
        const result = await db.from("work_items").insert(item);
        if (result.error) throw result.error;
        await refresh();
      } else {
        const status =
          ["notice", "result"].includes(item.kind) || publish === "true"
            ? "Published"
            : "Pending";
        setDemo((previous) => ({
          ...previous,
          [role]: [
            {
              ...item,
              status,
              id: crypto.randomUUID(),
              created_at: new Date().toISOString(),
            },
            ...previous[role],
          ],
        }));
      }
      setFilter(item.kind);
      setQueueVersion((value) => value + 1);
      setSearch("");
      setNotice("Saved.");
      setModal(null);
    } catch (failure) {
      setError(failure.message || "The item could not be saved.");
    } finally {
      setBusy(false);
    }
  }

  async function transition(event, item, status) {
    event.preventDefault();
    setBusy(true);
    setError("");
    const values = new FormData(event.currentTarget);
    const score = values.has("score") ? Number(values.get("score")) : null;
    try {
      if (db) {
        const result =
          status === "Paid"
            ? await db.rpc("admin_manage_item", {
                item_id: item.id,
                operation: "mark_paid",
              })
            : await db.rpc("advance_item", {
                item_id: item.id,
                next_status: status,
                points: score,
              });
        if (result.error) throw result.error;
        await refresh();
      } else {
        setDemo((previous) => ({
          ...previous,
          [role]: previous[role].map((row) =>
            row.id === item.id
              ? { ...row, status, score }
              : row.kind === "due" &&
                  row.id === item.related_id &&
                  status === "Verified"
                ? { ...row, status: "Paid" }
                : row,
          ),
        }));
      }
      setNotice("Status updated.");
      setModal(null);
    } catch (failure) {
      setError(failure.message || "The status could not be updated.");
    } finally {
      setBusy(false);
    }
  }

  async function manageItem(item, operation, fields = {}) {
    setBusy(true);
    setError("");
    try {
      if (db) {
        const { error } = await db.rpc("admin_manage_item", {
          item_id: item.id,
          operation,
          fields,
        });
        if (error) throw error;
        await refresh();
      } else {
        setDemo((previous) => ({
          ...previous,
          [role]: previous[role].flatMap((row) => {
            if (row.id !== item.id) return [row];
            if (operation === "delete") return [];
            const updated = {
              ...row,
              title: fields.title,
              detail: fields.detail,
            };
            return [
              item.kind === "outreach"
                ? {
                    ...updated,
                    public_details: { ...row.public_details, ...fields },
                  }
                : {
                    ...updated,
                    scheduled_on: fields.scheduled_on,
                    score: fields.score === "" ? null : Number(fields.score),
                    status: fields.score === "" ? "Pending" : "Evaluated",
                    admin_details: fields,
                  },
            ];
          }),
        }));
      }
      setNotice(operation === "delete" ? "Item deleted." : "Item updated.");
      setModal(null);
    } catch (error) {
      setError(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function exportReport(event) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const values = new FormData(event.currentTarget);
      const type = values.get("reportType");
      const month = values.get("month");
      const matches = recordsForRole.filter(
        (item) =>
          (type === "calendar"
            ? item.kind === "booking" &&
              ["Approved", "Conducted"].includes(item.status)
            : item.kind === "result") &&
          (!month ||
            (item.scheduled_on || item.created_at || "").slice(0, 7) === month),
      );
      if (db && type === "results") {
        const { data, error } = await db.rpc("admin_programme_standings");
        if (error && error.code !== "PGRST202") throw error;
        const { standingsDetail } = await import("./programme-standings");
        for (const row of data ?? []) {
          if (month && row.created_at.slice(0, 7) !== month) continue;
          matches.push({
            title: [row.programme_name, row.category]
              .filter(Boolean)
              .join(" · "),
            detail: standingsDetail(row),
            scheduled_on: row.created_at.slice(0, 10),
            sender: row.wing_name,
            status: "Published",
          });
        }
      }
      const { downloadProgrammeReport } = await import("./admin-pdf");
      await downloadProgrammeReport(matches, type);
      setNotice("PDF downloaded.");
      setModal(null);
    } catch (error) {
      setError(error.message || "The PDF could not be created.");
    } finally {
      setBusy(false);
    }
  }

  if (!loaded)
    return (
      <main className="login">
        <h1>Opening your workspace…</h1>
      </main>
    );
  if (db && (!session || profile?.id !== userId))
    return (
      <main className="login">
        <section className="panel">
          <Brand />
          <h1>{session ? "Portal access" : "Sign in"}</h1>
          <p>
            <a href="/">Back to the website</a>
          </p>
          {session ? (
            <button
              className="primary"
              onClick={async () => {
                await db.auth.signOut();
                setProfile(null);
              }}
            >
              Sign out
            </button>
          ) : (
            <form onSubmit={signIn}>
              <label>
                Username
                <input
                  type="text"
                  name="username"
                  maxLength={40}
                  autoCapitalize="none"
                  required
                  autoComplete="username"
                />
              </label>
              <label>
                Password
                <input
                  type="password"
                  name="password"
                  required
                  autoComplete="current-password"
                />
              </label>
              <button className="primary" disabled={busy}>
                Sign in <ArrowRight size={16} />
              </button>
            </form>
          )}
          <p role="status">{notice}</p>
        </section>
      </main>
    );

  return (
    <div className="shell">
      <aside className="rail" aria-label="Portal navigation">
        <a className="mark" href="/" aria-label="NUS’HA home">
          <span className="nusha-mark" aria-hidden="true" />
        </a>
        <div className="rail-links">
          {Object.entries(portals)
            .filter(([id]) => !db || id === role)
            .map(([id, entry]) => {
              const Icon = roleIcons[id];
              return (
                <button
                  className={id === role ? "rail-active" : ""}
                  key={id}
                  title={entry.name}
                  aria-label={entry.name + " portal"}
                  aria-current={id === role ? "page" : undefined}
                  onClick={() => selectRole(id)}
                >
                  <Icon size={19} />
                  <span>{entry.name}</span>
                </button>
              );
            })}
        </div>
        {db && (
          <button
            className="signout"
            title="Sign out"
            aria-label="Sign out"
            onClick={async () => {
              const result = await db.auth.signOut();
              if (result.error) setNotice(result.error.message);
              else {
                setProfile(null);
                setRecords([]);
                setNotice("");
              }
            }}
          >
            <LogOut size={19} />
          </button>
        )}
      </aside>
      <main className="workspace">
        <header className="topbar">
          <div className="crumb">
            <Brand />
            <span>/</span>
            <span>{portal.name} portal</span>
          </div>
          <div className="account">
            <button
              className="icon-button"
              title="Announcements"
              aria-label="Announcements"
              onClick={() => {
                setAdminSection("queue");
                setFilter("notice");
                setQueueVersion((value) => value + 1);
                setSearch("");
              }}
            >
              <Bell size={18} />
            </button>
            <div className="avatar" aria-hidden="true">
              {name
                .split(" ")
                .slice(0, 2)
                .map((part) => part[0])
                .join("")}
            </div>
            <div className="account-name">
              <strong>{name}</strong>
              <small>{portal.group}</small>
            </div>
          </div>
        </header>

        {role === "admin" ? (
          <section className="admin-intro">
            <h1>Admin Portal</h1>
            <p>
              Review requests, create accounts, and update the public website.
            </p>
            {db && profile && !Object.hasOwn(profile, "active") && (
              <p role="status">
                Apply the Admin Tools migration in Supabase to enable the new
                database actions.
              </p>
            )}
            <nav className="admin-nav" aria-label="Admin sections">
              {[
                ["queue", "Work queue"],
                ["accounts", "Accounts"],
                ["website", "Website"],
                ["tools", "Tools"],
              ].map(([value, label]) => (
                <button
                  key={value}
                  type="button"
                  aria-pressed={adminSection === value}
                  onClick={() => {
                    if (value === "queue" && adminSection !== value)
                      setFilter("all");
                    setAdminSection(value);
                  }}
                >
                  {label}
                </button>
              ))}
            </nav>
          </section>
        ) : (
          <section className="hero">
            <div className="hero-content">
              <div className="eyebrow">
                <RoleIcon size={16} /> {portal.name} portal
              </div>
              <h1>{portal.title}</h1>
              <p>{portal.description}</p>
              {portal.create.length > 0 && (
                <button
                  className="primary"
                  onClick={() =>
                    open({ type: "create", kind: portal.create[0] })
                  }
                >
                  <Plus size={17} />
                  {kinds[portal.create[0]].action}
                </button>
              )}
            </div>
            <div className="hero-art" aria-hidden="true">
              <div className="orb orbit-one" />
              <div className="orb orbit-two" />
              <div className="orb orbit-three" />
              <div className="emblem">
                <RoleIcon strokeWidth={1.2} size={56} />
              </div>
              <Sparkles className="spark" size={24} />
            </div>
          </section>
        )}

        {!db && (
          <p className="demo-label">
            Demo workspace <span>·</span> Sample data
          </p>
        )}
        {notice && (
          <p className="message" role="status">
            {notice}
            <button aria-label="Dismiss message" onClick={() => setNotice("")}>
              <X size={16} />
            </button>
          </p>
        )}
        {role === "student" && (
          <div className="student-row">
            <section className="panel">
              <h2>Digital membership</h2>
              <CardPreview
                profile={{
                  display_name: name,
                  member_number:
                    profile?.member_number || (!db ? "NUS–0247" : "Student"),
                  photo_url: profile?.photo_url,
                }}
                design={cardDesign}
              />
            </section>
            <section className="panel">
              <small>Outstanding dues</small>
              <h2>
                ₹
                {outstanding
                  .reduce((sum, item) => sum + Number(item.amount), 0)
                  .toLocaleString("en-IN")}
              </h2>
              {outstanding.length > 0 && (
                <button
                  className="text-button"
                  onClick={() => setFilter("due")}
                >
                  View dues <ArrowUpRight size={14} />
                </button>
              )}
            </section>
          </div>
        )}

        {role === "admin" ? (
          <div className="admin-body">
            {adminSection === "queue" && (
              <AdminQueue
                key={queueVersion}
                records={recordsForRole}
                initialFilter={filter}
                open={open}
                loading={recordsLoading}
              />
            )}
            {adminSection === "accounts" &&
              (db ? (
                <Accounts db={db} activeId={userId} />
              ) : (
                <p className="panel">
                  Account creation uses a connected Supabase project.
                </p>
              ))}
            {adminSection === "website" &&
              (db ? (
                <SiteContent db={db} />
              ) : (
                <p className="panel">
                  Website editing uses a connected Supabase project.
                </p>
              ))}
            {adminSection === "tools" && (
              <div className="admin-tools-stack">
                <AdminTools db={db} refresh={refresh} open={open} />
                <ProgrammeStandings db={db} role="admin" />
                <CardDesigner db={db} />
              </div>
            )}
          </div>
        ) : (
          <section className="work" aria-labelledby="work-title">
            <div className="work-head">
              <div>
                <h2 id="work-title">
                  {role === "printer" ? "Print queue" : "Work overview"}
                </h2>
                <p>
                  {shown.length} {shown.length === 1 ? "item" : "items"}
                </p>
              </div>
              <div className="toolbar">
                <label className="search">
                  <Search size={17} />
                  <input
                    aria-label="Search items"
                    placeholder="Search items"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                  />
                </label>
                <div className="segmented" aria-label="View options">
                  <button
                    aria-label="Board view"
                    aria-pressed={view === "board"}
                    onClick={() => setView("board")}
                  >
                    <LayoutGrid size={17} />
                  </button>
                  <button
                    aria-label="List view"
                    aria-pressed={view === "list"}
                    onClick={() => setView("list")}
                  >
                    <List size={18} />
                  </button>
                </div>
              </div>
            </div>
            <div className="filter-row">
              <div className="filters" aria-label="Filter items">
                {["all", ...portal.kinds].map((kind) => (
                  <button
                    key={kind}
                    aria-pressed={filter === kind}
                    onClick={() => setFilter(kind)}
                  >
                    {kind === "all" ? "All items" : kinds[kind].label}
                  </button>
                ))}
              </div>
              {portal.create.length > 1 && (
                <div className="actions">
                  <ChevronDown size={16} />
                  <select
                    aria-label="More actions"
                    value=""
                    onChange={(event) => {
                      if (event.target.value)
                        open({ type: "create", kind: event.target.value });
                    }}
                  >
                    <option value="">More actions</option>
                    {portal.create.map((kind) => (
                      <option key={kind} value={kind}>
                        {kinds[kind].action}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>
            {view === "board" ? (
              <div className="board">
                {stages.map((stage, index) => {
                  const Icon = stageIcons[stage.icon];
                  const items = shown.filter((item) =>
                    stage.statuses.includes(item.status),
                  );
                  return (
                    <section className={"lane lane-" + index} key={stage.title}>
                      <h3>
                        <Icon size={17} />
                        {stage.title}
                        <span>{items.length}</span>
                      </h3>
                      {items.length ? (
                        items.map((item) => (
                          <Item
                            key={item.id}
                            item={item}
                            onClick={() => open({ type: "detail", item })}
                          />
                        ))
                      ) : (
                        <p className="empty">No items here.</p>
                      )}
                    </section>
                  );
                })}
              </div>
            ) : (
              <div className="list">
                {shown.length ? (
                  shown.map((item) => (
                    <Item
                      key={item.id}
                      item={item}
                      onClick={() => open({ type: "detail", item })}
                    />
                  ))
                ) : (
                  <p className="empty">No items match.</p>
                )}
              </div>
            )}
          </section>
        )}
        {role === "wing" && <ProgrammeStandings db={db} role="wing" />}
        <footer>
          <a href="/">NUS’HA public website</a>
          <button onClick={() => open({ type: "help" })}>
            <CircleHelp size={15} /> Workspace help
          </button>
        </footer>
      </main>

      <dialog ref={dialog} onClose={() => setModal(null)}>
        {modal && (
          <div className="dialog-body">
            <button
              className="close icon-button"
              aria-label="Close dialog"
              disabled={busy}
              onClick={() => setModal(null)}
            >
              <X size={20} />
            </button>
            {modal.type === "help" && (
              <>
                <h2>Workspace help</h2>
                <p>
                  Use the filters to find items and open a card to see details
                  or take action.{" "}
                  {db
                    ? "Contact the union office for account support."
                    : "Switch portals using the navigation. Demo changes last until the page is reloaded."}
                </p>
              </>
            )}
            {modal.type === "create" && (
              <>
                <h2>{kinds[modal.kind].action}</h2>
                <form onSubmit={create}>
                  <label>
                    Title
                    <input name="title" required maxLength={160} />
                  </label>
                  {kinds[modal.kind].fields.map(([label, field, type]) =>
                    field === "related_id" ? (
                      <label key={field}>
                        {label}
                        <select
                          name={field}
                          required
                          defaultValue={modal.relatedId || ""}
                        >
                          <option value="">Choose dues</option>
                          {outstanding.map((item) => (
                            <option key={item.id} value={item.id}>
                              {item.title} · ₹{item.amount}
                            </option>
                          ))}
                        </select>
                      </label>
                    ) : (
                      <label key={field}>
                        {label}
                        <input
                          name={field}
                          type={type || "text"}
                          maxLength={2000}
                          min={type === "number" ? ".01" : undefined}
                          step={type === "number" ? ".01" : undefined}
                          required
                        />
                      </label>
                    ),
                  )}
                  {role === "admin" && modal.kind === "notice" && (
                    <label>
                      Audience
                      <select name="target_role">
                        <option value="">Everyone</option>
                        <option value="student">Students</option>
                        <option value="class">Classes</option>
                        <option value="wing">Wings</option>
                        <option value="printer">Printer</option>
                        <option value="admin">Admins</option>
                      </select>
                    </label>
                  )}
                  {role === "admin" && modal.kind === "outreach" && (
                    <OutreachFields create />
                  )}
                  <button className="primary" disabled={busy}>
                    Save <Check size={16} />
                  </button>
                </form>
              </>
            )}
            {modal.type === "report" && (
              <>
                <h2>Download programme report</h2>
                <form onSubmit={exportReport}>
                  <label>
                    Report
                    <select name="reportType">
                      <option value="results">Programme results</option>
                      <option value="calendar">Official calendar</option>
                    </select>
                  </label>
                  <label>
                    Filter by month (optional)
                    <input type="month" name="month" />
                  </label>
                  <button className="primary" disabled={busy}>
                    Download PDF
                  </button>
                </form>
              </>
            )}
            {modal.type === "admin-edit" && (
              <>
                <h2>
                  Edit{" "}
                  {modal.item.kind === "outreach"
                    ? "outreach post"
                    : "submission"}
                </h2>
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    manageItem(
                      modal.item,
                      "edit",
                      Object.fromEntries(new FormData(event.currentTarget)),
                    );
                  }}
                >
                  <AdminEditFields item={modal.item} />
                  <button className="primary" disabled={busy}>
                    Save changes
                  </button>
                </form>
              </>
            )}
            {modal.type === "detail" && (
              <>
                <span className="tag">{modal.item.status}</span>
                <h2>{modal.item.title}</h2>
                <p className="detail">{modal.item.detail}</p>
                {modal.item.scheduled_on && (
                  <p>Scheduled for {dateLabel(modal.item.scheduled_on)}</p>
                )}
                {modal.item.score != null && <p>{modal.item.score} points</p>}
                {modal.item.amount != null && (
                  <p>Amount due ₹{modal.item.amount}</p>
                )}
                {role === "student" &&
                  modal.item.kind === "due" &&
                  modal.item.status === "Pending" && (
                    <div className="dialog-actions">
                      {upiId && (
                        <a
                          className="primary"
                          href={
                            "upi://pay?" +
                            new URLSearchParams({
                              pa: upiId,
                              pn: "NUS’HA",
                              am: modal.item.amount,
                              cu: "INR",
                            })
                          }
                        >
                          Open UPI app
                        </a>
                      )}
                      <button
                        className="primary"
                        onClick={() =>
                          open({
                            type: "create",
                            kind: "payment",
                            relatedId: modal.item.id,
                          })
                        }
                      >
                        Submit reference
                      </button>
                    </div>
                  )}
                {role === "printer" && (
                  <button
                    className="text-button"
                    onClick={() => window.print()}
                  >
                    <ArrowDownToLine size={16} /> Print job sheet
                  </button>
                )}
                {role === "admin" && modal.item.sender && (
                  <p>Submitted by: {modal.item.sender}</p>
                )}
                {role === "admin" && modal.item.recipient && (
                  <p>
                    Recipient: {modal.item.recipient.display_name}
                    {modal.item.recipient.member_number &&
                      " · " + modal.item.recipient.member_number}
                  </p>
                )}
                {role === "admin" && modal.item.kind === "activity" && (
                  <>
                    {modal.item.admin_details?.time && (
                      <p>Time: {modal.item.admin_details.time}</p>
                    )}
                    {modal.item.admin_details?.stage && (
                      <p>Stage: {modal.item.admin_details.stage}</p>
                    )}
                    {modal.item.admin_details?.month && (
                      <p>Submission month: {modal.item.admin_details.month}</p>
                    )}
                    {/^(https?:)\/\//i.test(
                      modal.item.admin_details?.file || "",
                    ) && (
                      <p>
                        <a
                          href={modal.item.admin_details.file}
                          target="_blank"
                          rel="noopener noreferrer"
                        >
                          Open submission file
                        </a>
                      </p>
                    )}
                  </>
                )}
                {role === "admin" && modal.item.target_role && (
                  <p>Audience: {modal.item.target_role}</p>
                )}
                {role === "admin" && modal.item.admin_details?.category && (
                  <p>Category: {modal.item.admin_details.category}</p>
                )}
                {role === "admin" &&
                  ["activity", "outreach"].includes(modal.item.kind) && (
                    <button
                      className="secondary"
                      disabled={busy}
                      onClick={() =>
                        open({ type: "admin-edit", item: modal.item })
                      }
                    >
                      Edit{" "}
                      {modal.item.kind === "activity" ? "submission" : "post"}
                    </button>
                  )}
                {role === "admin" &&
                  [
                    "activity",
                    "booking",
                    "due",
                    "notice",
                    "result",
                    "support",
                    "outreach",
                  ].includes(modal.item.kind) && (
                    <button
                      className="secondary admin-delete"
                      disabled={busy}
                      onClick={() => {
                        const message =
                          modal.item.kind === "due"
                            ? "Delete this due and its payment references?"
                            : "Delete this item permanently?";
                        if (window.confirm(message))
                          manageItem(modal.item, "delete");
                      }}
                    >
                      Delete item
                    </button>
                  )}
                {nextStatuses(role, modal.item).map((status) => (
                  <form
                    className="transition"
                    key={status}
                    onSubmit={(event) => transition(event, modal.item, status)}
                  >
                    {status === "Evaluated" && (
                      <label>
                        Points awarded
                        <input
                          name="score"
                          type="number"
                          min="0"
                          max="100"
                          required
                        />
                      </label>
                    )}
                    {status === "Paid" && (
                      <label className="confirmation">
                        <input type="checkbox" required /> I have confirmed this
                        payment.
                      </label>
                    )}
                    {status === "Verified" && (
                      <label className="confirmation">
                        <input type="checkbox" required /> I confirmed the
                        reference against the UPI transaction.
                      </label>
                    )}
                    <button
                      className={
                        status === "Rejected" ? "secondary" : "primary"
                      }
                      disabled={busy}
                    >
                      {status === "Verified"
                        ? "Verify and mark dues paid"
                        : {
                            Approved: "Approve",
                            Paid: "Mark paid",
                            Rejected: "Reject",
                            Evaluated: "Save evaluation",
                            Published: "Publish",
                            Resolved: "Resolve",
                          }[status] || "Mark " + status.toLowerCase()}
                    </button>
                  </form>
                ))}
              </>
            )}
            {error && (
              <p className="error" role="alert">
                {error}
              </p>
            )}
          </div>
        )}
      </dialog>
    </div>
  );
}

function Brand() {
  return (
    <strong className="brand">
      <span className="nusha-mark" aria-hidden="true" />
      <span className="brand-name">
        NUS’HA<span className="brand-caption"> Union Workspace</span>
      </span>
    </strong>
  );
}

function Item({ item, onClick }) {
  return (
    <button className="item" onClick={onClick}>
      <span className="item-top">
        <span className={"tag tag-" + item.kind}>{kinds[item.kind].label}</span>
        <ArrowUpRight size={16} />
      </span>
      <strong>{item.title}</strong>
      <span className="item-detail">
        {item.kind === "payment"
          ? "UPI reference submitted"
          : item.detail || (item.amount ? "₹" + item.amount : "")}
      </span>
      <span className="item-bottom">
        <span>
          <Calendar size={13} />
          {dateLabel(item.scheduled_on || item.created_at)}
        </span>
        <span>{item.status}</span>
      </span>
    </button>
  );
}
