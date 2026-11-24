# Getting Started & Testing Guide — Romero's Party Needs

Welcome! This guide is for anyone opening this project for the first time: groupmates, instructors, or anyone who cloned the repository and wants to run and try the system on their own computer.

> **Short on time?** Do [Part 1](#part-1--install-and-run-about-10-minutes) to get it running, then follow the [5-minute demo](#5-minute-demo) in Part 2.
> For the full feature list and project structure, see the main [README.md](README.md).

---

## What is this system?

**Romero's Party Needs** is a family-owned party rental business (tables, chairs, tents, signage, catering setups). Its partner, South Flaneur Events, provides hosts and wedding/party organizers.

This website lets the business take bookings online instead of through chat and phone calls:

* **Customers** browse rentals and prices, check which dates are free, book their event with the exact items they need, schedule an ocular visit or planning consultation, and track their booking and payments.
* **The family (admins)** confirm bookings, record payments, see each day's setup schedule and what to load on the truck, manage stock and prices, and answer inquiries.

Everything is connected. A booking reserves real stock for that date, so the same chairs can't be promised to two events. Each service team can only cover so many events per day. Appointments are tied to the event they're for. And payments show up on the booking, the customer's account, and the admin dashboard.

---

## Part 1 — Install and run (about 10 minutes)

### What you need
* **XAMPP** with PHP 8.0 or newer (download from <https://www.apachefriends.org>)
* **Git** (optional; you can also download the ZIP from GitHub)
* Any modern browser. An internet connection is needed for Bootstrap, jQuery and the Google Fonts CDN.

### Step 1: Get the code into `htdocs`

**With Git** (open a terminal):
```bash
cd C:\xampp\htdocs
git clone https://github.com/Jade0324/Romeros-Party-Needs.PHP.git
```

**Without Git:** on the GitHub page click **Code → Download ZIP**, then extract it into `C:\xampp\htdocs\`.

You should end up with `C:\xampp\htdocs\Romeros-Party-Needs.PHP\index.php`.
(If the folder has a different name, that's fine. Just use that name in the URLs below.)

### Step 2: Start Apache and MySQL
Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**. Both should turn green.
Note the number in the **Port(s)** column for MySQL: usually `3306`, sometimes `3307`.

### Step 3: Create the database
1. Go to <http://localhost/phpmyadmin>
2. Click the **Import** tab.
3. Choose the file `db_setup/db.sql` from the project folder and click **Import** (bottom of the page).
4. You should now see a database called **`romeros_party_db`** with 13 tables.

### Step 4: Match the MySQL port
Open `includes/db.php` and make `$db_port` match the port from Step 2:
```php
$db_port = 3306; // or 3307 — whatever XAMPP shows for MySQL
```
> **Working in a group?** Instead of editing `db.php`, copy `includes/db.local.example.php` to `includes/db.local.php` and put your port there. That file is ignored by Git, so you won't overwrite each other's settings.

### Step 5: Open the site
<http://localhost/Romeros-Party-Needs.PHP/>

If you see the home page with the **Book now** button, you're done. 🎉

### Step 6 (optional): Check everything automatically
* Log in as the admin (below) and open **Admin → System**. It should say **0 problems**.
* Or run the automated test suite (101 checks, about 30 seconds) from a terminal in the project folder:
  ```
  C:\xampp\php\php.exe tests\run-tests.php --reset
  ```
  It **resets the database to the demo data** before and after, so don't run it if you've entered data you want to keep.

---

## Part 2 — Try it out

### Demo accounts

| Role | Email | Password | Try it for |
|---|---|---|---|
| Admin (the family) | `admin@romerospartyneeds.com` | `admin123` | Dashboard, confirming bookings, payments, calendar, stock |
| Customer | `juan@example.com` | `customer123` | Has a confirmed birthday with a payment, plus an appointment |
| Customer | `maria@example.com` | `customer123` | Has a pending wedding and a debut |

You can also **Sign up** as a brand-new customer.

> The demo bookings are dated relative to **the day you imported `db.sql`** (e.g. "6 days from now"), so the calendar and dashboard always have current data to show.

### 5-minute demo
1. **Services & Rentals:** pick a date in *"Check availability for a date"*. The stock counts change to show what's free on that day.
2. **Log in as Juan**, then click **Book now**.
3. In the calendar, click the **first date that has bookings** (6 days from your import date). Notice:
   * **Tiffany chair** shows only **20 available** (100 are already booked by Maria's debut that day).
   * **Event host / emcee** shows **Fully booked**: both host teams are taken that day.
4. Add some chairs, a tent and a service. Watch the **total and 30% down payment** update on the right. Fill in the event details and **Submit booking**.
5. You land on the booking page with a reference like `RPN-XXXXXX`. Click **Schedule a visit** and book an ocular inspection.
6. **Log out, log in as Admin.** The dashboard shows your new booking waiting to be confirmed.
7. Open it from **Bookings**. **Confirm** it, then **record a payment**. Go back to Juan's account: the status, payment and timeline have all updated.
8. Open **Calendar** and click that day. The **Day plan** lists every setup in time order and the **truck load list** of everything that has to go out.

### Full test checklist
Use this to check that every feature works. Each line says what to do → what should happen.

**Customer side**
- [ ] Sign up with a new email → you're logged in and taken to *My bookings*
- [ ] Book now without logging in → you're sent to log in, then brought back to the booking form
- [ ] Booking calendar → past/too-soon dates are greyed out; the blocked date (15 days after import) is crossed out
- [ ] Choose a date less than 3 days away → error: needs at least 3 days' notice
- [ ] Ask for more of an item than is available → refused with "Only X left"
- [ ] Pick a fully booked service → it can't be ticked
- [ ] Set the setup time *after* the event start → error
- [ ] Submit a valid booking → booking page shows status **Pending**, items, total, timeline
- [ ] Booking page → **Edit booking** → change quantities → total updates; asking for more than is free is refused
- [ ] Log in as Juan and try `book.php?edit=RPN-7K2M9Q` (a *confirmed* booking) → not editable online
- [ ] Open another customer's booking link (e.g. log in as Juan and visit `booking.php?ref=RPN-4H8T2W`) → "Booking not found"
- [ ] Book an appointment → slots that are taken show as crossed out; Sundays are refused
- [ ] Try to book an appointment for a booking on a date *after* its event → refused
- [ ] Cancel a booking → its items become available again on that date (check with *Check availability* on Services)
- [ ] Send a message on **Contact** while logged in → it appears under *Your messages* in My bookings
- [ ] **Print booking slip** → clean printable page without the menu
- [ ] My bookings → **Change password** with a wrong current password → refused
- [ ] Log in with a wrong password 5 times → the 6th attempt is locked for 15 minutes, even with the right password

**Admin side** (log in as admin)
- [ ] Dashboard numbers: bookings to confirm, money collected this month, outstanding balance, new inquiries
- [ ] Bookings → filter by status, search by name or reference
- [ ] Booking → **Confirm** → status changes and the customer's timeline shows it
- [ ] Booking → **Record payment** → status becomes *Partial* or *Paid*; paying more than the balance is refused
- [ ] Booking → change the date to one without enough stock → refused with the reason; a free date works
- [ ] Booking → **Cancel**, then **Restore** → restore only works if the stock is still free
- [ ] Calendar → click a day → day plan + truck load list; **Block this date** → customers can no longer pick it
- [ ] Inventory → change the date at the top to see reserved vs free; add a new item → it appears on the Services page and booking form
- [ ] Inventory → lower Tiffany chairs below what's already booked → refused
- [ ] Inventory → **Hide** an item → it disappears from the Services page and booking form, but old bookings keep it
- [ ] Inventory → add an item **with a photo** → the photo shows on the Services page and booking form
- [ ] Inventory → **Manage categories** → add a category with a photo; try uploading a non-image file → refused
- [ ] Services → change "Events per day" for a team → the booking form respects the new limit
- [ ] Appointments / Inquiries → mark done / replied / closed
- [ ] Customers → see each customer's number of bookings and total paid; click a name to see their bookings
- [ ] **Book now** as admin → you can choose which customer the booking is for (walk-in / phone orders)
- [ ] Accounts → create a customer account → they can log in
- [ ] Accounts → **Deactivate** that account → if they're logged in, they're signed out on their next click and can't log back in
- [ ] Accounts → try to change your own role or deactivate yourself → refused
- [ ] Accounts → make Juan an admin → his menu gets *Admin dashboard* without logging out; change him back
- [ ] System → 0 problems (the demo admin password shows as a warning; change it before going live)
- [ ] Open `http://localhost/…/db_setup/db.sql` or `…/includes/db.php` in the browser → **403 Forbidden**

### Starting over
Want fresh demo data? Just **import `db_setup/db.sql` again** in phpMyAdmin. It resets every table. (Anything you created while testing will be erased.)

---

## Part 3 — Troubleshooting

| Problem | Fix |
|---|---|
| **"Database connection failed"** page | MySQL isn't started in XAMPP, **or** `$db_port` in `includes/db.php` doesn't match XAMPP's MySQL port, **or** `db.sql` wasn't imported. The error page tells you which. |
| **"Unknown database romeros_party_db"** | Import `db_setup/db.sql` (Step 3). |
| **"Table … doesn't exist"** or missing columns | You have an older copy of the database. Re-import `db_setup/db.sql`. |
| **MySQL won't start: "shutdown unexpectedly"** | XAMPP's data files got corrupted (usually after a crash or forced shutdown). Stop MySQL, **rename** `C:\xampp\mysql\data` to `data_old`, **copy** `C:\xampp\mysql\backup` to a new `data` folder, start MySQL, then re-import `db.sql`. |
| **MySQL won't start: port 3306 in use** | Another MySQL (e.g. a separate MySQL 8 install) is already using 3306. Either stop that service (Windows **Services** app → *MySQL80* → Stop), or change XAMPP's MySQL to 3307 (XAMPP → MySQL **Config** → `my.ini` → change both `port=3306` lines to `3307`) and set `$db_port = 3307`. |
| **Apache won't start: port 80 in use** | Something else uses port 80 (often IIS, Skype or another web server). Stop it, or change Apache's port in XAMPP → Apache **Config** → `httpd.conf` (`Listen 8080`), then use `http://localhost:8080/…`. |
| **Page looks unstyled** | You're offline. Bootstrap, jQuery and fonts load from the internet. |
| **Availability/calendar never loads** | Open the browser console (F12). Usually the site was opened as a file (`file:///…`) instead of through `http://localhost/…`. It must run through XAMPP. |
| **"Your session expired or the form was invalid"** | The page was open too long or cookies are blocked. Refresh the page and submit again. |
| **Logged in but can't open /admin** | Only the admin account can. Log in as `admin@romerospartyneeds.com`. |

### Putting it online
See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for step-by-step instructions to host it on a real web server.

### Optional: make `http://localhost` open this site
By default `http://localhost` opens the XAMPP dashboard. To open this site instead, edit `C:\xampp\htdocs\index.php` (save a copy first) so it contains:
```php
<?php
header('Location: /Romeros-Party-Needs.PHP/index.php');
exit;
```
The XAMPP dashboard is still at <http://localhost/dashboard/>.

---

## Where to look in the code

| If you want to… | Look in |
|---|---|
| Change prices, stock, services | **Admin → Inventory / Services** (no code needed) |
| Change fees, crew capacity, notice period, appointment times | `includes/config.php` |
| Understand how availability is calculated | `includes/booking-functions.php` |
| See the database design | `db_setup/db.sql` (every table is commented) |
| Change the booking form behaviour (calendar, live totals) | `book.php` + `assets/js/booking.js` |
| Change the look | `assets/css/style.css` (colours are variables at the top) |
| Understand the whole system (ERD, data dictionary, flows) | [docs/SYSTEM-OVERVIEW.md](docs/SYSTEM-OVERVIEW.md) |
| See what each WBS work package delivered | [docs/PROJECT-STATUS.md](docs/PROJECT-STATUS.md) |

Questions or found a bug? Open an issue on the GitHub repository or message the group.
