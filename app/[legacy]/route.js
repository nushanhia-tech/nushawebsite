import { publicDb, publicPages, renderPublicPage, readPageVisibility } from "../../lib/public-site";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

export async function GET(request, { params }) {
  const { legacy } = await params;
  if (!legacy.endsWith(".php") || !publicPages.includes(legacy.slice(0, -4))) {
    return new Response("Page not found", { status: 404 });
  }
  return renderPublicPage(legacy.slice(0, -4), request.url);
}

export async function POST(request, { params }) {
  const { legacy } = await params;
  if (legacy !== "outreach.php")
    return new Response("Method not allowed", { status: 405 });
  const visibility = await readPageVisibility().catch(() => ({}));
  if (visibility.outreach === false) return new Response("Page not found", { status: 404 });
  if (!publicDb)
    return Response.redirect(
      new URL("/outreach.php?submission=unavailable", request.url),
      303,
    );
  const form = await request.formData();
  const fields = [
    "title",
    "category",
    "content",
    "thumbnail",
    "author_name",
    "author_role",
    "author_photo",
  ];
  const values = Object.fromEntries(
    fields.map((field) => [field, String(form.get(field) ?? "").trim()]),
  );
  const { error } = await publicDb.rpc("submit_public_outreach", values);
  const status = error ? "error" : "success";
  return Response.redirect(
    new URL("/outreach.php?submission=" + status, request.url),
    303,
  );
}
