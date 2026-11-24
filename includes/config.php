<?php
// ==========================================================================
// Business rules in one place. Change a number here and every page that
// books, prices, or schedules something picks it up.
// ==========================================================================

date_default_timezone_set('Asia/Manila');

define('BUSINESS_NAME', "Romero's Party Needs");
define('CURRENCY', '₱');

// Flat fee added to any booking that includes rental items
// (truck + crew to deliver, set up, and pick up).
define('DELIVERY_FEE', 1000.00);

// Share of the total we ask for to lock in a booking. Display only —
// admins record actual payments on the booking page.
define('DOWN_PAYMENT_RATE', 0.30);

// Our crew can only handle so many setups in a day. Once a date has this
// many pending/confirmed bookings, it's shown as fully booked.
define('MAX_BOOKINGS_PER_DAY', 4);

// Customers can't book an event sooner than this many days from today
// (we need time to prepare items and schedule the crew).
define('MIN_LEAD_DAYS', 3);
define('MAX_ADVANCE_DAYS', 365);

// Logged-in users are signed out after this long without any activity.
define('SESSION_IDLE_MINUTES', 120);

// Login throttling: after this many wrong passwords for the same email (or
// from the same IP) within the window, further attempts are refused.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

// Uploaded photos (categories, items, services).
define('UPLOAD_DIR', 'assets/uploads/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);

// Customers can cancel online up to this many days before the event.
define('CANCEL_CUTOFF_DAYS', 3);

define('EVENT_TYPES', [
    'Birthday', 'Wedding', 'Debut', 'Christening', 'Anniversary',
    'Reunion', 'Corporate event', 'Christmas party', 'Other',
]);

// Appointment slots (one customer per slot). Closed on Sundays.
define('APPOINTMENT_SLOTS', ['09:00', '10:30', '13:00', '14:30', '16:00']);
define('APPOINTMENT_PURPOSES', [
    'Ocular / venue inspection',
    'Planning consultation',
    'Showroom / item viewing',
]);

define('BOOKING_STATUSES', ['pending', 'confirmed', 'completed', 'cancelled']);
define('PAYMENT_METHODS', ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank transfer']);

// --------------------------------------------------------------------------
// BASE_URL — the web path to the project folder, worked out automatically so
// links keep working whatever the folder in htdocs is called, and from pages
// inside /admin or /api.
//   http://localhost/WebDevActivity/admin/index.php  ->  /WebDevActivity/
// --------------------------------------------------------------------------
$__scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$__scriptDir = preg_replace('#/(admin|api)$#', '', $__scriptDir);
define('BASE_URL', $__scriptDir . '/');
unset($__scriptDir);
