=== WIZAD OS ===
Version: 0.5.2 (Survey invite codes and Google Sheets download)

WIZAD's internal operating system for a private WordPress install (os.wizad.ca).

0.5.2 (Oct 28, 2026)
- The survey opens only with an invite code (WZ-7K2P style, no lookalike letters). Find clients > Survey > Invite codes adds prospects and gives each a code.
- Download for Google Sheets: every survey answer in words, one row per person, as a CSV that opens in Google Sheets or Excel. Codes download too, for a mail merge.
- Survey answers stay in WOS only; the download is a copy.
- The survey takes all local business types (16 fields), the field in their own words when it is Other, and an "anything else" note.
- Form v6 (research design v3): area, challenges in the past 30 days (several), the one that cost most, tool fit, real use, what gets in the way, software spend. Invited codes can carry field and area, so the form skips those questions.
- The download is coded for analysis: answer codes, Not sure as an empty cell with a flag, one 0/1 column per option.
- Record the discovery call date on each finished survey (the real-demand measure).
- The button an owner presses at the end of the report is saved. Requests for a next step, a guide or a referral show under "Asked for in the report" until marked done.

0.5.1 (Oct 28, 2026)
- The Connected Tools Check on wizad.ca posts its answers to WOS: each step is saved, so Find clients > Survey shows where people stop.
- A finished survey becomes a lead (or updates the prospect with the same ref or email), with its scores, level, answers and log.
- No sign-in for the form: allowed sites only, a rate limit, a honeypot field and strict answer lists. Answers can be deleted for a privacy request.

0.5.0 (Oct 28, 2026)
- Update WOS from Settings with one button: no more zip files. WOS fetches the new version from GitHub and WordPress swaps the files with a backup.
- Staging gets test versions first; the live site only gets versions Leo approved (merged to main).
- Each update is recorded: from, to, who and when.
- Includes everything in 0.4.0.

0.4.0 (Oct 27, 2026)
- Lead file: a Path to Won header replaces the tabs. Steps grouped Discover / Agree / Close, each box reads "date · status", % to Won, WON in the logo magenta, a Next line.
- Details: grouped and read-only, Edit opens the form. Other contacts for B2B clients (assistant, technical, finance, co-founder).
- Email and phone are masked (acc***@g***.com). The eye fetches the real value, and the lead log records who looked.
- Log: a timeline, newest first, grouped by day, with one icon and colour per kind of work and filters.
- Intake: a read-only table with a source tag per row and the 30 / 60 / 90 day baseline; Edit opens one row per field.
- The client's form: one column, chips, radios, a 1 to 5 scale, numbers with units, a progress bar and a privacy line. The discovery summary uses the same style.
- Tables follow one standard: row lines only, small grey headers, numbers right-aligned, status pills, the row opens the record.
- Demo data: leads come with a full log.

0.3.0 (Oct 27, 2026)
- Find clients: pipeline in 5 stages, assessments, and a lead file per lead with the 7-step Sprint 0 checklist and the Sprint 0 hour cap (Settings).
- Sprint 0 flow: assessment and level, preliminary proposal (no price, optional first draft by Claude), intake by interview or online, discovery summary the client confirms on a private link, price decision for Leo, Won (creates the client record).
- Decision cards show Why now, The numbers, Read first, and gain, risk and margin per option.
- Ask Claude: chat on Today, on each decision and on each lead file. The API key is added in Settings and never shown again.
- Demo data (staging only): load and clear sample leads and decisions from Settings.

0.2.2 (Oct 27, 2026)
- Add a decision: fields sit close together again.

0.2.1 (Oct 27, 2026)
- The WIZAD OS menu in wp-admin opens the app. The old dashboard is under "Old dashboard".
- Sign-in: space between the two-step code box and Verify.

0.2.0 · WOS Sprint 0 (Oct 27, 2026)
- The app at /app, outside wp-admin. Signing in lands on /app; signed-out visitors are sent to sign in; users without WIZAD OS access get a 403.
- Today (first version): the one decision to make now, and the queue.
- Decisions: the queue with options and a recommendation, answer in one tap or in your own words, set aside up to 14 days (it comes back by itself), withdraw, and the decision log. Yellow after 3 days, red after 7 or past due.
- REST for team tools: GET and POST /wp-json/wos/v1/decisions (signed-in user with WIZAD OS access; application passwords need HTTPS).
- Settings in the app: every rate, tax and warning level is an input with an "effective from" date, so a change never alters the past. Includes the role inventory (Lead Consultant, Consultant and Associate active; six more ready).
- Rates, GST, FX and payment terms moved from the admin settings page to the app; values saved under 0.1.x are kept.
- Other screens show which sprint brings them. The 0.1.x admin screens still work.

0.1.1 · Sprint 1 includes
- Private site: sign-in required for every page, feed and REST route; XML-RPC off; no sign-ups; hidden from search engines.
- Sign-in protection: 5 failed attempts lock the IP for 15 minutes; 12-hour sessions; sign-in log; urgent email on an admin sign-in from a new IP.
- Two-step sign-in required for admins once the free "Two Factor" plugin is active.
- Home: what needs Leo (blocked cards, late deals, overdue invoices), money this year, pipeline, GST tracker, security status.
- Delivery board (Kanban): To do, Doing, Blocked (needs Leo), Done, drag and drop.
- Clients, Deals, Projects, Invoices (numbered WZ-YYYY-NNN, print or save as PDF), Ledger of income and expenses with T2125 categories and private receipts.
- CSV export on every screen; starter data from the financial model.

Install
1. In the os.wizad.ca WordPress: Plugins > Add New > Upload Plugin > choose wizad-os.zip > Install > Activate.
2. Plugins > Add New > search "Two Factor" > install and activate the plugin by the WordPress contributors.
3. Users > Profile > Two-Factor Options: turn on "Authenticator App", scan the QR code, save backup codes.
4. WIZAD OS > Settings: check the invoice details, then "Load starter data".
