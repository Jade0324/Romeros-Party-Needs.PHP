<?php
// GET api/availability.php?date=2026-12-04
// What's free on one date: each rental item's remaining quantity, each
// service's remaining slots, and whether the date itself can be booked.
// Used by book.php and services.php (jQuery $.getJSON).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';

header('Content-Type: application/json');
// Always fresh (stock changes as people book), and release the session lock
// right away: this endpoint only reads the session, so the page's other
// requests don't have to wait for it.
header('Cache-Control: no-store');
session_write_close();

$date = clean_date($_GET['date'] ?? '');
if (!$date) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid date.']);
    exit;
}

// When a customer edits a booking, leave that booking's own items out of the
// count — but only if it really is their booking (or they're an admin).
$excludeId = 0;
$keepDate = null;
if (!empty($_GET['exclude']) && is_logged_in()) {
    $b = db_one($conn, "SELECT id, user_id, event_date FROM bookings WHERE reference = ?", "s", [strtoupper($_GET['exclude'])]);
    if ($b && ((int) $b['user_id'] === current_user_id() || is_admin())) {
        $excludeId = (int) $b['id'];
        $keepDate = $b['event_date'];
    }
}

$items = [];
foreach (get_item_availability($conn, $date, $excludeId) as $id => $row) {
    $items[$id] = ['available' => $row['available'], 'total' => (int) $row['total_quantity']];
}

$services = [];
foreach (get_service_availability($conn, $date, $excludeId) as $id => $row) {
    $services[$id] = ['remaining' => $row['remaining'], 'capacity' => (int) $row['daily_capacity']];
}

// Keeping the booking's current date is always allowed when editing.
$dateError = $date === $keepDate ? null : event_date_error($conn, $date, $excludeId);

echo json_encode([
    'ok' => $dateError === null,
    'date' => $date,
    'date_label' => fmt_date($date),
    'error' => $dateError,
    'bookings_on_date' => count_active_bookings($conn, $date, $excludeId),
    'max_bookings' => MAX_BOOKINGS_PER_DAY,
    'items' => $items,
    'services' => $services,
]);
