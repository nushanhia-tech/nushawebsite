import {
  Bell,
  CalendarDays,
  ClipboardCheck,
  Globe,
  IdCard,
  Inbox,
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
        label: "Evaluations",
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
