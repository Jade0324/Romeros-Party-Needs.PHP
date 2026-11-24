# Deployment Guide — Putting the Site Online

**WBS 1.5.1 Live Server Database Setup & Migration · 1.5.2 Website Deployment & Final Testing**

This guide moves the system from XAMPP on your computer to a real web host, so anyone can open it with a normal link. Any host with **PHP 8.0+** and **MySQL 5.7+ / MariaDB 10.2+** works: free ones like InfinityFree, or paid shared hosting with cPanel. The screens differ a little between hosts, but the steps are the same.

> ⏱ About 30–45 minutes the first time.

---

## Before you start (on your computer)

1. **Run the automated tests** and make sure everything passes:
   ```
   C:\xampp\php\php.exe tests\run-tests.php --reset
   ```
2. If anyone changed `db_setup/db.sql`, regenerate the hosting version:
   ```
   C:\xampp\php\php.exe db_setup\make-live-sql.php
   ```
3. Decide which data goes live:
   * **Demo data** (for a class demo): use `db_setup/db-live.sql`.
   * **Your own local data** (bookings you entered in XAMPP): export it, see Step 3B.

---

## Step 1: Create the hosting account and website

Sign up with your host and create a website/domain (e.g. `romerospartyneeds.free.nf`). Wait until the host says the site is active.

## Step 2: Create the MySQL database

In the hosting control panel, open **MySQL Databases** and create one. Write down these five values, because you'll need them in Step 5:

| Value | Example |
|---|---|
| MySQL host name | `sql123.infinityfree.com` (often just `localhost` on cPanel) |
| Database name | `if0_12345678_romeros` |
| Username | `if0_12345678` |
| Password | (your hosting/database password) |
| Port | `3306` |

> The host decides the database name. That's why there's a separate `db-live.sql`: it doesn't try to create its own `romeros_party_db`, which hosts don't allow.

## Step 3: Import the database

Open **phpMyAdmin** from the hosting panel.

**A) Demo data (recommended for presentations)**
1. Click **your database** in the left sidebar. This matters.
2. **Import** tab → choose `db_setup/db-live.sql` → **Import** (button at the bottom).
3. You should see 13 tables.

**B) Migrating your own local data**
1. On your computer, open `http://localhost/phpmyadmin` → click `romeros_party_db` → **Export** → *Quick*, format *SQL* → **Export**. You get a `.sql` file.
2. On the host's phpMyAdmin, click **your database** → **Import** → choose that file.

**Upgrading a database that's already live** (after pulling new code): don't re-import (that would erase it). Instead, import each new file in `db_setup/migrations/` in number order. Each file says at the top what it changes. A "Duplicate column" or "Duplicate key name" error means that migration was already applied.

| Migration | Adds |
|---|---|
| `001_user_status_and_login_attempts.sql` | Account active/inactive, last login, login throttling table |
| `002_inventory_item_photos.sql` | Photos for rental items |
| `003_report_indexes.sql` | Indexes that keep the dashboard and Reports fast (data unchanged) |

## Step 4: Upload the files

Use the host's **File Manager** or an FTP program like **FileZilla** (the host shows your FTP host, user and password). Upload into the web folder, usually `htdocs/` or `public_html/`.

**Upload:**
```
index.php  about.php  services.php  contact.php  book.php  booking.php
account.php  appointment.php  login.php  signup.php  logout.php
.htaccess              ← important (it's a hidden file; enable "show hidden files")
admin/   api/   assets/   includes/   db_setup/
```

**Don't upload:** `.git/`, `.vscode/`, `tests/`, `docs/`, `includes/db.local.php` from your computer (you'll make a server version next).

> Upload the files directly into `htdocs/` for a link like `https://yoursite.free.nf/`. A subfolder also works (`htdocs/romeros/` → `https://yoursite.free.nf/romeros/`), because the site works out its own address.

## Step 5: Connect the site to the database

In the File Manager, go into `includes/`, copy `db.local.example.php` to a new file called **`db.local.php`**, and fill in the Step 2 values:

```php
<?php
$db_host = "sql123.infinityfree.com";
$db_user = "if0_12345678";
$db_pass = "your-database-password";
$db_name = "if0_12345678_romeros";
$db_port = 3306;
$app_env = "production";   // hides PHP errors from visitors
```

## Step 6: Folder permissions

Make sure `assets/uploads/` is writable (permissions **755**). Admin photo uploads are saved there. Most hosts already set this.

## Step 7: Turn on HTTPS

In the hosting panel, find **SSL/TLS** and install the free certificate. HTTPS encrypts passwords on the way to the server. After it's active, always use the `https://` link.

## Step 8: First login and System check

1. Open your site. The home page should load.
2. Log in as `admin@romerospartyneeds.com` / `admin123`.
3. Go to **Admin → System**. It checks PHP, the database, tables, uploads, protection files, HTTPS and passwords.
4. Fix anything red, then refresh until **0 problems** shows. Two yellow warnings are worth fixing but not required:
   * **Indexes missing:** import `db_setup/migrations/003_report_indexes.sql` (only if the database came from an older `db.sql`).
   * **gd (optional) not enabled:** uploaded photos are kept at full size. Most hosts have GD on. Where it's a choice, enable it (cPanel → *Select PHP Version* → tick `gd`) so big phone photos are shrunk automatically.

   Compression and browser caching in `.htaccess` switch on by themselves when the host supports them; nothing to configure.
5. **Change the admin password immediately:** Admin → Accounts → Edit → *Reset password*. Better still, create a personal admin account for each family member, then deactivate the demo admin.

## Step 9: Going live with real customers (optional)

When the class demo is over and real customers will use it:
1. Back up first: phpMyAdmin → Export.
2. Import `db_setup/clear-demo-data.sql` into your database. It removes the demo bookings, customers, appointments, payments and inquiries, but keeps your admin accounts, categories, items and services.
3. Update prices and quantities in **Admin → Inventory** and **Admin → Services**.

## Step 10: Final testing on the live site

⚠️ **Never run `tests/run-tests.php` against the live site.** It resets the database.

Instead, go through the checklist in [GETTING-STARTED.md](../GETTING-STARTED.md#full-test-checklist) on the live link, on both a computer and a phone. At minimum:

- [ ] Home, Services, About, Contact load over `https://`
- [ ] `https://yoursite/db_setup/db.sql` shows **403 Forbidden** (files are protected)
- [ ] Sign up as a new customer, book an event, see it in My bookings
- [ ] As admin: confirm it, record a payment, see it on the dashboard and calendar
- [ ] Upload a photo for an item; it appears on the Services page
- [ ] Book an appointment; it shows in Admin → Appointments
- [ ] Contact form message appears in Admin → Inquiries
- [ ] Admin → System shows 0 problems

Record the results (date, tester, pass/fail, screenshot) for the final report.

---

## Troubleshooting on the host

| Symptom | Fix |
|---|---|
| **500 Internal Server Error** right after uploading | Some hosts don't allow `Options` in `.htaccess`. Delete the `Options -Indexes` line in `.htaccess` (and `Options -Indexes -ExecCGI` in `assets/uploads/.htaccess`), then reload. |
| "Database connection failed" | Re-check the 5 values in `includes/db.local.php`. The host name is usually **not** `localhost` on free hosts. |
| "Table … doesn't exist" | The import went into the wrong database, or didn't finish. Click your database first, then import again. |
| Blank white page | Temporarily set `$app_env = "development";` in `db.local.php` to see the error, fix it, then set it back to `"production"`. |
| Photo upload fails | `assets/uploads/` missing or not writable. Create it and set permissions to 755. |
| Times/dates look off by hours | The app uses Philippine time (`Asia/Manila`). Admin → System shows if the database clock differs. The demo data's dates are based on the database clock. |
| CSS/JS missing, page unstyled | The `assets/` folder didn't upload completely. Re-upload it. |
| Logged out every few minutes | Some free hosts clear sessions aggressively. Keep the tab active, or try another host. |
