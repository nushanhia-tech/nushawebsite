import {
  authClient,
  portalResponse,
  usernameEmail,
} from "../../../../lib/portal-auth";

async function admin(request, operation) {
  const token = request.headers
    .get("authorization")
    ?.match(/^Bearer (\S+)$/i)?.[1];
  if (!token)
    return portalResponse({ error: "Sign in as an administrator." }, 401);
  const db = authClient(process.env.SUPABASE_SERVICE_ROLE_KEY);
  if (!db)
    return portalResponse(
      {
        error: "Account management needs the server-side Supabase service key.",
      },
      503,
    );

  const { data: auth } = await db.auth.getUser(token);
  if (!auth.user)
    return portalResponse({ error: "Sign in again to continue." }, 401);
  const { data: actor } = await db
    .from("profiles")
    .select("*")
    .eq("id", auth.user.id)
    .maybeSingle();
  if (actor?.role !== "admin" || actor.active === false) {
    return portalResponse({ error: "Administrator access is required." }, 403);
  }
  return operation(db, actor);
}

export async function GET(request) {
  return admin(request, async (db) => {
    const { data, error } = await db
      .from("profiles")
      .select("*")
      .order("display_name");
    return error
      ? portalResponse({ error: "The account directory could not load." }, 503)
      : portalResponse({ accounts: data });
  });
}

function details(input) {
  const display_name =
    typeof input?.displayName === "string" ? input.displayName.trim() : "";
  const member_number =
    typeof input?.memberNumber === "string" ? input.memberNumber.trim() : "";
  const photo_url =
    typeof input?.photoUrl === "string" ? input.photoUrl.trim() : "";
  if (
    !display_name ||
    display_name.length > 120 ||
    member_number.length > 80 ||
    (photo_url && (!/^https:\/\//i.test(photo_url) || photo_url.length > 500))
  )
    return null;
  return {
    display_name,
    member_number: member_number || null,
    photo_url: photo_url || null,
  };
}

function validPassword(value) {
  return (
    typeof value === "string" &&
    value.length >= 8 &&
    new TextEncoder().encode(value).length <= 72
  );
}

export async function POST(request) {
  return admin(request, async (db) => {
    const input = await request.json().catch(() => null);
    const email = usernameEmail(input?.username);
    const profile = details(input);
    const role = input?.role;
    if (
      !["student", "class", "wing", "admin"].includes(role) ||
      !email ||
      email.startsWith("admin@") ||
      !profile ||
      !validPassword(input?.password)
    ) {
      return portalResponse(
        {
          error:
            "Check the account details and use an initial password of at least 8 characters.",
        },
        400,
      );
    }
    const username = email.split("@")[0];
    const created = await db.auth.admin.createUser({
      email,
      password: input.password,
      email_confirm: true,
    });
    if (created.error || !created.data.user) {
      return portalResponse(
        {
          error:
            "Supabase could not create the account. Check for a duplicate username or invalid password.",
        },
        409,
      );
    }
    const id = created.data.user.id;
    const basic = {
      id,
      display_name: profile.display_name,
      role,
      member_number: role === "student" ? profile.member_number : null,
    };
    let { error } = await db
      .from("profiles")
      .insert({ ...basic, username, photo_url: profile.photo_url });
    if (error?.code === "PGRST204" && role !== "admin" && !profile.photo_url) {
      ({ error } = await db.from("profiles").insert(basic));
    }
    if (error) {
      const cleanup = await db.auth.admin.deleteUser(id);
      if (cleanup.error) console.error("Portal account rollback failed", id);
      return portalResponse(
        {
          error:
            error.code === "23505"
              ? "This admission number is already assigned."
              : "The portal profile could not be created. Check the Admin Tools migration.",
        },
        error.code === "23505" ? 409 : 503,
      );
    }
    return portalResponse({ account: { id, role, username } }, 201);
  });
}

export async function PATCH(request) {
  return admin(request, async (db, actor) => {
    const input = await request.json().catch(() => null);
    if (!input?.id || typeof input.id !== "string")
      return portalResponse({ error: "Choose an account." }, 400);
    const { data: target } = await db
      .from("profiles")
      .select("*")
      .eq("id", input.id)
      .maybeSingle();
    if (!target) return portalResponse({ error: "Account not found." }, 404);
    let patch;
    switch (input.operation) {
      case "edit": {
        const profile = details(input);
        if (!profile)
          return portalResponse(
            { error: "Check the name and photo URL." },
            400,
          );
        patch = {
          ...profile,
          member_number:
            target.role === "student" ? profile.member_number : null,
        };
        break;
      }
      case "upload":
        if (
          typeof input.enabled !== "boolean" ||
          !["class", "wing"].includes(target.role)
        )
          return portalResponse(
            { error: "Choose a Class or Wing account." },
            400,
          );
        patch = { upload_access: input.enabled };
        break;
      case "access":
        if (typeof input.enabled !== "boolean" || actor.id === target.id)
          return portalResponse(
            { error: "You cannot remove your own access." },
            400,
          );
        patch = { active: input.enabled };
        break;
      case "password": {
        if (!validPassword(input.password))
          return portalResponse(
            { error: "Choose a password of at least 8 characters." },
            400,
          );
        const { error } = await db.auth.admin.updateUserById(target.id, {
          password: input.password,
        });
        return error
          ? portalResponse(
              { error: "Supabase rejected the password reset." },
              409,
            )
          : portalResponse({ ok: true });
      }
      default:
        return portalResponse({ error: "Choose an account action." }, 400);
    }
    const { error } = await db
      .from("profiles")
      .update(patch)
      .eq("id", target.id);
    return error
      ? portalResponse(
          {
            error:
              "The account could not be updated. Check the details and migration.",
          },
          409,
        )
      : portalResponse({ ok: true });
  });
}
