import {
  authClient,
  portalResponse,
  portalRoles,
  usernameEmail,
} from "../../../../lib/portal-auth";

export async function POST(request) {
  const input = await request.json().catch(() => null);
  const email = usernameEmail(input?.username);
  const password = input?.password;
  const role = input?.role;
  const denied = () =>
    portalResponse(
      { error: "Check your username, password and portal type." },
      401,
    );
  if (
    !email ||
    typeof password !== "string" ||
    !password ||
    password.length > 128 ||
    (role && !portalRoles.includes(role))
  )
    return denied();

  const db = authClient(process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY);
  if (!db)
    return portalResponse({ error: "Portal sign-in is unavailable." }, 503);
  const identity =
    email === "admin@accounts.nusha.invalid"
      ? process.env.PORTAL_ADMIN_EMAIL || email
      : email;
  const { data, error } = await db.auth.signInWithPassword({
    email: identity,
    password,
  });
  if (error || !data.session) {
    if (error?.status === 429)
      return portalResponse(
        { error: "Too many attempts. Try again shortly." },
        429,
      );
    if (error?.status >= 500)
      return portalResponse(
        { error: "Portal sign-in is temporarily unavailable." },
        503,
      );
    return denied();
  }

  const { data: profile } = await db
    .from("profiles")
    .select("role")
    .eq("id", data.user.id)
    .maybeSingle();
  if (
    !profile ||
    !portalRoles.includes(profile.role) ||
    (role && role !== profile.role)
  ) {
    await db.auth.signOut({ scope: "local" });
    return denied();
  }
  return portalResponse({
    role: profile.role,
    session: {
      access_token: data.session.access_token,
      refresh_token: data.session.refresh_token,
    },
  });
}
