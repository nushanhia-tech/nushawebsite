import {
  authClient,
  portalResponse,
  usernameEmail,
} from "../../../../lib/portal-auth";

export async function POST(request) {
  const token = request.headers
    .get("authorization")
    ?.match(/^Bearer (\S+)$/i)?.[1];
  if (!token)
    return portalResponse({ error: "Sign in as an administrator." }, 401);
  const db = authClient(process.env.SUPABASE_SERVICE_ROLE_KEY);
  if (!db)
    return portalResponse(
      { error: "Account creation needs the server-side Supabase service key." },
      503,
    );

  const { data: auth } = await db.auth.getUser(token);
  if (!auth.user)
    return portalResponse({ error: "Sign in again to continue." }, 401);
  const { data: actor } = await db
    .from("profiles")
    .select("role")
    .eq("id", auth.user.id)
    .maybeSingle();
  if (actor?.role !== "admin")
    return portalResponse({ error: "Administrator access is required." }, 403);

  const input = await request.json().catch(() => null);
  const email = usernameEmail(input?.username);
  const name =
    typeof input?.displayName === "string" ? input.displayName.trim() : "";
  const number =
    typeof input?.memberNumber === "string" ? input.memberNumber.trim() : "";
  const role = input?.role;
  const password = input?.password;
  if (
    !["student", "class", "wing"].includes(role) ||
    !email ||
    email.startsWith("admin@") ||
    !name ||
    name.length > 120 ||
    number.length > 80 ||
    typeof password !== "string" ||
    password.length < 8 ||
    password.length > 72
  ) {
    return portalResponse(
      { error: "Check the account details. Passwords need 8–72 characters." },
      400,
    );
  }

  const created = await db.auth.admin.createUser({
    email,
    password,
    email_confirm: true,
  });
  if (created.error || !created.data.user) {
    return portalResponse(
      {
        error:
          created.error?.status >= 500
            ? "Supabase could not create the account. Try again."
            : "This username is unavailable or the password was rejected.",
      },
      created.error?.status >= 500 ? 503 : 409,
    );
  }
  const id = created.data.user.id;
  const { error } = await db.from("profiles").insert({
    id,
    display_name: name,
    role,
    member_number: role === "student" ? number || null : null,
  });
  if (error) {
    const cleanup = await db.auth.admin.deleteUser(id);
    if (cleanup.error) console.error("Portal account rollback failed", id);
    return portalResponse(
      {
        error:
          error.code === "23505"
            ? "This admission number is already assigned."
            : "The portal profile could not be created.",
      },
      error.code === "23505" ? 409 : 503,
    );
  }
  return portalResponse(
    { account: { id, role, username: input.username.trim().toLowerCase() } },
    201,
  );
}
