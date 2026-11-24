# Project Status: WBS Traceability

This page maps every work package on the project's WBS / Kanban board to **what was built**, **where it lives**, and **how it was verified**. Use it to move cards and to back up the final report.

**Verification key**
* **Auto:** covered by `tests/run-tests.php` (100 automated checks, all passing as of Nov 24, 2026).
* **Shot:** screenshot in [`docs/screenshots/`](screenshots/) (see [SCREENSHOTS.md](SCREENSHOTS.md)).
* **Manual:** item in the checklist in [GETTING-STARTED.md](../GETTING-STARTED.md#full-test-checklist).

| | |
|---|---|
| Work packages | 15 |
| Implemented | 15 |
| Waiting on the team | Final presentation (part of 1.6), PM's formal documentation, live-hosting account (1.5.2 can only be finished once it exists) |

---

## 1.1 Database Architecture & Schema (Kanban #3)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.1.1** Relational Schema Design (#4) | 13 normalized InnoDB tables with foreign keys, unique keys and indexes; junction tables for booking↔items and booking↔services; prices copied into bookings | `db_setup/db.sql` · ERD + data dictionary in [SYSTEM-OVERVIEW.md](SYSTEM-OVERVIEW.md) | Auto (System check: "13 of 13 tables", "Schema up to date") |
| **1.1.2** Sample Data & SQL Export File (#5) | Demo accounts, catalog, 4 bookings (two share a date to show shared stock), payments, appointments, inquiries, blocked date, all dated relative to the import day. Hosting version, migrations, clear-demo script | `db_setup/db.sql`, `db-live.sql`, `make-live-sql.php`, `migrations/`, `clear-demo-data.sql` | Auto (every run imports `db.sql`); `db-live.sql` and `clear-demo-data.sql` tested on scratch databases |

## 1.2 Authentication & Role-Based Access Control (Kanban #6)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.2.1** Role-Based Login & Navigation (#7) | Sign up / log in / log out; customer vs admin roles; menu changes by role (account dropdown, admin tabs); login returns you to the page you were on; admins land on the dashboard | `login.php`, `signup.php`, `includes/header.php`, `includes/admin-nav.php` | Auto: "customer login goes to My bookings", "admin login goes to the dashboard", "booking requires login (redirects…?next=)" |
| **1.2.2** Protected Page Access Guards (#8) | `require_login()` / `require_admin()` on every protected page; friendly 403/404 pages; owners-only booking access; CSRF tokens; login throttling; deactivated accounts; idle logout; `.htaccess` blocking `includes/`, `db_setup/`, `tests/`, `.sql`/`.md` files and folder listings | `includes/functions.php`, `includes/login-form.php`, `.htaccess` files | Auto: 5 "direct web access blocked" checks, CSRF rejection, 403/404 checks, lock-out after 5 wrong passwords, deactivated user signed out |

## 1.3 Customer Catalog & Booking Portal (Kanban #9)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.3.1** Database-Driven Services & Rentals Catalog (#10) | Categories, items (with photos), prices and services all loaded from the database; "check availability for a date" | `services.php`, `assets/js/services.js`, `api/availability.php` | Auto ("new item shows on the public Services page") · Shot 02 |
| **1.3.2** Event Booking Submission Form (#11) | Availability calendar, live stock per date, fully-booked services disabled, running total + 30% down payment, client + server validation, transaction locking against double-booking; admins can book for walk-in customers | `book.php`, `assets/js/booking.js`, `includes/booking-functions.php`, `api/calendar.php` | Auto: section "Customer: catalog & booking" (21 checks: over-booking, full service, blocked date, notice period, times, empty, totals, stock decrement…) · Shot 03, 14 |
| **1.3.3** Customer Booking Management Page (#12) | My bookings dashboard; booking details with timeline, payments and appointments; **edit pending bookings**; cancel (≥3 days before); print slip; appointments; profile and password | `account.php`, `booking.php`, `book.php?edit=`, `appointment.php` | Auto: section "Customer: managing a booking" (19 checks) · Shot 04, 05, 06, 07 |

## 1.4 Administrator Control Panel (Kanban #13)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.4.1** Admin Overview Dashboard (#14) | Bookings to confirm, events in 30 days, money collected this month, outstanding balance, new inquiries, 7-day setup schedule, appointments this week, low-stock watch | `admin/index.php` | Auto (page loads) · Shot 08 |
| **1.4.2** Services & Rentals Management (#15) | Inventory CRUD with photo upload and reserved-vs-free per date (can't go below what's booked); categories CRUD; services CRUD with daily team capacity; hide/show without breaking old bookings | `admin/inventory.php`, `admin/categories.php`, `admin/services.php`, `includes/photo-field.php` | Auto: inventory guard, item + photo, category + photo, fake-image rejection, uploads can't run scripts · Shot 11 |
| **1.4.3** Bookings & Inquiries Management | Search/filter bookings; confirm / complete / cancel / restore; record payments (no overpaying); reschedule with stock re-check; edit items; internal notes; month calendar with day plan + truck load list; block dates; appointments; inquiries with reply/close | `admin/bookings.php`, `admin/booking.php`, `admin/calendar.php`, `admin/appointments.php`, `admin/inquiries.php`, `admin/customers.php` | Auto: confirm, overpay refused, payment → partial, reschedule refused/allowed, restore, day-plan totals, block/unblock · Shot 09, 10 |
| **1.4.4** User Account & Role Management | Create accounts, change roles, reset passwords, deactivate/reactivate, delete accounts with no history; can't demote/deactivate yourself or remove the last admin; changes apply immediately | `admin/users.php` | Auto: section "Accounts & roles" (11 checks) · Shot 12 |

## 1.5 Live Hosting Deployment & Quality Assurance (Kanban #18)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.5.1** Live Server Database Setup & Migration | Hosting-safe SQL file, numbered migrations for upgrading a live database without losing data, per-server settings file (`db.local.php`), production mode that hides errors, step-by-step guide | `db_setup/db-live.sql`, `db_setup/migrations/`, `includes/db.local.example.php`, [DEPLOYMENT.md](DEPLOYMENT.md) | `db-live.sql` imported into a differently-named database (13 tables) |
| **1.5.2** Website Deployment & Final Testing | Automated end-to-end test suite; Admin → System health check for the live server; live testing checklist | `tests/run-tests.php`, `admin/system.php`, [DEPLOYMENT.md §10](DEPLOYMENT.md#step-10-final-testing-on-the-live-site) | Auto: 100/100 passing locally · Shot 13. **Live run pending the hosting account.** |

## 1.6 Project Documentation & Final Presentation (Kanban #21)

| WBS | Deliverable | Where | Verified |
|---|---|---|---|
| **1.6.1** System Overview, ERD & Data Dictionary | Purpose, roles, architecture diagram, ERD, full data dictionary, status flows, business rules, page/API map, security measures | [SYSTEM-OVERVIEW.md](SYSTEM-OVERVIEW.md) | Data dictionary generated from the live schema |
| **1.6.2** UI Screenshots, Setup Guide & Verification Checklist | 14 screenshots (desktop + mobile), install guide, 5-minute demo, full test checklist, deployment guide | [SCREENSHOTS.md](SCREENSHOTS.md), [GETTING-STARTED.md](../GETTING-STARTED.md), [README.md](../README.md), [DEPLOYMENT.md](DEPLOYMENT.md) | Screenshots captured from the running system |

---

## Re-running the verification

```
C:\xampp\php\php.exe tests\run-tests.php --reset
```

The suite opens the real site over HTTP as a guest, two customers and an admin. It resets the database to the demo data before and after. It prints one PASS/FAIL line per rule, grouped by WBS package.
