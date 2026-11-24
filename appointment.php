<?php
require_once 'includes/db.php';
require_once 'includes/booking-functions.php';

require_login();
$uid = current_user_id();
$errors = [];

// The customer's active bookings, so an appointment can be tied to one.
$myBookings = db_all(
    $conn,
    "SELECT id, reference, event_type, event_date FROM bookings
     WHERE user_id = ? AND status IN ('pending','confirmed') AND event_date >= ?
     ORDER BY event_date",
    "is",
    [$uid, today()]
);
$bookingsById = array_column($myBookings, null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $purpose = post_str('purpose');
    $date = clean_date(post_str('appointment_date'));
    $time = clean_time(post_str('appointment_time'));
    $bookingId = (int) ($_POST['booking_id'] ?? 0) ?: null;
    $notes = post_str('notes');

    if (!in_array($purpose, APPOINTMENT_PURPOSES, true)) $errors[] = 'Please choose what the appointment is for.';
    if ($err = appointment_date_error($conn, $date)) $errors[] = $err;
    if (!$time || !in_array($time, APPOINTMENT_SLOTS, true)) $errors[] = 'Please pick one of the available time slots.';
    if ($bookingId !== null) {
        if (!isset($bookingsById[$bookingId])) {
            $errors[] = 'Please choose one of your active bookings.';
        } elseif ($date && $date > $bookingsById[$bookingId]['event_date']) {
            $errors[] = 'The appointment has to be on or before the event date (' . fmt_date($bookingsById[$bookingId]['event_date']) . ').';
        }
    }

    if (!$errors) {
        mysqli_begin_transaction($conn);
        try {
            // Lock that day's appointments so two people can't take the same slot.
            db_all($conn, "SELECT id FROM appointments WHERE appointment_date = ? FOR UPDATE", "s", [$date]);
            $slots = get_appointment_slots($conn, $date);

            if (empty($slots[$time])) {
                $errors[] = 'Sorry, that slot was just taken. Please pick another time.';
                mysqli_rollback($conn);
            } else {
                db_exec(
                    $conn,
                    "INSERT INTO appointments (user_id, booking_id, purpose, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?, ?, ?)",
                    "iissss",
                    [$uid, $bookingId, $purpose, $date, $time, $notes ?: null]
                );
                if ($bookingId) {
                    add_booking_log($conn, $bookingId, 'appointment', "$purpose scheduled for " . fmt_date($date) . ' ' . fmt_time($time), $uid);
                }
                mysqli_commit($conn);
                flash('success', "$purpose booked for " . fmt_date($date) . ' at ' . fmt_time($time) . '.');
                redirect('account.php');
            }
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $errors[] = 'Something went wrong. Please try again.';
            error_log('Appointment failed: ' . $ex->getMessage());
        }
    }
}

// Pre-select a booking when coming from a booking page (?booking=RPN-XXXX).
$preselect = (int) ($_POST['booking_id'] ?? 0);
if (!$preselect && !empty($_GET['booking'])) {
    foreach ($myBookings as $b) {
        if ($b['reference'] === strtoupper($_GET['booking'])) {
            $preselect = (int) $b['id'];
        }
    }
}

$selectedDate = clean_date($_POST['appointment_date'] ?? '') ?: '';
$selectedTime = $_POST['appointment_time'] ?? '';
$serverSlots = ($selectedDate && !appointment_date_error($conn, $selectedDate)) ? get_appointment_slots($conn, $selectedDate) : [];

$pageTitle = "Book an Appointment | Romero's Party Needs";
$activePage = "appointment";
$pageScripts = ['appointment.js'];
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Book an appointment</h1>
    <p class="page-sub">Meet us before the big day — for a venue ocular, a planning session with our organizers, or to see the items in person.</p>
  </div>
</header>

<section class="contact-section container">
  <?php if ($errors): ?>
    <div class="form-errors" role="alert">
      <ul><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form id="appointmentForm" method="POST" action="appointment.php" novalidate>
    <?php echo csrf_field(); ?>

    <div class="form-row">
      <label for="purpose">What's it for?</label>
      <select id="purpose" name="purpose" required>
        <?php foreach (APPOINTMENT_PURPOSES as $p): ?>
          <option <?php echo ($_POST['purpose'] ?? '') === $p ? 'selected' : ''; ?>><?php echo e($p); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="booking_id">Related booking <span class="label-hint">(optional)</span></label>
      <select id="booking_id" name="booking_id">
        <option value="">Not for a specific booking</option>
        <?php foreach ($myBookings as $b): ?>
          <option value="<?php echo (int) $b['id']; ?>" data-event-date="<?php echo e($b['event_date']); ?>" <?php echo $preselect === (int) $b['id'] ? 'selected' : ''; ?>>
            <?php echo e($b['reference'] . ' — ' . $b['event_type'] . ', ' . fmt_date($b['event_date'], 'M j')); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="appointment_date">Date <span class="label-hint">(Monday–Saturday)</span></label>
      <input type="date" id="appointment_date" name="appointment_date" required
             min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo e($selectedDate); ?>" />
      <span class="date-status" id="slotStatus" aria-live="polite"></span>
    </div>

    <div class="form-row">
      <label>Time</label>
      <div class="slot-grid" id="slotGrid">
        <?php if ($serverSlots): foreach ($serverSlots as $t => $free): ?>
          <label class="slot <?php echo $free ? '' : 'is-taken'; ?>">
            <input type="radio" name="appointment_time" value="<?php echo e($t); ?>" <?php echo $free ? '' : 'disabled'; ?> <?php echo $selectedTime === $t ? 'checked' : ''; ?> />
            <span><?php echo fmt_time($t); ?></span>
          </label>
        <?php endforeach; else: ?>
          <p class="muted">Pick a date to see open times.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-row">
      <label for="notes">Notes <span class="label-hint">(optional)</span></label>
      <textarea id="notes" name="notes" rows="3" maxlength="255"><?php echo old('notes'); ?></textarea>
    </div>

    <button type="submit" class="btn-primary">Book appointment</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>
