<?php
// GET api/calendar.php?month=2026-12
// Day-by-day status for the month calendar on the booking page:
// open / limited / full / blocked / unavailable (too soon or past).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';

header('Content-Type: application/json');
// Always fresh (stock changes as people book), and release the session lock
// right away: this endpoint only reads the session, so the page's other
// requests don't have to wait for it.
header('Cache-Control: no-store');
session_write_close();

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || !checkdate((int) $m[2], 1, (int) $m[1])) {
    http_response_code(400);
    echo json_encode(['error' => 'Use month=YYYY-MM']);
    exit;
}

$days = get_month_overview($conn, (int) $m[1], (int) $m[2]);

// Don't reveal why a date is blocked or exactly how many events are on
// it to the public — just whether it can be booked.
$public = [];
foreach ($days as $date => $info) {
    $public[$date] = $info['state'];
}

echo json_encode([
    'month' => $month,
    'label' => date('F Y', strtotime($month . '-01')),
    'first_weekday' => (int) date('w', strtotime($month . '-01')),
    'days' => $public,
]);
