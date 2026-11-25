"use client";

import { useEffect, useRef, useState } from "react";
import dynamic from "next/dynamic";
import AdminQueue from "./admin-queue";
import AdminNavigation, { PortalNavigation, adminSections } from "./admin-navigation";
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
  IdCard,
  Layers3,
  LayoutGrid,
  List,
  Plus,
  Printer,
  Search,
  ShieldCheck,
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

function PortalOverview({ role, records, createItems, onCreate }) {
  const pending = records.filter((item) => item.status === "Pending").length;
  const active = records.filter((item) => ["Approved", "In progress", "Ready"].includes(item.status)).length;
  const finished = records.filter((item) => ["Published", "Conducted", "Evaluated", "Collected", "Verified", "Paid", "Resolved"].includes(item.status)).length;
  const days = Array.from({ length: 7 }, (_, index) => {
    const day = new Date();
    day.setDate(day.getDate() - (6 - index));
    const key = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Kolkata", year: "numeric", month: "2-digit", day: "2-digit" }).format(day);
    const count = records.filter((item) => item.created_at && new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Kolkata", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date(item.created_at)) === key).length;
    return { day, count };
  });
  const maximum = Math.max(1, ...days.map((day) => day.count));
  const points = days.map(({ count }, index) => `${24 + index * 58},${142 - (count / maximum) * 112}`).join(" ");
  const activityTotal = days.reduce((sum, day) => sum + day.count, 0);
  const labels = new Intl.DateTimeFormat("en", { weekday: "short", timeZone: "Asia/Kolkata" });

  return (
    <section className="portal-overview" aria-label="Portal dashboard overview">
      <div className="portal-metrics">
        <article className="portal-metric portal-metric-primary"><span>Total tasks</span><strong>{records.length}</strong><small>Items in your workspace</small><ArrowUpRight size={19} aria-hidden="true" /></article>
        <article className="portal-metric"><span>In progress</span><strong>{active}</strong><small>Moving forward</small><ArrowUpRight size={19} aria-hidden="true" /></article>
        <article className="portal-metric"><span>Waiting</span><strong>{pending}</strong><small>Needs a next step</small><ArrowUpRight size={19} aria-hidden="true" /></article>
        <article className="portal-metric"><span>Completed</span><strong>{finished}</strong><small>Finished items</small><ArrowUpRight size={19} aria-hidden="true" /></article>
      </div>
      <div className="portal-insight-grid">
        <section className="panel portal-activity-card">
          <div className="portal-card-heading"><div><h2>Activity</h2><p>Your updates over the last seven days</p></div><span className="portal-period">7 days</span></div>
          <div className="portal-chart-wrap">
            <svg className="portal-chart" viewBox="0 0 420 178" role="img" aria-label={`${activityTotal} tasks created in the last seven days`}>
              <defs><linearGradient id="portal-chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#13865a" stopOpacity=".24" /><stop offset="100%" stopColor="#13865a" stopOpacity="0" /></linearGradient></defs>
              {[30, 70, 110, 150].map((y) => <line key={y} x1="18" x2="408" y1={y} y2={y} className="portal-chart-grid" />)}
              <polygon points={`24,150 ${points} 372,150`} fill="url(#portal-chart-fill)" />
              <polyline points={points} fill="none" stroke="#13865a" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
              {days.map(({ count }, index) => <circle key={index} cx={24 + index * 58} cy={142 - (count / maximum) * 112} r="4" fill="#fff" stroke="#13865a" strokeWidth="2" />)}
            </svg>
            <div className="portal-chart-labels">{days.map(({ day }, index) => <span key={index}>{labels.format(day)}</span>)}</div>
          </div>
          <div className="portal-chart-foot"><span><i /> New tasks</span><strong>{activityTotal} this week</strong></div>
        </section>
        <section className="panel portal-actions-card">
          <div className="portal-card-heading"><div><h2>Quick actions</h2><p>Start something new</p></div><span className="portal-action-icon"><LayoutGrid size={18} /></span></div>
          {createItems.length ? <div className="portal-quick-actions">{createItems.map((kind) => <button type="button" key={kind} onClick={() => onCreate(kind)}><span className="portal-quick-icon"><Plus size={18} /></span><span>{kinds[kind].action}<small>Open a new request</small></span><ArrowUpRight size={17} /></button>)}</div> : <p className="portal-no-actions">There are no new actions for this portal right now.</p>}
          <div className="portal-role-note"><span className="portal-role-dot" /><span>{role === "student" ? "Your student details and activity are up to date." : "Your submissions and updates appear in your task list."}</span></div>
        </section>
      </div>
    </section>
  );
}

export default function Home() {
  const [role, setRole] = useState("admin");
  const [records, setRecords] = useState([]);
  const [recordsLoading, setRecordsLoading] = useState(Boolean(db));
  const [adminSection, setAdminSection] = useState("queue");
  const [adminMenuOpen, setAdminMenuOpen] = useState(false);
  const adminHeading = useRef(null);
  const [queueVersion, setQueueVersion] = useState(0);
  const [cardDesign, setCardDesign] = useState(defaultCard);
  const [demo, setDemo] = useState(initialDemo);
  const [session, setSession] = useState(null);
  const [profile, setProfile] = useState(null);
  const [loaded, setLoaded] = useState(!db);
  const [filter, setFilter] = useState("all");
  const [portalSection, setPortalSection] = useState("overview");
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
    const params = new URLSearchParams(window.location.search);
    const requested = params.get("role");
    const section = params.get("section");
    if (Object.hasOwn(adminSections, section)) setAdminSection(section);
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
  const section = adminSections[adminSection];
  const isAdmin = role === "admin";
  const RoleIcon = roleIcons[role];
  const name = profile?.display_name || portal.identity;
  const recordsForRole = (db ? records : demo[role]).filter((item) =>
    portal.kinds.includes(item.kind),
  );
  const shown = recordsForRole.filter(
    (item) =>
      (portalSection === "overview" || portalSection === "tasks"
        ? filter === "all" || item.kind === filter
        : item.kind === portalSection) &&
      [item.title, item.detail, kinds[item.kind].label, item.status].some(
        (value) => value?.toLowerCase().includes(search.toLowerCase()),
      ),
  );
  const portalSectionTitle = portalSection === "overview"
    ? "Dashboard overview"
    : portalSection === "tasks"
      ? "All tasks"
      : portalSection === "notice"
        ? "Announcements"
        : portalSection === "membership"
          ? "Membership"
        : kinds[portalSection]?.label || "Portal details";
  const outstanding = recordsForRole.filter(
    (item) => item.kind === "due" && item.status === "Pending",
  );
  const upiId = process.env.NEXT_PUBLIC_UPI_ID;

  function navigateAdmin(section) {
    setAdminSection(section);
    setFilter("all");
    setAdminMenuOpen(false);
    const url = new URL(window.location.href);
    if (section === "queue") url.searchParams.delete("section");
    else url.searchParams.set("section", section);
    window.history.replaceState(window.history.state, "", url);
    if (adminMenuOpen)
      requestAnimationFrame(() => adminHeading.current?.focus());
  }

  async function signOut() {
    const result = await db.auth.signOut();
    if (result.error) setNotice(result.error.message);
    else {
      setProfile(null);
      setRecords([]);
      setNotice("");
      setAdminMenuOpen(false);
    }
  }

  function selectRole(value) {
    setRole(value);
    setFilter("all");
    setPortalSection("overview");
    setSearch("");
    setNotice("");
  }

  function selectPortalSection(value) {
    setPortalSection(value);
    setFilter(value === "overview" || value === "tasks" ? "all" : value);
    setView(value === "overview" ? "board" : "list");
    setSearch("");
    setAdminMenuOpen(false);
    window.scrollTo({ top: 0, behavior: "smooth" });
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
    <div className={"shell admin-shell" + (isAdmin ? "" : " portal-shell")}>
      {isAdmin ? (
        <AdminNavigation
          section={adminSection}
          navigate={navigateAdmin}
          signedIn={Boolean(db)}
          signOut={signOut}
          onRoleChange={selectRole}
          menuOpen={adminMenuOpen}
          setMenuOpen={setAdminMenuOpen}
        />
      ) : (
        <PortalNavigation
          className={"role-sidebar" + (adminMenuOpen ? " portal-mobile-open" : "")}
          role={role}
          kinds={portal.kinds}
          section={portalSection}
          onSelect={selectPortalSection}
          signedIn={Boolean(db)}
          signOut={signOut}
          menuOpen={adminMenuOpen}
          setMenuOpen={setAdminMenuOpen}
        />
      )}
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
                if (isAdmin) navigateAdmin("announcements");
                else {
                  selectPortalSection("notice");
                  setView("list");
                }
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
            <div className="admin-intro-content">
              <div>
                <h1 ref={adminHeading} tabIndex={-1}>
                  {section.label}
                </h1>
                <p>{section.description}</p>
              </div>
              <div className="admin-intro-actions">
                {section.action && (
                  <button
                    className="primary"
                    onClick={() =>
                      open({ type: "create", kind: section.action })
                    }
                  >
                    <Plus size={16} /> {kinds[section.action].action}
                  </button>
                )}
                {adminSection === "bookings" && (
                  <a className="secondary" href="/calendar.php">
                    Official calendar
                  </a>
                )}
                {["bookings", "results"].includes(adminSection) && (
                  <button
                    className="primary"
                    onClick={() =>
                      open({
                        type: "report",
                        reportType:
                          adminSection === "bookings" ? "calendar" : "results",
                      })
                    }
                  >
                    <ArrowDownToLine size={16} /> Download report
                  </button>
                )}
              </div>
            </div>
            {db && profile && !Object.hasOwn(profile, "active") && (
              <p className="admin-migration-notice" role="status">
                Apply the Admin Tools migration in Supabase to enable the new
                database actions.
              </p>
            )}
          </section>
        ) : (
          <section className="admin-intro">
            <div className="admin-intro-content">
              <div>
                <div className="eyebrow"><RoleIcon size={16} /> {portal.name} portal</div>
                <h1>{portal.title}</h1>
                <p>{portal.description}</p>
              </div>
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
        {role === "student" && portalSection === "overview" && (
          <section className="panel student-details-card" aria-labelledby="student-details-title">
            <div className="student-details-avatar">
              {profile?.photo_url ? <img src={profile.photo_url} alt="" /> : name.split(" ").map((part) => part[0]).slice(0, 2).join("")}
            </div>
            <div className="student-details-copy">
              <span className="portal-section-kicker">Student profile</span>
              <h2 id="student-details-title">{name}</h2>
              <div className="student-detail-facts">
                <span><small>Portal</small><strong>Student</strong></span>
                <span><small>Admission number</small><strong>{profile?.member_number || "Not added yet"}</strong></span>
                {profile?.username && <span><small>Username</small><strong>{profile.username}</strong></span>}
              </div>
            </div>
          </section>
        )}

        {role !== "admin" && portalSection === "overview" && (
          <PortalOverview
            role={role}
            records={recordsForRole}
            createItems={portal.create}
            onCreate={(kind) => open({ type: "create", kind })}
          />
        )}
        {role === "admin" ? (
          <div className="admin-body">
            {(adminSection === "fees" || adminSection === "submissions") && (
              <AdminTools
                key={adminSection}
                db={db}
                refresh={refresh}
                section={adminSection}
              />
            )}
            {(adminSection === "queue" || section.kinds) && (
              <AdminQueue
                key={adminSection + ":" + queueVersion}
                records={
                  section.kinds
                    ? recordsForRole.filter((item) =>
                        section.kinds.includes(item.kind),
                      )
                    : recordsForRole
                }
                initialFilter={adminSection === "queue" ? filter : "all"}
                availableKinds={section.kinds}
                overview={adminSection === "queue"}
                title={
                  adminSection === "queue"
                    ? "Pending and recent items"
                    : section.label + " records"
                }
                open={open}
                loading={recordsLoading}
              />
            )}
            {adminSection === "results" && (
              <ProgrammeStandings db={db} role="admin" />
            )}
            {adminSection === "accounts" &&
              (db ? (
                <Accounts db={db} activeId={userId} />
              ) : (
                <p className="panel">
                  Account creation uses a connected Supabase project.
                </p>
              ))}
            {(adminSection === "website" || adminSection === "outreach") &&
              (db ? (
                <SiteContent
                  key={adminSection}
                  db={db}
                  outreachOnly={adminSection === "outreach"}
                />
              ) : (
                <p className="panel">
                  Website editing uses a connected Supabase project.
                </p>
              ))}
            {adminSection === "cards" && <CardDesigner db={db} />}
          </div>
        ) : role === "student" && portalSection === "membership" ? (
          <section key="membership" className="panel portal-membership-view" id="student-membership">
            <div className="portal-card-heading"><div><h2>Digital membership</h2><p>Your student membership card.</p></div><span className="portal-action-icon"><IdCard size={18} /></span></div>
            <CardPreview
              profile={{
                display_name: name,
                member_number: profile?.member_number || (!db ? "NUS–0247" : "Student"),
                photo_url: profile?.photo_url,
              }}
              design={cardDesign}
            />
          </section>
        ) : (
          <section key={portalSection} className="work" id="portal-work" aria-labelledby="work-title">
            <div className="work-head">
              <div>
                <h2 id="work-title">{portalSection === "overview"
                  ? "Recent tasks"
                  : portalSection === "tasks"
                    ? role === "printer" ? "Print queue" : "All tasks"
                    : portalSectionTitle}</h2>
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
                {portalSection !== "overview" && portalSection !== "tasks" && portal.create.includes(portalSection) && (
                  <button className="primary portal-section-action" onClick={() => open({ type: "create", kind: portalSection })}>
                    <Plus size={16} /> {kinds[portalSection].action}
                  </button>
                )}
                {portalSection === "overview" && <div className="segmented" aria-label="View options">
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
                </div>}
              </div>
            </div>
            {portalSection === "overview" && (
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
            )}
            {portalSection === "overview" && view === "board" ? (
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
        {role === "wing" && (portalSection === "overview" || portalSection === "result") && <ProgrammeStandings db={db} role="wing" />}
        <footer>
          <a href="/">NUS’HA public website</a>
          <button onClick={() => open({ type: "help" })}>
            <CircleHelp size={15} /> Workspace help
          </button>
        </footer>
      </main>

      {role !== "admin" && (
        <nav
          className="portal-mobile-nav"
          aria-label="Mobile portal navigation"
          style={{ "--active-shift": `${(adminMenuOpen || !["overview", "tasks", "notice"].includes(portalSection) ? 4 : portalSection === "tasks" ? 1 : portalSection === "notice" ? 3 : 0) * 20}%` }}
        >
          <span className="portal-mobile-indicator" aria-hidden="true" />
          <button type="button" aria-current={!adminMenuOpen && portalSection === "overview" ? "page" : undefined} onClick={() => selectPortalSection("overview")}>
            <LayoutGrid size={20} /><span>Home</span>
          </button>
          <button type="button" aria-current={!adminMenuOpen && portalSection === "tasks" ? "page" : undefined} onClick={() => selectPortalSection("tasks")}>
            <List size={20} /><span>Tasks</span>
          </button>
          <button className="portal-mobile-add" type="button" aria-label={portal.create.length ? kinds[portal.create[0]].action : "No new action available"} disabled={!portal.create.length} onClick={() => portal.create[0] && open({ type: "create", kind: portal.create[0] })}>
            <span><Plus size={23} /></span><small>Add</small>
          </button>
          <button type="button" aria-current={!adminMenuOpen && portalSection === "notice" ? "page" : undefined} onClick={() => selectPortalSection("notice")}>
            <Bell size={20} /><span>Updates</span>
          </button>
          <button type="button" aria-current={adminMenuOpen || !["overview", "tasks", "notice"].includes(portalSection) ? "page" : undefined} aria-expanded={adminMenuOpen} onClick={() => setAdminMenuOpen(!adminMenuOpen)}>
            <RoleIcon size={20} /><span>Menu</span>
          </button>
        </nav>
      )}

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
                    <OutreachFields create db={db} />
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
                    <select
                      name="reportType"
                      defaultValue={modal.reportType || "results"}
                    >
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
                  <AdminEditFields item={modal.item} db={db} />
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
