"use client";

import { useEffect, useState } from "react";
import { csvRows } from "./admin-client";

export default function AdminTools({ db, refresh, section }) {
  const [uploads, setUploads] = useState(null);
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [assessmentMonth, setAssessmentMonth] = useState("");

  useEffect(() => {
    let live = true;
    if (db && (section === "submissions" || section === "evaluations"))
      db.rpc("assessment_status").then(({ data, error }) => {
        if (live && !error) { setUploads(data.enabled); setAssessmentMonth(data.month || ""); }
      });
    return () => {
      live = false;
    };
  }, [db, section]);

  async function run(action) {
    setBusy(true);
    setNotice("");
    try {
      await action();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  }

  function assign(event, bulk) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    run(async () => {
      const entries = bulk
        ? (await csvRows(values.get("csv"), 4)).map(
            ([admission, title, category, amount]) => ({
              admission,
              title,
              category,
              amount,
            }),
          )
        : [Object.fromEntries(values)];
      if (
        entries.some(
          ({ admission, title, amount }) =>
            !admission ||
            !title ||
            !/^\d+(\.\d{1,2})?$/.test(amount) ||
            Number(amount) <= 0,
        )
      ) {
        throw new Error(
          "Every row needs an admission number, fee description and positive amount.",
        );
      }
      const { data, error } = await db.rpc("admin_assign_dues", { entries });
      if (error) throw error;
      form.reset();
      setNotice(data + (data === 1 ? " fee assigned." : " fees assigned."));
      await refresh();
    });
  }

  return (
    <div className="panel site-content">
      <h2>
        {section === "fees"
          ? "Assign student fees"
          : "Monthly assessment access"}
      </h2>
      {!db ? (
        <p>Connect Supabase to manage this setting.</p>
      ) : (
        <>
          {section === "fees" && (
            <>
              <form
                className="account-form"
                onSubmit={(event) => assign(event, false)}
              >
                <label>
                  Admission number
                  <input name="admission" required />
                </label>
                <label>
                  Fee description
                  <input name="title" required maxLength={160} />
                </label>
                <label>
                  Category
                  <input name="category" maxLength={100} />
                </label>
                <label>
                  Amount (₹)
                  <input
                    name="amount"
                    type="number"
                    min=".01"
                    step=".01"
                    required
                  />
                </label>
                <button className="primary" disabled={busy}>
                  Assign fee
                </button>
              </form>
              <details className="admin-disclosure">
                <summary>Import fees from CSV</summary>
                <form onSubmit={(event) => assign(event, true)}>
                  <p>
                    CSV columns in order: Admission number, Description,
                    Category, Amount. Include a header row.
                  </p>
                  <label>
                    Fees CSV
                    <input
                      name="csv"
                      type="file"
                      accept=".csv,text/csv"
                      required
                    />
                  </label>
                  <button className="secondary" disabled={busy}>
                    Import fees
                  </button>
                </form>
              </details>
            </>
          )}
          {(section === "submissions" || section === "evaluations") && (
            <>
              <p>
                {uploads === null
                  ? "Submission setting unavailable. Apply the Admin Tools migration."
                  : uploads
                    ? "Submissions are open."
                    : "Submissions are closed."}
              </p>
              <label>Assessment month <input type="month" value={assessmentMonth} onChange={(event) => setAssessmentMonth(event.target.value)} /></label>
              {uploads !== null && (
                <button
                  className="secondary"
                  disabled={busy}
                  onClick={() =>
                    run(async () => {
                      const { error } = await db.rpc("set_assessment_window", {
                        month_value: assessmentMonth,
                        is_open: !uploads,
                      });
                      if (error) throw error;
                      setUploads(!uploads);
                      setNotice("Submission access updated.");
                    })
                  }
                >
                  {uploads ? "Close submissions" : "Open submissions"}
                </button>
              )}
              {uploads && <button className="secondary" disabled={busy || !assessmentMonth} onClick={() => run(async () => { const { error } = await db.rpc("set_assessment_window", { month_value: assessmentMonth, is_open: true }); if (error) throw error; setNotice("Assessment month updated."); })}>Update open month</button>}
            </>
          )}
        </>
      )}
      <p role="status">{notice}</p>
    </div>
  );
}
