# Romero's Party Needs

A PHP and MySQL web application for **Romero's Party Needs**, featuring party equipment rentals, event hosting and organizing services, an **online booking system tied to live inventory**, appointment scheduling, customer accounts, and an admin back office.

> 👋 **New here or just cloned the repo?** Start with **[GETTING-STARTED.md](GETTING-STARTED.md)**. It walks you through installing, logging in with the demo accounts, a 5-minute demo, and a full test checklist.

## Documentation
| Document | For |
|---|---|
| [GETTING-STARTED.md](GETTING-STARTED.md) | Installing, demo accounts, guided demo, test checklist, troubleshooting |
| [docs/SYSTEM-OVERVIEW.md](docs/SYSTEM-OVERVIEW.md) | Architecture, **ERD**, **data dictionary**, status flows, business rules, security |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Putting the site on a live web host, migrating the database, final testing |
| [docs/PROJECT-STATUS.md](docs/PROJECT-STATUS.md) | Every WBS / Kanban work package → where it's built → how it was verified |
| [docs/SCREENSHOTS.md](docs/SCREENSHOTS.md) | UI screenshots of every main screen |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | **Progress log**: what changed in each version, why, and how it was tested |

## Tech Stack
* **Frontend:** HTML5, CSS3, Bootstrap 5, jQuery (AJAX for live availability)
* **Backend:** PHP (`mysqli` prepared statements, sessions, CSRF tokens)
* **Database:** MySQL / MariaDB (XAMPP)

## Features

### For customers
| Page | What it does |
|---|---|
| `services.php` | Rentals and services with prices, loaded from the database. Pick a date to see what's still free that day. |
| `book.php` | Booking form: availability calendar, live stock per item for the chosen date, hosts/organizers, running total and down payment. |
| `booking.php?ref=…` | Booking details: status, setup schedule, items, payments, timeline, linked appointments, cancel, print slip. |
| `book.php?edit=…` | Edit a booking while it's still pending (date, items, services); stock is re-checked. |
| `appointment.php` | Book an ocular visit / planning consultation / showroom visit in a free time slot, optionally linked to a booking. |
| `account.php` | My bookings, appointments, messages, profile, and password. |
| `contact.php` | Inquiry form (linked to the account when logged in). |

### For admins (`/admin`)
| Page | What it does |
|---|---|
| Dashboard | Bookings to confirm, upcoming events, money collected/outstanding, this week's setup schedule, appointments, low-stock warnings, new inquiries. |
| Bookings | Search/filter all bookings (50 per page), **export to CSV**. On a booking: confirm / complete / cancel / restore, record payments, reschedule, edit items, internal notes. |
| Calendar | Month view of setups and appointments. Click a day for the **day plan + truck load list**. Block/unblock dates. |
| Inventory & Categories | Add/edit rental items and categories with photos; see reserved vs. free stock on any date. |
| Services | Add/edit services (with photos) and how many events each team can cover per day. |
| Appointments / Inquiries / Customers | Manage each list; everything links back to the related bookings. |
| Reports | Booked value vs. money collected by month, top rental items, services, event types. Monthly summary and payments **CSV downloads**. |
| Accounts | Create accounts, change roles, reset passwords, deactivate/reactivate users. |
| System | Health check for installs and live deployments: PHP, database, schema, uploads, security. |

Admins can also use **Book now** to create a booking for a walk-in or phone customer.

### How the features connect
* **Inventory ↔ bookings:** stock is never stored as "available". For any date it's calculated as *quantity owned − quantity held by pending/confirmed bookings on that date*. Cancelling a booking frees its items immediately, and the booking form can't take more than what's free.
* **Services ↔ bookings:** each service has a daily capacity (e.g. 2 host teams). Once a date is full, the service shows as fully booked for that date.
* **Calendar ↔ bookings:** each date can hold a limited number of setups (crew capacity), and blocked dates stop new bookings and appointments.
* **Appointments ↔ bookings:** an appointment can be tied to a booking, must happen before the event, and shows up in that booking's timeline. Cancelling the booking cancels its appointments.
* **Payments ↔ bookings:** payments add up to paid / partial / unpaid, and the dashboard totals what's collected and outstanding.
* **No double-booking:** saving a booking runs in a database transaction that locks that date, so two people can't grab the last tent at the same moment.

All of these rules live in one file, `includes/booking-functions.php`, which the booking form, the AJAX API, the services page and the admin pages all use.

## Local Setup Guide (XAMPP)

1. **Move Project to `htdocs`**
   Place the project folder inside your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\Romeros-Party-Needs.PHP`). Any folder name works; links adjust automatically.

2. **Start XAMPP Services**
   Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

3. **Import `db.sql` in phpMyAdmin**
   * Open `http://localhost/phpmyadmin` in your browser.
   * Click the **Import** tab at the top.
   * Select `db_setup/db.sql` from this repository and click **Import**. This creates the `romeros_party_db` database with all tables and demo data.
   * ⚠️ Re-importing **resets** every table to the demo data.

4. **Check Your MySQL Port (`3306` or `3307`)**
   * Look at the **Port(s)** column next to **MySQL** in your XAMPP Control Panel.
   * Open `includes/db.php` and set `$db_port` to match your XAMPP MySQL port:
     ```php
     $db_port = 3307; // Change to 3306 if your XAMPP MySQL uses the default port
     ```
   * **Tip for the group:** instead of editing `db.php`, copy `includes/db.local.example.php` to `includes/db.local.php` and set your port there. That file is git-ignored, so your port never overwrites a groupmate's.

5. **Run the Website**
   Open your browser and visit:
   ```text
   http://localhost/Romeros-Party-Needs.PHP/index.php
   ```

## Automated Tests

With Apache and MySQL running:
```
C:\xampp\php\php.exe tests\run-tests.php --reset
```
Runs 126 end-to-end checks as a guest, customers and an admin: stock limits, fully booked services, edits, payments, roles, uploads, access guards, reports and exports. (Add `-d extension=gd` after `php.exe` to include the 2 photo-resizing checks.) It **resets the database to the demo data** before and after, so never point it at a live site.

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@romerospartyneeds.com` | `admin123` |
| Customer | `juan@example.com` | `customer123` |
| Customer | `maria@example.com` | `customer123` |

The demo data includes bookings, payments, appointments, inquiries and a blocked date, all relative to the day you import, so the dashboard and calendar always have something to show. Two bookings share the same date on purpose to show stock and the host service being used up.

## Business Rules (change in `includes/config.php`)

| Setting | Default | Meaning |
|---|---|---|
| `DELIVERY_FEE` | ₱1,000 | Added to bookings with rental items |
| `DOWN_PAYMENT_RATE` | 30% | Suggested down payment shown to customers |
| `MAX_BOOKINGS_PER_DAY` | 4 | Crew setups per date |
| `MIN_LEAD_DAYS` | 3 | Earliest bookable date from today |
| `CANCEL_CUTOFF_DAYS` | 3 | Customers can cancel online until this many days before |
| `APPOINTMENT_SLOTS` | 9:00, 10:30, 1:00, 2:30, 4:00 | One appointment per slot, Mon–Sat |
| `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCK_MINUTES` | 5 / 15 | Wrong-password lock-out |
| `SESSION_IDLE_MINUTES` | 120 | Automatic logout after inactivity |

Prices and quantities are placeholders. Update them in **Admin → Inventory** and **Admin → Services**.

## Project Structure
```text
├── index.php, services.php, about.php, contact.php     Public pages
├── book.php, booking.php, appointment.php, account.php Customer pages (login required)
├── login.php, signup.php, logout.php
├── admin/                 Admin back office (admin role required), incl. reports.php, users.php, categories.php, system.php
├── api/                   JSON endpoints used by jQuery
│   ├── availability.php   Free stock + service slots for a date
│   ├── calendar.php       Open/limited/full status for each day of a month
│   └── slots.php          Free appointment times for a date
├── includes/
│   ├── config.php            Business rules
│   ├── db.php                Database connection
│   ├── functions.php         Helpers: escaping, CSRF, login checks, query helpers
│   ├── booking-functions.php Availability + booking rules
│   ├── db.local.example.php  Template for per-computer / live-server settings
│   └── header.php, footer.php, admin-nav.php, photo-field.php, *-form.php
├── assets/css/style.css
├── assets/js/             script.js (site-wide) + booking.js, appointment.js, services.js
├── assets/uploads/        Photos uploaded by admins (scripts can't run here)
├── db_setup/
│   ├── db.sql             Schema + demo data (local XAMPP)
│   ├── db-live.sql        Same, for web hosts (generated by make-live-sql.php)
│   ├── migrations/        Upgrade an existing database without losing data
│   └── clear-demo-data.sql  Remove demo bookings/customers before going live
├── tests/run-tests.php    Automated end-to-end tests
└── docs/                  System overview/ERD, deployment, project status, changelog, screenshots
```

## Database Tables
`users`, `login_attempts`, `categories`, `inventory_items`, `services`, `bookings`, `booking_items`, `booking_services`, `payments`, `booking_logs`, `appointments`, `blocked_dates`, `inquiries`. See the ERD and data dictionary in [docs/SYSTEM-OVERVIEW.md](docs/SYSTEM-OVERVIEW.md).

**Already have the database from an older version?** Import the files in `db_setup/migrations/` (in number order) instead of re-importing `db.sql`. That keeps your data.

## Troubleshooting
* **"Database connection failed"**: MySQL isn't started, the port in `db.php` is wrong, or `db.sql` hasn't been imported.
* **XAMPP says "MySQL shutdown unexpectedly"**: XAMPP's data folder is corrupted (common after a crash or forced shutdown). Stop MySQL, rename `C:\xampp\mysql\data` to `data_old` (your backup), copy `C:\xampp\mysql\backup` to a new `data` folder, start MySQL, and re-import `db.sql`.
* **Something looks wrong after setup**: log in as admin and open **Admin → System**. It lists exactly what's missing.

## Performance notes
* Availability is calculated with one grouped query per date (see `includes/booking-functions.php`), backed by indexes on booking dates, payment dates and inquiry status.
* CSS/JS links are versioned (`style.css?v=…`), and `.htaccess` turns on compression and 30-day browser caching where the server supports it.
* Uploaded photos are scaled down to 1600 px automatically when PHP's GD extension is enabled.
* Details and before/after numbers: [docs/CHANGELOG.md](docs/CHANGELOG.md).
