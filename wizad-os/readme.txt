=== WIZAD OS ===
Version: 0.2.2 (WOS Sprint 0)

WIZAD's internal operating system for a private WordPress install (os.wizad.ca).

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
