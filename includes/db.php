<?php
require_once __DIR__ . '/config.php';

// Default XAMPP MySQL credentials: user "root", no password.
// Port changed from the default 3306 to 3307 due to a port conflict.
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "romeros_party_db";
$db_port = 3307; // Change to 3306 if your XAMPP MySQL uses the default port

// "development" shows PHP errors on the page (handy while coding).
// "production" hides them from visitors and writes them to the server log
// instead. Set $app_env = "production" in db.local.php on the live server.
$app_env = "development";

// Optional per-computer override (not committed to git) so each groupmate
// can use their own port without editing this shared file. Copy
// includes/db.local.example.php to includes/db.local.php to use it.
if (file_exists(__DIR__ . '/db.local.php')) {
    require __DIR__ . '/db.local.php';
}

error_reporting(E_ALL);
ini_set('display_errors', $app_env === 'production' ? '0' : '1');
ini_set('log_errors', '1');
define('APP_ENV', $app_env);

require_once __DIR__ . '/functions.php';

// Turn off mysqli's exception mode for the connect call only, so a wrong
// port shows our friendly message instead of a fatal error.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    http_response_code(500);
    error_log('Database connection failed: ' . mysqli_connect_error());
    // Never show connection details to visitors on the live site.
    $err = APP_ENV === 'production' ? 'Please try again in a few minutes.' : htmlspecialchars(mysqli_connect_error());
    die("<div style='font-family:sans-serif;max-width:640px;margin:3rem auto;line-height:1.6'>
        <h2>Database connection failed</h2>
        <p><code>$err</code></p>
        <ol>
          <li>Is <b>MySQL</b> started in the XAMPP Control Panel?</li>
          <li>Does <code>\$db_port</code> in <code>includes/db.php</code> match the port XAMPP shows for MySQL (3306 or 3307)?</li>
          <li>Did you import <code>db_setup/db.sql</code> in phpMyAdmin?</li>
        </ol></div>");
}

mysqli_set_charset($conn, 'utf8mb4');
// From here on, a failed query throws an exception instead of failing
// silently, which makes bugs much easier to spot during development.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Make sure the logged-in user still exists, is active, and has the role
// we think they have (see functions.php).
refresh_session_user($conn);
