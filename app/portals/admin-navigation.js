import {
  Bell,
  CalendarDays,
  ClipboardCheck,
  Globe,
  IdCard,
  Inbox,
  Layers3,
  LogOut,
  Menu,
  MessageSquare,
  Newspaper,
  Printer,
  Settings2,
  Trophy,
  Users,
  Wallet,
  X,
} from "lucide-react";

export const adminGroups = [
  {
    label: "Programme management",
    sections: [
      {
        id: "queue",
        label: "Work queue",
        description: "Review requests and recent updates.",
        icon: Inbox,
      },
      {
        id: "bookings",
        label: "Bookings",
        description: "Review venue bookings and open the official calendar.",
        icon: CalendarDays,
        kinds: ["booking"],
      },
      {
        id: "evaluations",
        label: "Assessment",
        description: "Edit programme submissions and award points.",
        icon: ClipboardCheck,
        kinds: ["activity"],
      },
      {
        id: "results",
        label: "Results",
        description: "Review results, placements, and grades.",
        icon: Trophy,
        kinds: ["result"],
      },
      {
        id: "printing",
        label: "Print jobs",
        description: "See print requests and their current status.",
        icon: Printer,
        kinds: ["print"],
      },
    ],
  },
  {
    label: "Union administration",
    sections: [
      {
        id: "fees",
        label: "Fees & payments",
        description:
          "Assign student fees, review payment references, and mark dues paid.",
        icon: Wallet,
        kinds: ["due", "payment"],
      },
      {
        id: "accounts",
        label: "Accounts",
        description: "Create accounts, update details, or manage access.",
        icon: Users,
      },
      {
        id: "cards",
        label: "ID cards",
        description: "Design membership cards and export them for printing.",
        icon: IdCard,
      },
      {
        id: "submissions",
        label: "Submission access",
        description:
          "Open or close programme submissions for Classes and Wings.",
        icon: Settings2,
      },
    ],
  },
  {
    label: "Communication",
    sections: [
      {
        id: "announcements",
        label: "Announcements",
        description: "Publish updates to everyone or a specific portal.",
        icon: Bell,
        kinds: ["notice"],
        action: "notice",
      },
      {
        id: "outreach",
        label: "Outreach",
        description: "Publish, edit, and review Arabic outreach posts.",
        icon: Newspaper,
        kinds: ["outreach"],
        action: "outreach",
      },
      {
        id: "feedback",
        label: "Feedback",
        description: "Review and resolve support messages.",
        icon: MessageSquare,
        kinds: ["support"],
      },
      {
        id: "website",
        label: "Website",
        description:
          "Update the programmes, highlights, notices, and leadership roster.",
        icon: Globe,
      },
    ],
  },
];

export const adminSections = Object.fromEntries(
  adminGroups.flatMap(({ sections }) =>
    sections.map((section) => [section.id, section]),
  ),
);

export function PortalNavigation({ className = "", role, kinds, section, onSelect, signedIn, signOut, menuOpen, setMenuOpen }) {
  const Icon = { student: Users, wing: Layers3, class: ClipboardCheck, printer: Printer }[role] || Users;
  return (
    <aside className={`admin-sidebar ${className}`} aria-label={`${role} portal navigation`}>
      <div className="admin-sidebar-brand">
        <a className="mark" href="/" aria-label="NUS’HA public website"><span className="nusha-mark" aria-hidden="true" /></a>
        <div><strong>NUS’HA</strong><small>{role[0].toUpperCase() + role.slice(1)} Portal</small></div>
        <button className="admin-menu-toggle" type="button" aria-expanded={menuOpen} aria-label={menuOpen ? "Close portal menu" : "Open portal menu"} onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X size={20} /> : <Menu size={20} />}</button>
      </div>
      <div className={"admin-sidebar-content" + (menuOpen ? " admin-sidebar-open" : "")}>
        <nav className="admin-sidebar-nav" aria-label="Portal sections">
          <div className="admin-nav-group"><p>Workspace</p>
            <button type="button" aria-current={section === "overview" ? "page" : undefined} onClick={() => onSelect("overview")}><Icon size={17} /><span>Overview</span></button>
            {role === "printer" && <button type="button" aria-current={section === "catalog" ? "page" : undefined} onClick={() => onSelect("catalog")}><Printer size={17} /><span>Print items</span></button>}
            {role === "printer" && <button type="button" aria-current={section === "billing" ? "page" : undefined} onClick={() => onSelect("billing")}><Wallet size={17} /><span>Student bills</span></button>}
            {(role === "class" || role === "wing") && <button type="button" aria-current={section === "performance" ? "page" : undefined} onClick={() => onSelect("performance")}><Trophy size={17} /><span>Performance</span></button>}
            {role === "student" && <button type="button" aria-current={section === "membership" ? "page" : undefined} onClick={() => onSelect("membership")}><IdCard size={17} /><span>Membership</span></button>}
            <button type="button" aria-current={section === "tasks" ? "page" : undefined} onClick={() => onSelect("tasks")}><Inbox size={17} /><span>All tasks</span></button>
            {kinds.filter((kind) => kind !== "notice").map((kind) => {
              const labels = { due: "Dues", payment: "Payments", result: "Results", activity: "Monthly assessment", booking: "Bookings", print: "Print jobs", support: "Support", outreach: "Outreach" };
              return <button type="button" key={kind} aria-current={section === kind ? "page" : undefined} onClick={() => onSelect(kind)}><Inbox size={17} /><span>{labels[kind] || kind}</span></button>;
            })}
            <button type="button" aria-current={section === "notice" ? "page" : undefined} onClick={() => onSelect("notice")}><Bell size={17} /><span>Announcements</span></button>
          </div>
        </nav>
        <div className="admin-sidebar-footer"><a href="/"><Globe size={17} /> Public website</a>{signedIn && <button type="button" onClick={signOut}><LogOut size={17} /> Sign out</button>}</div>
      </div>
    </aside>
  );
}

export default function AdminNavigation({
  section,
  navigate,
  signedIn,
  signOut,
  onRoleChange,
  menuOpen,
  setMenuOpen,
}) {
  return (
    <aside className="admin-sidebar" aria-label="Admin workspace">
      <div className="admin-sidebar-brand">
        <a className="mark" href="/" aria-label="NUS’HA public website">
          <span className="nusha-mark" aria-hidden="true" />
        </a>
        <div>
          <strong>NUS’HA</strong>
          <small>Admin Portal</small>
        </div>
        <button
          className="admin-menu-toggle"
          type="button"
          aria-controls="admin-section-menu"
          aria-expanded={menuOpen}
          aria-label={menuOpen ? "Close admin menu" : "Open admin menu"}
          onClick={() => setMenuOpen(!menuOpen)}
        >
          {menuOpen ? <X size={20} /> : <Menu size={20} />}
        </button>
      </div>
      <div
        id="admin-section-menu"
        className={
          "admin-sidebar-content" + (menuOpen ? " admin-sidebar-open" : "")
        }
      >
        <nav className="admin-sidebar-nav" aria-label="Admin sections">
          {adminGroups.map(({ label, sections }) => (
            <div className="admin-nav-group" key={label}>
              <p>{label}</p>
              {sections.map(({ id, label, icon: Icon }) => (
                <button
                  type="button"
                  key={id}
                  aria-current={id === section ? "page" : undefined}
                  onClick={() => navigate(id)}
                >
                  <Icon size={17} aria-hidden="true" />
                  <span>{label}</span>
                </button>
              ))}
            </div>
          ))}
        </nav>
        <div className="admin-sidebar-footer">
          <a href="/">
            <Globe size={17} aria-hidden="true" /> Public website
          </a>
          {signedIn ? (
            <button type="button" onClick={signOut}>
              <LogOut size={17} aria-hidden="true" /> Sign out
            </button>
          ) : (
            <label>
              Preview portal
              <select
                value="admin"
                onChange={(event) => onRoleChange(event.target.value)}
              >
                <option value="admin">Admin</option>
                <option value="student">Student</option>
                <option value="wing">Wing</option>
                <option value="class">Class</option>
                <option value="printer">Printer</option>
              </select>
            </label>
          )}
        </div>
      </div>
    </aside>
  );
}
