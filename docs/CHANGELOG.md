# Progress Log / Changelog

What changed in the system, when, and why. Newest first. Each entry lists what was done, where it lives, and how it was checked, so the team can review it and move Kanban cards.

---

## v1.1 — Optimization, reports & hardening (Nov 24, 2026)

Branch: `romerojohndavidbranch` · Tests: **126 of 126 passing** (128 of 128 with PHP's GD extension on) · No new PHP warnings in the Apache log.

### At a glance

| Area | What changed | Result |
|---|---|---|
| ⚡ Speed | Availability queries rewritten; photos resized; browser caching + compression; lazy-loaded images; parallel AJAX | Site photos **2.75 MB → 1.6 MB (−42%)**; availability does one pass over the date's bookings instead of one subquery per item |
| 📊 New: Reports | **Admin → Reports**: monthly booked value vs. collected, top rental items, services, event types, CSV downloads | Answers "how did we do this year?" without a spreadsheet |
| 📄 New: Export | **Admin → Bookings → Export CSV** (respects filters) + 50-per-page pagination | No more silent 300-row cap; data opens straight in Excel / Google Sheets |
| 🛡️ Hardening | Security headers, friendly 400/500 pages, double-submit guard, safer URL handling, CSV formula protection | Fewer ways to break or misuse the site; errors look like part of the site |
| 🗄️ Database | Two new indexes (migration `003`), System check now checks indexes and GD | Dashboard + Reports stay fast as bookings pile up |
| ✅ Tests | 25 new automated checks (+2 when GD is on) | 101 → 126 |

### 1. Performance optimizations

**Availability engine** (`includes/booking-functions.php`)
* `get_item_availability()` and `get_service_availability()` used to run a separate sub-query **for every item / service**. Now the date's active bookings are found once (via the `event_date` index), summed per item, and joined to the list.
* These two functions power the booking form, the availability API, the Services page, inventory, reschedule/restore checks, so every one of those got faster.
* **Proof it's still correct:** a new test compares the optimized numbers with a plain "add up every booking" count for every item and service, plus the "exclude this booking" case used when editing.

**Images**
* The 10 photos in `assets/img/` were up to 2048 px and 580 KB each but never shown wider than ~600 px. They were resized to max 1200 px (still sharp on high-DPI phones), saved as progressive JPEG: **2,750 KB → 1,607 KB**.
* Below-the-fold images on Home, About and Services now use `loading="lazy"` + `decoding="async"`, so the first screen appears sooner.
* **Uploaded photos** are automatically scaled down to 1600 px (`UPLOAD_MAX_SIDE` in `config.php`) by the new `shrink_image()` in `includes/functions.php`, keeping PNG/WebP transparency. A 4000 px phone photo becomes a few hundred KB. This needs PHP's GD extension (on at most hosts; off by default in XAMPP, where photos are simply kept as-is). Admin → System shows whether it's on.

**Browser caching & compression** (root `.htaccess`)
* `mod_deflate` compresses HTML/CSS/JS/JSON; `mod_expires` + `Cache-Control` let browsers keep CSS/JS/images for 30 days.
* New `asset()` helper adds `?v=<last-modified time>` to `style.css` and the scripts, so a changed file is picked up immediately even with long caching.
* All rules sit inside `<IfModule>`, so they switch on where the host has the modules and are harmless where it doesn't (XAMPP ships with them off).
* `preconnect` hints to the Bootstrap / Google Fonts CDNs so CSS and fonts start downloading earlier.

**AJAX endpoints** (`api/availability.php`, `api/calendar.php`, `api/slots.php`)
* Release the PHP session lock right after reading the login, so the booking page's calendar + availability requests run **in parallel** instead of one after the other.
* `Cache-Control: no-store` so a browser or proxy never shows yesterday's stock.

**Queries**
* Dashboard "Collected this month" now filters `paid_at` by a date range (can use an index) instead of `DATE_FORMAT(paid_at)` (can't).
* New indexes: `payments(paid_at)` and `inquiries(status, created_at)`. Already in `db.sql`; existing databases get them from `db_setup/migrations/003_report_indexes.sql`.

### 2. New: Admin → Reports (`admin/reports.php`)

* **Year picker** (any year that has bookings or payments).
* **Headline numbers:** events booked, booked value, collected, average per event, cancellation rate.
* **Booked value by month** column chart: hover or tab onto a month for the exact amount, events and money collected. The busiest month is labelled, and a **"Show as a table"** view has every number (for printing and screen readers).
  * *Booked value* counts bookings in the month of the **event** (cancelled ones left out).
  * *Collected* counts payments in the month they were **received**.
* **Top rental items:** units, events and rental income with inline bars. **Hosts & organizers** and **Events by type** tables.
* **Downloads:** *Monthly summary CSV* and *Payments CSV* (every payment with booking, customer, method, reference and who recorded it), ready for the family's books.
* Works on phones (bars give way to figures on small screens).

### 3. New: bookings export & pagination (`admin/bookings.php`)

* **Export CSV** downloads every booking matching the current filters (status, search, dates, customer) with schedule, customer contact, venue, status, totals, paid, balance and payment status.
* The list now shows **50 per page** with *Showing 1–50 of N* and Previous/Next (the old page silently stopped at 300 bookings). The total count is shown next to the title.
* Shared helpers in `includes/functions.php`: `csv_download()` (UTF-8 with BOM so names like *Peñaflor* open correctly in Excel) and `pagination_links()`.

### 4. Hardening & reliability

| Change | Where | Why |
|---|---|---|
| Security headers on every page: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` | `includes/functions.php` | Stops content-type sniffing and click-jacking in frames, and keeps booking references out of other sites' logs |
| **Friendly 500 page** for unexpected errors (logged to the server log; technical detail shown only in development) | `includes/functions.php` | A database hiccup no longer shows a raw PHP stack trace; any half-drawn page is discarded first |
| **Friendly "form expired" page** (400) instead of a bare text message on CSRF failures | `require_csrf()` | Looks like the site and offers a way back |
| **Double-submit guard**: once a form is really submitted, further clicks are ignored and buttons show as busy | `assets/js/script.js` | A double-click on a slow connection can't create two bookings or record a payment twice |
| URL parameters that are arrays (`?q[]=x`) are dropped | `includes/functions.php` | A hand-edited link can't crash `trim()` and friends |
| Admin calendar validates `?month=` (e.g. `2026-13` no longer shows January 1970) | `admin/calendar.php` | Correctness |
| CSV cells starting with `= + - @` are prefixed with `'` | `csv_safe()` | A customer typing `=HYPERLINK(...)` in a venue can't run a formula in Excel (CSV injection) |
| System check: new rows for **performance indexes** and **GD (optional)** | `admin/system.php` | Tells you after deploying whether migration 003 / GD are needed |

### 5. Small UI polish
* Site **favicon** (`assets/img/favicon.svg`: a party tent in the brand colours), browser `theme-color`, and a page `meta description` for search results.
* Admin tab bar: tighter spacing so all 11 tabs (incl. the new **Reports**) fit on a laptop; on narrower desktops they wrap to a second row instead of hiding off the edge. Phones keep the swipeable row.

### 6. Tests (`tests/run-tests.php`)
New section **"Reports, exports, speed & hardening (v1.1)"** with 24 checks (+2 with GD), plus `admin/reports.php` added to the admin "page opens without errors" list (25 new in total):
* optimized availability = plain count (items, services, excluded booking)
* security headers, versioned CSS/JS, favicon, API `no-store`
* friendly expired-form page, array-in-URL and bad calendar month don't crash
* bookings CSV: download headers, one row per booking, status filter respected, formula neutralising
* pagination: 50 per page, page 2, page number past the end
* reports: booked value and collected match the database, top items, tab in the menu, both CSVs, unknown year
* photo resizing: big photo → 1600 px keeping its shape, small photo untouched (needs GD: `php -d extension=gd tests\run-tests.php --reset`)

The test `Browser` now also records response headers.

### Files touched
* **New:** `admin/reports.php`, `db_setup/migrations/003_report_indexes.sql`, `assets/img/favicon.svg`, `docs/CHANGELOG.md`, `docs/screenshots/15-admin-reports.jpg`, `docs/screenshots/16-admin-bookings-export.jpg`
* **Changed:** `includes/functions.php`, `includes/booking-functions.php`, `includes/config.php`, `includes/header.php`, `includes/footer.php`, `includes/admin-nav.php`, `admin/bookings.php`, `admin/index.php`, `admin/calendar.php`, `admin/system.php`, `api/*.php`, `assets/js/script.js`, `assets/css/style.css`, `.htaccess`, `index.php`, `about.php`, `services.php`, `assets/img/*.jpg` (resized), `db_setup/db.sql`, `db_setup/db-live.sql`, `tests/run-tests.php`, docs
* **No changes to** the booking rules, prices, statuses or the database tables' columns, so no data is affected.

### Upgrading an existing copy
1. Pull the branch.
2. Either re-import `db_setup/db.sql` (resets to demo data) **or** keep your data and import `db_setup/migrations/003_report_indexes.sql` once.
3. Open **Admin → System**: it should show *0 problems* (GD and the demo password may show as warnings locally).

### Ideas for next (not done, to discuss with the team)
* Email notifications (booking received / confirmed / payment recorded) and a "forgot password" link. They need a mail account / SMTP on the live host.
* Upload a proof-of-payment photo (GCash screenshot) from the customer's booking page.
* Damage / missing-item notes when a booking is completed, to track losses.
* Apply the PM's formal design/output documentation once it's ready.

---

## v1.0 — Full system for the WBS / Kanban (Nov 24, 2026)

Commits `4cb81a2` + merge `3265abe` on `romerojohndavidbranch`. Built every work package on the WBS (1.1–1.6): database, login and roles, catalog and booking with live inventory, booking management, admin panel (dashboard, bookings, calendar, inventory, services, appointments, inquiries, customers, accounts, system check), deployment files, automated tests (101 checks) and documentation. Merged Mark Anthony's layout/CSS fixes from `main`. See [PROJECT-STATUS.md](PROJECT-STATUS.md) for the full traceability table.
