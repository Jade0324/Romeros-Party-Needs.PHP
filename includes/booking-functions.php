<?php
// ==========================================================================
// The booking engine. Everything that answers "can this be booked on this
// date?" lives here, so the booking form, the JSON API, the services page
// and the admin pages all use the exact same rules.
//
// Key idea: availability is never stored. For any date it's calculated as
//   total we own  -  what pending + confirmed bookings on that date hold
// So cancelling a booking instantly frees its items; no stock to "return".
// ==========================================================================

// Statuses that hold inventory / a crew slot on their event date.
const ACTIVE_STATUSES_SQL = "('pending','confirmed')";

function get_blocked_reason(mysqli $conn, string $date): ?string
{
    $row = db_one($conn, "SELECT reason FROM blocked_dates WHERE blocked_date = ?", "s", [$date]);
    return $row ? ($row['reason'] ?: 'Closed on this date') : null;
}

function count_active_bookings(mysqli $conn, string $date, int $excludeBookingId = 0): int
{
    return (int) db_value(
        $conn,
        "SELECT COUNT(*) FROM bookings
         WHERE event_date = ? AND status IN " . ACTIVE_STATUSES_SQL . " AND id <> ?",
        "si",
        [$date, $excludeBookingId]
    );
}

/**
 * Checks the date-level rules (lead time, blocked dates, crew capacity).
 * Returns an error message, or null if the date can take a new booking.
 */
function event_date_error(mysqli $conn, ?string $date, int $excludeBookingId = 0): ?string
{
    if (!$date) {
        return 'Please choose a valid event date.';
    }
    $earliest = date('Y-m-d', strtotime('+' . MIN_LEAD_DAYS . ' days'));
    $latest = date('Y-m-d', strtotime('+' . MAX_ADVANCE_DAYS . ' days'));

    if ($date < $earliest) {
        return 'Bookings need at least ' . MIN_LEAD_DAYS . ' days\' notice. The earliest date you can book is ' . fmt_date($earliest) . '.';
    }
    if ($date > $latest) {
        return 'We only take bookings up to one year ahead.';
    }
    if ($reason = get_blocked_reason($conn, $date)) {
        return 'Sorry, we\'re not taking bookings on ' . fmt_date($date) . ' (' . $reason . ').';
    }
    if (count_active_bookings($conn, $date, $excludeBookingId) >= MAX_BOOKINGS_PER_DAY) {
        return 'Our crew is fully booked on ' . fmt_date($date) . '. Please pick another date.';
    }
    return null;
}

/**
 * Every rental item with how many are free on $date.
 * Returns [item_id => row] where row has: total_quantity, reserved, available.
 */
function get_item_availability(mysqli $conn, ?string $date, int $excludeBookingId = 0, bool $activeOnly = true): array
{
    // The date's active bookings are found once (via the event_date index)
    // and summed per item, then joined to the item list. Faster than running
    // a separate subquery for every item.
    $sql = "SELECT i.*, c.name AS category_name, c.slug AS category_slug,
                   COALESCE(r.reserved, 0) AS reserved
            FROM inventory_items i
            JOIN categories c ON c.id = i.category_id
            LEFT JOIN (
                SELECT bi.item_id, SUM(bi.quantity) AS reserved
                FROM bookings b
                JOIN booking_items bi ON bi.booking_id = b.id
                WHERE b.event_date = ?
                  AND b.status IN " . ACTIVE_STATUSES_SQL . "
                  AND b.id <> ?
                GROUP BY bi.item_id
            ) r ON r.item_id = i.id
            " . ($activeOnly ? "WHERE i.is_active = 1" : "") . "
            ORDER BY c.sort_order, i.id";

    $items = [];
    foreach (db_all($conn, $sql, "si", [$date ?? '0000-00-00', $excludeBookingId]) as $row) {
        $row['reserved'] = (int) $row['reserved'];
        $row['available'] = max(0, (int) $row['total_quantity'] - $row['reserved']);
        $items[(int) $row['id']] = $row;
    }
    return $items;
}

/**
 * Every service with how many of its daily slots are used on $date.
 * Returns [service_id => row] with: daily_capacity, used, remaining.
 */
function get_service_availability(mysqli $conn, ?string $date, int $excludeBookingId = 0, bool $activeOnly = true): array
{
    $sql = "SELECT s.*, COALESCE(u.used, 0) AS used
            FROM services s
            LEFT JOIN (
                SELECT bs.service_id, COUNT(*) AS used
                FROM bookings b
                JOIN booking_services bs ON bs.booking_id = b.id
                WHERE b.event_date = ?
                  AND b.status IN " . ACTIVE_STATUSES_SQL . "
                  AND b.id <> ?
                GROUP BY bs.service_id
            ) u ON u.service_id = s.id
            " . ($activeOnly ? "WHERE s.is_active = 1" : "") . "
            ORDER BY s.price";

    $services = [];
    foreach (db_all($conn, $sql, "si", [$date ?? '0000-00-00', $excludeBookingId]) as $row) {
        $row['used'] = (int) $row['used'];
        $row['remaining'] = max(0, (int) $row['daily_capacity'] - $row['used']);
        $services[(int) $row['id']] = $row;
    }
    return $services;
}

/** Status of each day in a month, for the availability calendars. */
function get_month_overview(mysqli $conn, int $year, int $month): array
{
    $first = sprintf('%04d-%02d-01', $year, $month);
    $last = date('Y-m-t', strtotime($first));

    $counts = [];
    foreach (db_all(
        $conn,
        "SELECT event_date, COUNT(*) AS n FROM bookings
         WHERE event_date BETWEEN ? AND ? AND status IN " . ACTIVE_STATUSES_SQL . "
         GROUP BY event_date",
        "ss",
        [$first, $last]
    ) as $r) {
        $counts[$r['event_date']] = (int) $r['n'];
    }

    $blocked = [];
    foreach (db_all($conn, "SELECT blocked_date, reason FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?", "ss", [$first, $last]) as $r) {
        $blocked[$r['blocked_date']] = $r['reason'];
    }

    $earliest = date('Y-m-d', strtotime('+' . MIN_LEAD_DAYS . ' days'));
    $days = [];
    for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        $n = $counts[$d] ?? 0;
        if ($d < $earliest) {
            $state = 'unavailable';
        } elseif (isset($blocked[$d])) {
            $state = 'blocked';
        } elseif ($n >= MAX_BOOKINGS_PER_DAY) {
            $state = 'full';
        } elseif ($n >= MAX_BOOKINGS_PER_DAY - 1) {
            $state = 'limited';
        } else {
            $state = 'open';
        }
        $days[$d] = ['state' => $state, 'bookings' => $n, 'reason' => $blocked[$d] ?? null];
    }
    return $days;
}

// --------------------------------------------------------------------------
// Booking records
// --------------------------------------------------------------------------

function generate_booking_reference(mysqli $conn): string
{
    // No 0/O/1/I so references are easy to read over the phone.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'RPN-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (db_value($conn, "SELECT COUNT(*) FROM bookings WHERE reference = ?", "s", [$code]) > 0);
    return $code;
}

function add_booking_log(mysqli $conn, int $bookingId, string $action, string $note, ?int $userId): void
{
    db_exec(
        $conn,
        "INSERT INTO booking_logs (booking_id, action, note, created_by) VALUES (?, ?, ?, ?)",
        "issi",
        [$bookingId, $action, $note, $userId]
    );
}

function booking_amount_paid(mysqli $conn, int $bookingId): float
{
    return (float) db_value($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = ?", "i", [$bookingId]);
}

function payment_status(float $total, float $paid): string
{
    if ($paid <= 0) {
        return 'unpaid';
    }
    return $paid + 0.009 >= $total ? 'paid' : 'partial';
}

/** A booking plus everything attached to it, for the detail pages. */
function get_booking_details(mysqli $conn, int $bookingId): ?array
{
    $booking = db_one(
        $conn,
        "SELECT b.*, u.full_name, u.email, u.phone
         FROM bookings b JOIN users u ON u.id = b.user_id
         WHERE b.id = ?",
        "i",
        [$bookingId]
    );
    if (!$booking) {
        return null;
    }

    $booking['items'] = db_all(
        $conn,
        "SELECT bi.*, i.name, i.unit, c.name AS category_name
         FROM booking_items bi
         JOIN inventory_items i ON i.id = bi.item_id
         JOIN categories c ON c.id = i.category_id
         WHERE bi.booking_id = ? ORDER BY c.sort_order, i.name",
        "i",
        [$bookingId]
    );
    $booking['services'] = db_all(
        $conn,
        "SELECT bs.*, s.name, s.provider FROM booking_services bs
         JOIN services s ON s.id = bs.service_id WHERE bs.booking_id = ?",
        "i",
        [$bookingId]
    );
    $booking['payments'] = db_all(
        $conn,
        "SELECT * FROM payments WHERE booking_id = ? ORDER BY paid_at",
        "i",
        [$bookingId]
    );
    $booking['logs'] = db_all(
        $conn,
        "SELECT l.*, u.full_name AS by_name FROM booking_logs l
         LEFT JOIN users u ON u.id = l.created_by
         WHERE l.booking_id = ? ORDER BY l.created_at, l.id",
        "i",
        [$bookingId]
    );
    $booking['appointments'] = db_all(
        $conn,
        "SELECT * FROM appointments WHERE booking_id = ? ORDER BY appointment_date, appointment_time",
        "i",
        [$bookingId]
    );

    $booking['amount_paid'] = array_sum(array_column($booking['payments'], 'amount'));
    $booking['balance'] = max(0, (float) $booking['total_amount'] - $booking['amount_paid']);
    $booking['payment_status'] = payment_status((float) $booking['total_amount'], $booking['amount_paid']);
    return $booking;
}

/**
 * Would this existing booking (all its items + services) fit on $date?
 * Used when an admin reschedules a booking or restores a cancelled one.
 * The booking's own reservation is excluded so it doesn't block itself.
 * Admins skip the customer lead-time rule but not stock/crew limits.
 */
function booking_fit_errors(mysqli $conn, array $booking, string $date): array
{
    $errors = [];
    $id = (int) $booking['id'];

    if ($date < today()) {
        $errors[] = 'That date is in the past.';
    }
    if ($reason = get_blocked_reason($conn, $date)) {
        $errors[] = fmt_date($date) . " is blocked ($reason). Unblock it in the calendar first.";
    }
    if (count_active_bookings($conn, $date, $id) >= MAX_BOOKINGS_PER_DAY) {
        $errors[] = 'The crew already has ' . MAX_BOOKINGS_PER_DAY . ' bookings on ' . fmt_date($date) . '.';
    }

    $items = get_item_availability($conn, $date, $id, false);
    foreach ($booking['items'] as $it) {
        $free = $items[(int) $it['item_id']]['available'] ?? 0;
        if ((int) $it['quantity'] > $free) {
            $errors[] = "Only $free × {$it['name']} free on " . fmt_date($date) . " (booking needs {$it['quantity']}).";
        }
    }

    $services = get_service_availability($conn, $date, $id, false);
    foreach ($booking['services'] as $sv) {
        if (($services[(int) $sv['service_id']]['remaining'] ?? 0) < 1) {
            $errors[] = "{$sv['name']} is fully booked on " . fmt_date($date) . '.';
        }
    }
    return $errors;
}

/** Customers may cancel pending/confirmed bookings until the cutoff. */
function customer_can_cancel(array $booking): bool
{
    $cutoff = date('Y-m-d', strtotime('+' . CANCEL_CUTOFF_DAYS . ' days'));
    return in_array($booking['status'], ['pending', 'confirmed'], true) && $booking['event_date'] >= $cutoff;
}

// --------------------------------------------------------------------------
// Appointments
// --------------------------------------------------------------------------

/** [ '09:00' => true/false (free?) ] for a date. */
function get_appointment_slots(mysqli $conn, string $date): array
{
    $taken = array_map(
        fn($r) => substr($r['appointment_time'], 0, 5),
        db_all($conn, "SELECT appointment_time FROM appointments WHERE appointment_date = ? AND status = 'scheduled'", "s", [$date])
    );
    $slots = [];
    foreach (APPOINTMENT_SLOTS as $slot) {
        // Same-day slots that already passed aren't bookable.
        $past = $date === today() && $slot <= date('H:i');
        $slots[$slot] = !$past && !in_array($slot, $taken, true);
    }
    return $slots;
}

function appointment_date_error(mysqli $conn, ?string $date): ?string
{
    if (!$date) {
        return 'Please choose a valid date.';
    }
    if ($date <= today()) {
        return 'Appointments can be booked from tomorrow onwards.';
    }
    if ($date > date('Y-m-d', strtotime('+' . MAX_ADVANCE_DAYS . ' days'))) {
        return 'Please choose a date within the next year.';
    }
    if (date('w', strtotime($date)) === '0') {
        return 'We\'re closed for appointments on Sundays.';
    }
    if ($reason = get_blocked_reason($conn, $date)) {
        return 'We\'re closed on ' . fmt_date($date) . ' (' . $reason . ').';
    }
    return null;
}
