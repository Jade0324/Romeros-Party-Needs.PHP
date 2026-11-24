<?php
// GET api/slots.php?date=2026-12-01
// Which appointment time slots are still free on a date.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';

header('Content-Type: application/json');
// Always fresh (stock changes as people book), and release the session lock
// right away: this endpoint only reads the session, so the page's other
// requests don't have to wait for it.
header('Cache-Control: no-store');
session_write_close();

$date = clean_date($_GET['date'] ?? '');
$error = appointment_date_error($conn, $date);

$slots = [];
if ($error === null) {
    foreach (get_appointment_slots($conn, $date) as $time => $free) {
        $slots[] = ['time' => $time, 'label' => fmt_time($time), 'free' => $free];
    }
}

echo json_encode(['ok' => $error === null, 'error' => $error, 'slots' => $slots]);
