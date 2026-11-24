# NUS’HA Union Workspace

A Next.js redesign for the Student, Wing, Class, Printer, and Admin portals. Printer is implemented as a document request queue with a printable job sheet.

## Local preview

Run `npm ci` and `npm run dev`. Open http://localhost:3000. With no Supabase environment variables, the application runs as a sample workspace with five switchable roles. Sample changes revert on reload.

## Supabase and Vercel

1. Apply the SQL in `supabase/migrations` to the target Supabase project. Create accounts in Supabase Authentication, then assign each account a role in `public.profiles`. The profile ID is the Auth user UUID. Assign roles through the SQL Editor or trusted administrative tooling.
2. Copy `.env.example` to `.env.local` and enter the project URL and publishable key. Optionally set the union's verified UPI ID. Use the same values in the Vercel project's environment settings. Use only the publishable key here.
3. Push the committed repository to your Git provider and import it into Vercel as a Next.js project. Set the production branch to `main`. Alternatively deploy the project using an authenticated Vercel CLI with `vercel --prod`.
4. Sign in with an assigned user email and password. Exercise each role with a separate test account before importing records. Wing results accept a recipient account UUID. Admin dues assignment accepts a student account UUID. The Printer queue accepts document links from Class and Wing and supports printable job sheets.

## Legacy scope

This repository implements the five portal dashboards and a fresh Supabase schema. The uploaded PHP website did not include a MySQL data export. Historical records and legacy file uploads need to be imported separately. Class and wing rankings use evaluations recorded in the new workspace. To import existing records, first export the MySQL data, provision Supabase Auth accounts, and resolve IDs and roles. Invite existing users to set new passwords through Supabase.

## Public website

The original public page templates and styles are in `legacy/` and `public/`. Next.js serves them at the original PHP URLs using an embedded PHP runtime. The public homepage is at `/` and the redesigned workspace is at `/portals`. Apply both Supabase migrations in order. The second exposes the data for the public calendar, rankings, outreach and content pages. Use **Public website content** in the signed-in Admin portal to add and edit the homepage updates, programmes and leadership entries. Public outreach submissions are held in the Admin outreach queue until approved.

The shared logo is an SVG trace of the supplied NUS’HA logo image. It uses the surrounding text color for contrast. The homepage uses the supplied campus photo in `public/bg-image.jpg`. Historical MySQL data was absent from the supplied source. This implementation uses Node.js 24 on Vercel.
