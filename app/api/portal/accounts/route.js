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
  const viewer = authClient(
    process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY,
    token,
  );
  if (!viewer)
    return portalResponse(
      { error: "Portal authentication is not configured." },
      503,
    );

  const { data: auth, error: authError } = await viewer.auth.getUser(token);
  if (authError?.status >= 500)
    return portalResponse(
      { error: "Supabase sign-in verification is temporarily unavailable." },
      503,
    );
  if (!auth?.user)
    return portalResponse({ error: "Sign in again to continue." }, 401);

  const { data: actor, error: profileError } = await viewer
    .from("profiles")
    .select("*")
    .eq("id", auth.user.id)
    .maybeSingle();
  if (profileError) {
    console.error(
      "Account manager could not verify the Admin profile",
      profileError.code,
    );
    return portalResponse(
      {
        error:
          "Could not verify your portal profile. Check that the Supabase portal migrations are applied.",
      },
      503,
    );
  }
  if (actor?.role !== "admin" || actor.active === false) {
    return portalResponse(
      {
        error:
          "Administrator access is required. Check that your Supabase profile is an active Admin.",
      },
      403,
    );
  }

  const serviceKey = process.env.SUPABASE_SERVICE_ROLE_KEY?.trim();
  const db = authClient(serviceKey);
  if (!db)
    return portalResponse(
      {
        error: "Account management needs the server-side Supabase service key.",
      },
      503,
    );

  const { data: serviceUser, error: serviceError } =
    await db.auth.admin.getUserById(actor.id);
  if (serviceError || serviceUser?.user?.id !== actor.id) {
    const rejected = [400, 401, 403, 404].includes(serviceError?.status);
    console.error(
      "Supabase account management credentials could not be verified",
      serviceError?.status,
    );
    return portalResponse(
      {
        error:
          rejected || !serviceError
            ? "Vercel’s SUPABASE_SERVICE_ROLE_KEY could not authorize account management. Use the service_role or Secret key from the linked Supabase project, then redeploy."
            : "Supabase account management is temporarily unavailable. Please try again.",
      },
      503,
    );
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
    // Some Supabase projects create the profile in an Auth trigger.
    // Complete the profile for this newly created Auth ID either way.
    let { error } = await db
      .from("profiles")
      .upsert(
        { ...basic, username, photo_url: profile.photo_url },
        { onConflict: "id" },
      );
    if (error?.code === "PGRST204" && role !== "admin" && !profile.photo_url) {
      ({ error } = await db
        .from("profiles")
        .upsert(basic, { onConflict: "id" }));
    }
    if (error) {
      const cleanup = await db.auth.admin.deleteUser(id);
      if (cleanup.error) console.error("Portal account rollback failed", id);
      console.error("Portal profile save failed", error.code);
      const explanation = {
        23505: "That username or admission number is already assigned.",
        42501:
          "Supabase denied profile writes. Run the Profile Service Access migration in SQL Editor.",
        42703:
          "The portal profile columns are missing. Run the Admin Tools migration in SQL Editor.",
        PGRST204:
          "The portal profile columns are missing. Run the Admin Tools migration and reload the Supabase schema cache.",
        PGRST205:
          "The profiles table is missing from the Supabase API. Run the Portal migration and reload the schema cache.",
      }[error.code];
      return portalResponse(
        {
          error:
            explanation ||
            "The portal profile could not be saved (" +
              (error.code || "unknown error") +
              ").",
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
