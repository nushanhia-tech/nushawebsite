"use client";

import { useEffect, useState } from "react";
import { adminAccountRequest, csvRows } from "./admin-client";
import PhotoUpload from "./photo-upload";

const labels = {
  student: "Student",
  class: "Class",
  wing: "Wing",
  admin: "Admin",
  printer: "Printer",
};

export default function Accounts({ db, activeId }) {
  const [role, setRole] = useState("student");
  const [notice, setNotice] = useState("");
  const [directoryError, setDirectoryError] = useState("");
  const [accounts, setAccounts] = useState([]);
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState(null);
  const [busy, setBusy] = useState(false);

  async function refresh() {
    try {
      const result = await adminAccountRequest(db, "GET");
      setAccounts(result.accounts);
      setDirectoryError("");
    } catch (error) {
      setDirectoryError(error.message);
    }
  }

  useEffect(() => {
    refresh();
  }, [db]);

  async function run(task) {
    setBusy(true);
    setNotice("");
    try {
      await task();
      await refresh();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  }

  function createAccount(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const input = Object.fromEntries(new FormData(form));
    run(async () => {
      const { account } = await adminAccountRequest(db, "POST", input);
      form.reset();
      setRole("student");
      setNotice(
        "Account created. " +
          account.username +
          " can sign in to the " +
          account.role +
          " portal.",
      );
    });
  }

  function importStudents(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const file = new FormData(form).get("csv");
    run(async () => {
      const rows = await csvRows(file, 3);
      const failures = [];
      let count = 0;
      for (const [
        index,
        [displayName, admission, password],
      ] of rows.entries()) {
        try {
          await adminAccountRequest(db, "POST", {
            role: "student",
            displayName,
            memberNumber: admission,
            username: admission,
            password,
          });
          count++;
        } catch (error) {
          failures.push("Row " + (index + 2) + ": " + error.message);
        }
        if ((index + 1) % 10 === 0)
          setNotice(
            "Processed " + (index + 1) + " of " + rows.length + " rows.",
          );
      }
      form.reset();
      setNotice(
        count +
          " student accounts created." +
          (failures.length
            ? " " +
              failures.length +
              " failed. " +
              failures.slice(0, 5).join(" ")
            : ""),
      );
    });
  }

  function change(account, operation, fields) {
    run(async () => {
      await adminAccountRequest(db, "PATCH", {
        id: account.id,
        operation,
        ...fields,
      });
      setSelected(null);
      setNotice(
        operation === "password" ? "Password updated." : "Account updated.",
      );
    });
  }

  const term = query.trim().toLowerCase();
  const matching = accounts.filter((account) =>
    [
      account.display_name,
      account.username,
      account.member_number,
      account.role,
    ].some((value) => value?.toLowerCase().includes(term)),
  );

  return (
    <section className="panel site-content" aria-labelledby="manage-accounts">
      <h2 id="manage-accounts">Accounts and access</h2>
      <p role="status">{notice}</p>
      <details className="admin-disclosure">
        <summary>Create portal account</summary>
        <form className="account-form" onSubmit={createAccount}>
          <label>
            Portal
            <select
              name="role"
              value={role}
              onChange={(event) => setRole(event.target.value)}
            >
              {["student", "class", "wing", "admin"].map((value) => (
                <option key={value} value={value}>
                  {labels[value]}
                </option>
              ))}
            </select>
          </label>
          <label>
            Name
            <input name="displayName" required maxLength={120} />
          </label>
          <label>
            Username
            <input
              name="username"
              required
              minLength={2}
              maxLength={40}
              autoComplete="off"
            />
          </label>
          {role === "student" && (
            <label>
              Admission number
              <input name="memberNumber" maxLength={80} />
            </label>
          )}
          {role !== "admin" && (
            <label>
              <PhotoUpload db={db} name="photoUrl" label={`${labels[role]} photo`} />
            </label>
          )}
          <label>
            Initial password
            <input
              name="password"
              type="password"
              required
              minLength={8}
              maxLength={72}
              autoComplete="new-password"
            />
          </label>
          <button className="primary" disabled={busy}>
            Create account
          </button>
        </form>
      </details>
      <details className="admin-disclosure">
        <summary>Bulk import student accounts</summary>
        <form onSubmit={importStudents}>
          <p>
            CSV columns in order: Name, Admission number, Password. Include a
            header row.
          </p>
          <label>
            Student CSV
            <input type="file" name="csv" accept=".csv,text/csv" required />
          </label>
          <button className="primary" disabled={busy}>
            Import students
          </button>
        </form>
      </details>
      <h3>Account directory</h3>
      {directoryError && <p role="alert">{directoryError}</p>}
      <label>
        Search accounts
        <input
          className="admin-input"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
      </label>
      {matching.slice(0, 40).map((account) => (
        <div className="content-row admin-account-row" key={account.id}>
          <div>
            <strong>{account.display_name}</strong>
            <p>
              {labels[account.role]}
              {account.username && " · " + account.username}
              {account.active === false && " · Access removed"}
            </p>
          </div>
          <button
            className="secondary"
            disabled={busy}
            onClick={() =>
              setSelected(account.id === selected ? null : account.id)
            }
          >
            {account.id === selected ? "Close" : "Manage"}
          </button>
          {account.id === selected && (
            <div className="admin-account-tools">
              <form
                className="account-form"
                onSubmit={(event) => {
                  event.preventDefault();
                  change(
                    account,
                    "edit",
                    Object.fromEntries(new FormData(event.currentTarget)),
                  );
                }}
              >
                <label>
                  Name
                  <input
                    name="displayName"
                    defaultValue={account.display_name}
                    required
                    maxLength={120}
                  />
                </label>
                {account.role === "student" && (
                  <label>
                    Admission number
                    <input
                      name="memberNumber"
                      defaultValue={account.member_number || ""}
                      maxLength={80}
                    />
                  </label>
                )}
                <label>
                  <PhotoUpload
                    db={db}
                    name="photoUrl"
                    label="Profile photo"
                    value={account.photo_url || ""}
                  />
                </label>
                <button className="primary" disabled={busy}>
                  Save details
                </button>
              </form>
              <form
                className="account-form"
                onSubmit={(event) => {
                  event.preventDefault();
                  change(account, "password", {
                    password: new FormData(event.currentTarget).get("password"),
                  });
                }}
              >
                <label>
                  New password
                  <input
                    name="password"
                    type="password"
                    required
                    minLength={8}
                    maxLength={72}
                    autoComplete="new-password"
                  />
                </label>
                <button className="secondary" disabled={busy}>
                  Reset password
                </button>
              </form>
              <div className="dialog-actions">
                {["class", "wing"].includes(account.role) && (
                  <button
                    className="secondary"
                    disabled={busy}
                    onClick={() =>
                      change(account, "upload", {
                        enabled: account.upload_access === false,
                      })
                    }
                  >
                    {account.upload_access === false
                      ? "Enable submissions"
                      : "Pause submissions"}
                  </button>
                )}
                {account.id !== activeId && (
                  <button
                    className="secondary"
                    disabled={busy}
                    onClick={() => {
                      const enabled = account.active === false;
                      if (
                        enabled ||
                        window.confirm(
                          "Remove portal access for " +
                            account.display_name +
                            "? Their records will remain in the system.",
                        )
                      )
                        change(account, "access", { enabled });
                    }}
                  >
                    {account.active === false
                      ? "Restore access"
                      : "Remove access"}
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      ))}
      {matching.length > 40 && (
        <p>
          Showing 40 accounts. Search by name, role, or admission number to
          narrow the list.
        </p>
      )}
      {!matching.length && !directoryError && <p>No matching accounts.</p>}
    </section>
  );
}
