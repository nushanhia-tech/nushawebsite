export const portals = {
  student: {
    name: "Student",
    identity: "Afsal Rahman",
    group: "Semester 4",
    title: "Your student workspace",
    description: "Keep track of your achievements, membership and union dues.",
    kinds: ["result", "due", "payment", "support", "notice"],
    create: ["payment", "support"],
  },
  wing: {
    name: "Wing",
    identity: "Literary Wing",
    group: "Programme team",
    title: "Programme workspace",
    description: "Organize programmes, book venues and share results.",
    kinds: ["booking", "activity", "result", "print", "notice"],
    create: ["booking", "activity", "result", "print"],
  },
  class: {
    name: "Class",
    identity: "Class of Semester 4",
    group: "Class representative",
    title: "Class workspace",
    description: "Submit activities and follow evaluations for your class.",
    kinds: ["activity", "result", "print", "notice", "support"],
    create: ["activity", "print", "support"],
  },
  printer: {
    name: "Printer",
    identity: "Print Desk",
    group: "Production team",
    title: "Print workspace",
    description:
      "See incoming requests and move each job through to collection.",
    kinds: ["print", "notice"],
    create: [],
  },
  admin: {
    name: "Admin",
    identity: "Union Admin",
    group: "Administration",
    title: "Union operations",
    description:
      "Review requests, evaluate activities and keep the union informed.",
    kinds: [
      "booking",
      "activity",
      "payment",
      "outreach",
      "print",
      "support",
      "due",
      "result",
      "notice",
    ],
    create: ["notice", "due", "outreach"],
  },
};

export const kinds = {
  result: {
    label: "Results",
    action: "Publish result",
    fields: [
      ["Audience account ID", "recipient_id"],
      ["Position or result", "detail"],
    ],
  },
  due: {
    label: "Dues",
    action: "Assign dues",
    fields: [
      ["Student account ID", "recipient_id"],
      ["Amount in INR", "amount", "number"],
    ],
  },
  payment: {
    label: "Payment references",
    action: "Submit UPI reference",
    fields: [
      ["Related dues record ID", "related_id"],
      ["UPI transaction reference", "detail"],
    ],
  },
  support: {
    label: "Support",
    action: "Contact support",
    fields: [["Message", "detail"]],
  },
  booking: {
    label: "Bookings",
    action: "Request booking",
    fields: [
      ["Programme date", "scheduled_on", "date"],
      ["Venue and time", "detail"],
    ],
  },
  activity: {
    label: "Activities",
    action: "Submit activity",
    fields: [["Activity description", "detail"]],
  },
  print: {
    label: "Print jobs",
    action: "Request printing",
    fields: [
      ["Pickup date", "scheduled_on", "date"],
      ["Document link and print specifications", "detail"],
    ],
  },
  notice: {
    label: "Announcements",
    action: "Post announcement",
    fields: [["Announcement", "detail"]],
  },
  outreach: {
    label: "Outreach",
    action: "Add outreach post",
    fields: [["Post content", "detail"]],
  },
};

export const stages = [
  { title: "To review", statuses: ["Pending"], icon: "review" },
  {
    title: "In progress",
    statuses: ["Approved", "In progress", "Ready"],
    icon: "progress",
  },
  {
    title: "Completed",
    statuses: [
      "Published",
      "Evaluated",
      "Collected",
      "Verified",
      "Paid",
      "Resolved",
      "Rejected",
    ],
    icon: "done",
  },
];

export function nextStatuses(role, item) {
  if (role === "printer" && item.kind === "print") {
    return (
      {
        Pending: ["In progress"],
        "In progress": ["Ready"],
        Ready: ["Collected"],
      }[item.status] || []
    );
  }
  if (role !== "admin" || item.status !== "Pending") return [];
  return (
    {
      booking: ["Approved", "Rejected"],
      activity: ["Evaluated"],
      payment: ["Verified", "Rejected"],
      outreach: ["Published", "Rejected"],
      support: ["Resolved"],
    }[item.kind] || []
  );
}

const demoRows = {
  admin: [
    ["booking", "Literary Week opening", "Literary Wing", "Pending"],
    ["activity", "Arabic debate circle", "Semester 4", "Pending"],
    ["payment", "Annual union dues", "Student payment reference", "Pending"],
    ["outreach", "Reflections on reading", "Arabic editorial desk", "Pending"],
    ["booking", "Inter-class quiz", "Academic Wing", "Approved"],
    ["print", "Programme certificates", "Print Desk", "In progress"],
    ["activity", "Campus reading circle", "Semester 2", "Evaluated"],
    [
      "notice",
      "November programme schedule",
      "Published to all portals",
      "Published",
    ],
  ],
  wing: [
    ["booking", "Literary Week opening", "Main hall · 10:30 AM", "Pending"],
    ["activity", "Arabic debate circle", "Activity report", "Pending"],
    ["booking", "Inter-class quiz", "Seminar hall · 2:00 PM", "Approved"],
    [
      "result",
      "Campus poetry competition",
      "Individual placements",
      "Published",
    ],
    ["print", "Participant certificates", "30 copies · A4", "In progress"],
  ],
  class: [
    ["activity", "Arabic debate circle", "Group activity", "Pending"],
    ["activity", "Campus reading circle", "18 points awarded", "Evaluated"],
    ["result", "Inter-class quiz", "Second place", "Published"],
    [
      "notice",
      "Activity submission reminder",
      "Submit your report before the monthly cutoff.",
      "Published",
    ],
  ],
  printer: [
    ["print", "Literary Week posters", "12 copies · A3 color", "Pending"],
    ["print", "Programme certificates", "35 copies · A4 color", "In progress"],
    ["print", "Debate score sheets", "20 copies · A4 grayscale", "Ready"],
    ["print", "Campus reading flyers", "50 copies · A5", "Collected"],
  ],
  student: [
    ["due", "Annual union dues", "Outstanding balance", "Pending"],
    ["result", "Qur’an recitation", "First place", "Published"],
    ["result", "Inter-class quiz", "Second place · Team event", "Published"],
    [
      "notice",
      "Literary Week registration",
      "Registration is open through the union office.",
      "Published",
    ],
  ],
};

export function demoItems(role) {
  return demoRows[role].map(([kind, title, detail, status], index) => ({
    id: role + "-" + index,
    kind,
    title,
    detail,
    status,
    amount: kind === "due" ? 300 : null,
    created_at: new Date(Date.UTC(2026, 10, 23 - index)).toISOString(),
  }));
}
