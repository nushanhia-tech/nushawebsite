import { readFile } from "node:fs/promises";
import path from "node:path";
import { unstable_cache } from "next/cache";
import { supabaseProjectUrl } from "./supabase-url";
import { getPHPLoaderModule } from "@php-wasm/node-8-3";
import { PHP, loadPHPRuntime } from "@php-wasm/universal";
import { createClient } from "@supabase/supabase-js";

export const publicPages = [
  "index",
  "about",
  "events",
  "calendar",
  "leadership",
  "ranking",
  "outreach",
  "contact",
];

const url = supabaseProjectUrl(process.env.NEXT_PUBLIC_SUPABASE_URL);
const key = process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY;
export const publicDb =
  url && key
    ? createClient(url, key, {
        auth: {
          persistSession: false,
          autoRefreshToken: false,
          detectSessionInUrl: false,
        },
      })
    : null;

let runtime;

async function initialize() {
  const [loader, ...templates] = await Promise.all([
    getPHPLoaderModule(),
    ...[...publicPages, "footer", "db", "portal-login"].map(async (name) => {
      const file = name + ".php";
      return [file, await readFile(path.join(process.cwd(), "legacy", file))];
    }),
  ]);
  const php = new PHP(await loadPHPRuntime(loader));
  php.mkdir("/site");
  for (const [file, contents] of templates)
    php.writeFile("/site/" + file, contents);
  return php;
}

const readPublicData = unstable_cache(
  async (page) => {
    const { data, error } = await publicDb
      .rpc("public_site_data", { page_name: page })
      .abortSignal(AbortSignal.timeout(2000));
    if (error) throw new Error(error.code || "PUBLIC_DATA_UNAVAILABLE");
    return data;
  },
  ["nusha-public-data-v1", url || ""],
  { revalidate: 45 },
);

export const readPageVisibility = unstable_cache(async () => {
  if (!publicDb) return {};
  const { data, error } = await publicDb.rpc("visible_public_pages").abortSignal(AbortSignal.timeout(2000));
  if (error) throw new Error(error.code || "PAGE_VISIBILITY_UNAVAILABLE");
  return data || {};
}, ["nusha-page-visibility-v1", url || ""], { revalidate: 30 });

const readTextOverrides = unstable_cache(async (page) => {
  if (!publicDb) return [];
  const { data, error } = await publicDb.rpc("public_text_for_page", { page_name: page }).abortSignal(AbortSignal.timeout(2000));
  if (error) throw new Error(error.code || "PUBLIC_TEXT_UNAVAILABLE");
  return data || [];
}, ["nusha-public-text-v1", url || ""], { revalidate: 30 });

function escapeText(value) {
  return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function applyTextOverrides(html, overrides) {
  const mapping = new Map(overrides.map((entry) => [entry.original.trim(), escapeText(entry.replacement)]));
  if (!mapping.size) return html;
  // Skip scripts, styles, comments and markup: only replace complete text nodes.
  return html.split(/(<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<!--[\s\S]*?-->|<[^>]*>)/gi).map((part) => {
    if (!part || part[0] === "<") return part;
    const match = part.match(/^(\s*)([\s\S]*?)(\s*)$/);
    const decoded = match[2].replace(/&(?:amp|lt|gt|quot|apos|#0*39|#x0*27|nbsp);/gi, (entity) => ({ "&amp;": "&", "&lt;": "<", "&gt;": ">", "&quot;": '"', "&apos;": "'", "&nbsp;": " " }[entity.toLowerCase()] || "'"));
    const replacement = mapping.get(decoded);
    return replacement === undefined ? part : match[1] + replacement + match[3];
  }).join("");
}

export async function renderPublicPage(page, requestUrl) {
  const request = new URL(requestUrl);
  runtime ??= initialize().catch((error) => {
    runtime = undefined;
    throw error;
  });
  const [php, result, visibility, overrides] = await Promise.all([
    runtime,
    publicDb
      ? readPublicData(page)
          .then((data) => ({ data }))
          .catch((error) => {
            console.error("Public site data is unavailable:", error.message);
            return { data: {}, error: true };
          })
      : { data: {} },
    readPageVisibility().catch(() => ({})),
    readTextOverrides(page).catch(() => []),
  ]);
  if (visibility[page] === false) return new Response("Page not found", { status: 404, headers: { "Cache-Control": "no-store", "Content-Type": "text/plain; charset=utf-8" } });
  if (result.data?.website_visible === false) {
    return new Response(
      "<!doctype html><html lang=\"en\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Website unavailable · NUS’HA</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#19263b;font:16px system-ui,sans-serif}.card{max-width:480px;margin:24px;padding:36px;border:1px solid #dce4ef;border-radius:24px;background:#fff;text-align:center;box-shadow:0 18px 60px #19263b12}h1{font-size:28px;margin:0 0 12px}p{color:#53637a;line-height:1.6}</style><main class=\"card\"><h1>NUS’HA website is temporarily unavailable</h1><p>Please check back soon.</p></main></html>",
      { status: 200, headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" } },
    );
  }
  const rendered = await php.run({
    scriptPath: "/site/" + page + ".php",
    relativeUri: request.pathname + request.search,
    env: { NUSHA_PUBLIC_DATA: JSON.stringify(result.data ?? {}) },
    usePrimaryPhp: true,
  });
  const status = rendered.httpStatusCode || 200;
  const headers = {
    "Content-Type": "text/html; charset=utf-8",
    "Cache-Control": "no-store",
  };
  // Cache the anonymous page briefly even when Supabase is unavailable.
  // Submission feedback and non-200 responses stay out of the CDN.
  if (status === 200 && !(page === "outreach" && request.search)) {
    headers["Cache-Control"] = "public, max-age=0, must-revalidate";
    headers["Vercel-CDN-Cache-Control"] = result.error
      ? "public, s-maxage=30, stale-while-revalidate=60"
      : "public, s-maxage=60, stale-while-revalidate=120";
  }
  const hiddenPages = publicPages.filter((name) => visibility[name] === false);
  const hiddenSelectors = hiddenPages.flatMap((name) => name === "index" ? ['a[href="/"]','a[href="index.php"]','a[href="/index.php"]'] : [`a[href="${name}.php"]`,`a[href="/${name}.php"]`]);
  const edited = applyTextOverrides(rendered.text, overrides);
  const html = hiddenSelectors.length ? edited.replace('</head>', `<style>${hiddenSelectors.join(',')}{display:none!important}</style></head>`) : edited;
  return new Response(html, { status, headers });
}
