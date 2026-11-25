import { createClient } from "@supabase/supabase-js";
import { supabaseProjectUrl } from "./supabase-url";

export const portalRoles = ["student", "class", "wing", "printer", "admin"];

export function usernameEmail(value) {
  const username = typeof value === "string" ? value.trim().toLowerCase() : "";
  return /^[a-z0-9][a-z0-9._-]{1,39}$/.test(username)
    ? username + "@accounts.nusha.invalid"
    : null;
}

export function authClient(key) {
  const url = supabaseProjectUrl(process.env.NEXT_PUBLIC_SUPABASE_URL);
  return url && key
    ? createClient(url, key, {
        auth: { persistSession: false, autoRefreshToken: false },
      })
    : null;
}

export function portalResponse(body, status = 200) {
  return Response.json(body, {
    status,
    headers: { "Cache-Control": "no-store" },
  });
}
