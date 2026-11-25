"use client";

import { useState } from "react";

export default function Accounts({ db }) {
  const [role, setRole] = useState("student");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);

  async function createAccount(event) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setNotice("");
    try {
      const { data } = await db.auth.getSession();
      if (!data.session) throw new Error("Sign in again to continue.");
      const response = await fetch("/api/portal/accounts", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: "Bearer " + data.session.access_token,
        },
        body: JSON.stringify(Object.fromEntries(new FormData(form))),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error);
      form.reset();
      setRole("student");
      setNotice(
        "Account created. " +
          result.account.username +
          " can sign in to the " +
          result.account.role +
          " portal now.",
      );
    } catch (error) {
      setNotice(error.message || "Account creation failed. Try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="panel site-content" aria-labelledby="manage-accounts">
      <h2 id="manage-accounts">Create portal account</h2>
      <form className="account-form" onSubmit={createAccount}>
        <label>
          Portal
          <select
            name="role"
            value={role}
            onChange={(event) => setRole(event.target.value)}
          >
            <option value="student">Student</option>
            <option value="class">Class</option>
            <option value="wing">Wing</option>
          </select>
        </label>
        <label>
          {role === "student"
            ? "Student name"
            : role === "class"
              ? "Class name"
              : "Wing name"}
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
            autoCapitalize="none"
          />
        </label>
        {role === "student" && (
          <label>
            Admission number
            <input name="memberNumber" maxLength={80} />
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
          {busy ? "Creating…" : "Create account"}
        </button>
      </form>
      <p role="status">{notice}</p>
    </section>
  );
}
