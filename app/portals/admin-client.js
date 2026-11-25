export async function adminAccountRequest(db, method, body) {
  const { data } = await db.auth.getSession();
  if (!data.session) throw new Error("Sign in again to continue.");
  const response = await fetch("/api/portal/accounts", {
    method,
    headers: {
      Authorization: "Bearer " + data.session.access_token,
      "Content-Type": "application/json",
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const result = await response.json();
  if (!response.ok)
    throw new Error(result.error || "The account request failed.");
  return result;
}

export async function csvRows(file, columns, maxRows = 250) {
  if (!file || file.size > 500_000)
    throw new Error("Choose a CSV file under 500 KB.");
  const { default: Papa } = await import("papaparse");
  const result = Papa.parse((await file.text()).replace(/^\ufeff/, ""), {
    skipEmptyLines: "greedy",
  });
  const rows = result.data.slice(1);
  if (
    result.errors.length ||
    rows.length < 1 ||
    rows.length > maxRows ||
    result.data[0]?.length !== columns ||
    rows.some((row) => row.length !== columns)
  ) {
    throw new Error(
      "Check the CSV header, column count, and maximum of " +
        maxRows +
        " data rows.",
    );
  }
  return rows.map((row) => row.map((text) => text.trim()));
}
