export function supabaseProjectUrl(value) {
  if (!value) return undefined;
  return value
    .trim()
    .replace(/\/rest\/v1\/?$/i, "")
    .replace(/\/$/, "");
}
