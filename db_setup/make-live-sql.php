<?php
// ==========================================================================
// Builds db_setup/db-live.sql from db_setup/db.sql.
//
// Why: on most web hosts you can't run "CREATE DATABASE" — the host creates
// the database for you (e.g. if0_12345678_romeros) and you import into it.
// db-live.sql is the same as db.sql minus the CREATE DATABASE / USE lines.
//
// Run after changing db.sql:
//   C:\xampp\php\php.exe db_setup\make-live-sql.php
// ==========================================================================

$source = __DIR__ . '/db.sql';
$target = __DIR__ . '/db-live.sql';

$sql = file_get_contents($source);
$live = preg_replace('/^CREATE DATABASE[^;]+;\s*^USE [^;]+;\s*/ms', '', $sql, 1, $count);
if ($count !== 1) {
    exit("Couldn't find the CREATE DATABASE / USE lines in db.sql — check the file.\n");
}

$header = "-- ==========================================================================\n"
    . "-- GENERATED FILE — do not edit. Edit db.sql, then run:\n"
    . "--   php db_setup/make-live-sql.php\n"
    . "--\n"
    . "-- For live hosting: in phpMyAdmin, click YOUR database on the left first,\n"
    . "-- then Import this file. (Same tables + demo data as db.sql.)\n"
    . "-- ==========================================================================\n\n";

file_put_contents($target, $header . $live);
echo "Wrote $target\n";
