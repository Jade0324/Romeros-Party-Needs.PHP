<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Default XAMPP MySQL credentials: user "root", no password.
// Port changed from the default 3306 to 3307 due to a port conflict.
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "romeros_party_db";
$db_port = 3307;

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

?>