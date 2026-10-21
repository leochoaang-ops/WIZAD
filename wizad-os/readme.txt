=== WIZAD OS ===
Version: 0.1.1 (Sprint 1)

WIZAD's internal operating system for a private WordPress install (os.wizad.ca).

Sprint 1 includes
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
