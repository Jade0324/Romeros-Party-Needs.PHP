# Romero's Party Needs

A PHP and MySQL web application for **Romero's Party Needs**, featuring party equipment rentals, event hosting and organizing services, customer inquiry forms, and user authentication.

## Tech Stack
* **Frontend:** HTML5, CSS3, Bootstrap 5, jQuery
* **Backend:** PHP (`mysqli` prepared statements)
* **Database:** MySQL / MariaDB (XAMPP)

## Local Setup Guide (XAMPP)

1. **Move Project to `htdocs`**
   Place the project folder inside your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\Romeros-Party-Needs.PHP`).

2. **Start XAMPP Services**
   Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

3. **Import `db.sql` in phpMyAdmin**
   * Open `http://localhost/phpmyadmin` in your browser.
   * Click the **Import** tab at the top.
   * Select `db_setup/db.sql` from this repository and click **Import** to create the `romeros_party_db` database and its tables (`users` and `inquiries`).

4. **Check Your MySQL Port (`3306` or `3307`)**
   * Look at the **Port(s)** column next to **MySQL** in your XAMPP Control Panel.
   * Open `includes/db.php` and set `$db_port` to match your XAMPP MySQL port:
     ```php
     $db_port = 3307; // Change to 3306 if your XAMPP MySQL uses the default port
     ```

5. **Run the Website**
   Open your browser and visit:
   ```text
   http://localhost/Romeros-Party-Needs.PHP/index.php
   ```