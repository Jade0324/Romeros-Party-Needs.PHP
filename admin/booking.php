<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$booking = get_booking_details($conn, $id);
if (!$booking) {
    flash('error', 'Booking not found.');
    redirect('admin/bookings.php');
}
$adminId = current_user_id();
$back = "admin/booking.php?id=$id";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // ---- Status changes -------------------------------------------------
    if ($action === 'status') {
        $to = $_POST['to'] ?? '';
        $note = post_str('note');
        $from = $booking['status'];
        $allowed = [
            'confirmed' => ['pending'],
            'completed' => ['confirmed'],
            'cancelled' => ['pending', 'confirmed'],
            'pending'   => ['cancelled'], // "restore"
        ];

        if (!isset($allowed[$to]) || !in_array($from, $allowed[$to], true)) {
            flash('error', "Can't change a $from booking to $to.");
        } elseif ($to === 'completed' && $booking['event_date'] > today()) {
            flash('error', "You can only mark it completed on or after the event date.");
        } else {
            mysqli_begin_transaction($conn);
            $errors = [];
            if ($to === 'pending') {
                // Restoring puts its items back on hold, so make sure they're still free.
                db_all($conn, "SELECT id FROM bookings WHERE event_date = ? FOR UPDATE", "s", [$booking['event_date']]);
                $errors = booking_fit_errors($conn, $booking, $booking['event_date']);
            }
            if ($errors) {
                mysqli_rollback($conn);
                flash('error', 'Cannot restore: ' . implode(' ', $errors));
            } else {
                db_exec($conn, "UPDATE bookings SET status = ? WHERE id = ?", "si", [$to, $id]);
                if ($to === 'cancelled') {
                    db_exec($conn, "UPDATE appointments SET status = 'cancelled' WHERE booking_id = ? AND status = 'scheduled'", "i", [$id]);
                }
                $labels = ['confirmed' => 'Confirmed', 'completed' => 'Marked as completed', 'cancelled' => 'Cancelled', 'pending' => 'Restored to pending'];
                add_booking_log($conn, $id, $to, $labels[$to] . ' by admin' . ($note ? " — $note" : ''), $adminId);
                mysqli_commit($conn);
                flash('success', "Booking {$booking['reference']}: {$labels[$to]}.");
            }
        }
        redirect($back);
    }

    // ---- Record a payment -----------------------------------------------
    if ($action === 'payment') {
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        $method = $_POST['method'] ?? '';
        $refNo = post_str('reference_no');
        $paidOn = clean_date(post_str('paid_at')) ?: today();
        $pnote = post_str('payment_note');

        if ($booking['status'] === 'cancelled') {
            flash('error', 'Cancelled bookings can\'t take payments.');
        } elseif ($amount <= 0 || $amount > $booking['balance'] + 0.001) {
            flash('error', 'Enter an amount between ' . peso(0.01) . ' and the balance of ' . peso($booking['balance']) . '.');
        } elseif (!isset(PAYMENT_METHODS[$method])) {
            flash('error', 'Choose a payment method.');
        } else {
            $paidAt = $paidOn . ' ' . date('H:i:s');
            db_exec(
                $conn,
                "INSERT INTO payments (booking_id, amount, method, reference_no, notes, paid_at, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
                "idssssi",
                [$id, $amount, $method, $refNo ?: null, $pnote ?: null, $paidAt, $adminId]
            );
            add_booking_log($conn, $id, 'payment', 'Payment of ' . number_format($amount, 2) . ' received (' . PAYMENT_METHODS[$method] . ')', $adminId);
            flash('success', 'Payment of ' . peso($amount) . ' recorded.');
        }
        redirect($back);
    }

    // ---- Reschedule / edit event details ---------------------------------
    if ($action === 'schedule') {
        $newDate = clean_date(post_str('event_date'));
        $setup = clean_time(post_str('setup_time'));
        $start = clean_time(post_str('event_start'));
        $end = clean_time(post_str('event_end'));
        $venue = post_str('venue_address');
        $guests = (int) ($_POST['guest_count'] ?? 0);

        if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
            flash('error', 'Only pending or confirmed bookings can be rescheduled.');
        } elseif (!$newDate || !$setup || !$start || !$end || $venue === '' || $guests < 1) {
            flash('error', 'Please fill in every schedule field.');
        } elseif (!($setup < $start && $start < $end)) {
            flash('error', 'Setup must be before the start, and the start before the end.');
        } else {
            mysqli_begin_transaction($conn);
            $errors = [];
            if ($newDate !== $booking['event_date']) {
                db_all($conn, "SELECT id FROM bookings WHERE event_date = ? FOR UPDATE", "s", [$newDate]);
                $errors = booking_fit_errors($conn, $booking, $newDate);
            }
            if ($errors) {
                mysqli_rollback($conn);
                flash('error', 'Cannot move to ' . fmt_date($newDate) . ': ' . implode(' ', $errors));
            } else {
                db_exec(
                    $conn,
                    "UPDATE bookings SET event_date = ?, setup_time = ?, event_start = ?, event_end = ?, venue_address = ?, guest_count = ? WHERE id = ?",
                    "sssssii",
                    [$newDate, $setup, $start, $end, $venue, $guests, $id]
                );
                $msg = $newDate !== $booking['event_date']
                    ? 'Rescheduled from ' . fmt_date($booking['event_date']) . ' to ' . fmt_date($newDate)
                    : 'Event schedule/venue updated';
                add_booking_log($conn, $id, 'updated', $msg, $adminId);
                mysqli_commit($conn);
                flash('success', $msg . '.');
            }
        }
        redirect($back);
    }

    // ---- Internal notes -------------------------------------------------
    if ($action === 'admin_notes') {
        db_exec($conn, "UPDATE bookings SET admin_notes = ? WHERE id = ?", "si", [post_str('admin_notes') ?: null, $id]);
        flash('success', 'Notes saved.');
        redirect($back);
    }
}

$isActive = in_array($booking['status'], ['pending', 'confirmed'], true);
$otherBookingsThatDay = db_all(
    $conn,
    "SELECT id, reference, setup_time, status FROM bookings
     WHERE event_date = ? AND id <> ? AND status IN ('pending','confirmed') ORDER BY setup_time",
    "si",
    [$booking['event_date'], $id]
);

$pageTitle = "{$booking['reference']} | Admin";
$activePage = "admin";
$adminPage = "bookings";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page detail-page">
  <p class="small"><a href="bookings.php">&larr; All bookings</a></p>
  <div class="admin-head">
    <div>
      <h1 class="admin-title"><?php echo e($booking['reference']); ?> <?php echo status_badge($booking['status']); ?> <?php echo status_badge($booking['payment_status']); ?></h1>
      <p class="muted"><?php echo e($booking['event_type']); ?> · <?php echo fmt_date($booking['event_date'], 'l, F j, Y'); ?> · booked <?php echo fmt_date($booking['created_at'], 'M j, Y'); ?></p>
    </div>
    <div class="detail-actions">
      <a class="btn-ghost" href="<?php echo url('booking.php?ref=' . urlencode($booking['reference'])); ?>">Customer view</a>
      <a class="btn-ghost" href="calendar.php?day=<?php echo e($booking['event_date']); ?>">Day plan</a>
      <?php if (in_array($booking['status'], ['pending', 'confirmed'], true) && $booking['event_date'] >= today()): ?>
        <a class="btn-ghost" href="<?php echo url('book.php?edit=' . urlencode($booking['reference'])); ?>">Edit items &amp; services</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Status buttons — only the moves that make sense from the current status -->
  <form method="POST" class="status-actions panel">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="status" />
    <input type="text" name="note" placeholder="Optional note (shown in the customer's timeline)" maxlength="200" />
    <?php if ($booking['status'] === 'pending'): ?>
      <button name="to" value="confirmed" class="btn-primary">Confirm booking</button>
    <?php endif; ?>
    <?php if ($booking['status'] === 'confirmed'): ?>
      <button name="to" value="completed" class="btn-primary" <?php echo $booking['event_date'] > today() ? 'disabled title="Available on/after the event date"' : ''; ?>>Mark completed</button>
    <?php endif; ?>
    <?php if ($isActive): ?>
      <button name="to" value="cancelled" class="btn-danger-link" data-confirm="Cancel this booking? Its items and service slots will be released.">Cancel booking</button>
    <?php endif; ?>
    <?php if ($booking['status'] === 'cancelled'): ?>
      <button name="to" value="pending" class="btn-ghost">Restore booking</button>
    <?php endif; ?>
  </form>

  <div class="detail-grid">
    <div class="panel">
      <h2 class="panel-title">Customer</h2>
      <dl class="detail-list">
        <div><dt>Name</dt><dd><a href="bookings.php?user=<?php echo (int) $booking['user_id']; ?>"><?php echo e($booking['full_name']); ?></a></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?php echo e($booking['email']); ?>"><?php echo e($booking['email']); ?></a></dd></div>
        <div><dt>Phone</dt><dd><a href="tel:<?php echo e($booking['phone']); ?>"><?php echo e($booking['phone']); ?></a></dd></div>
        <?php if ($booking['notes']): ?><div><dt>Customer notes</dt><dd><?php echo nl2br(e($booking['notes'])); ?></dd></div><?php endif; ?>
      </dl>
    </div>

    <div class="panel">
      <h2 class="panel-title">Schedule &amp; venue</h2>
      <form method="POST" class="compact-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="schedule" />
        <div class="time-row">
          <div class="form-row"><label>Event date</label><input type="date" name="event_date" value="<?php echo e($booking['event_date']); ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
          <div class="form-row"><label>Guests</label><input type="number" name="guest_count" min="1" value="<?php echo (int) $booking['guest_count']; ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
        </div>
        <div class="time-row">
          <div class="form-row"><label>Setup</label><input type="time" name="setup_time" value="<?php echo e(substr($booking['setup_time'], 0, 5)); ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
          <div class="form-row"><label>Start</label><input type="time" name="event_start" value="<?php echo e(substr($booking['event_start'], 0, 5)); ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
          <div class="form-row"><label>End</label><input type="time" name="event_end" value="<?php echo e(substr($booking['event_end'], 0, 5)); ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
        </div>
        <div class="form-row"><label>Venue</label><input type="text" name="venue_address" value="<?php echo e($booking['venue_address']); ?>" <?php echo $isActive ? '' : 'disabled'; ?> /></div>
        <?php if ($isActive): ?>
          <button type="submit" class="btn-ghost">Save schedule</button>
          <p class="label-hint">Changing the date re-checks stock and crew capacity on the new date.</p>
        <?php endif; ?>
      </form>
      <?php if ($otherBookingsThatDay): ?>
        <p class="small muted">Also on this date:
          <?php foreach ($otherBookingsThatDay as $o): ?>
            <a href="booking.php?id=<?php echo (int) $o['id']; ?>"><?php echo e($o['reference']); ?></a> (setup <?php echo fmt_time($o['setup_time']); ?>)
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">Items &amp; services</h2>
    <table class="data-table">
      <thead><tr><th>Item / service</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($booking['items'] as $it): ?>
          <tr><td><?php echo e($it['name']); ?> <span class="muted">· <?php echo e($it['category_name']); ?></span></td><td class="num"><?php echo (int) $it['quantity']; ?></td><td class="num"><?php echo peso($it['unit_price']); ?></td><td class="num"><?php echo peso($it['subtotal']); ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($booking['services'] as $sv): ?>
          <tr><td><?php echo e($sv['name']); ?> <span class="muted">· <?php echo e($sv['provider']); ?></span></td><td class="num">1</td><td class="num"><?php echo peso($sv['price']); ?></td><td class="num"><?php echo peso($sv['price']); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3">Rentals</td><td class="num"><?php echo peso($booking['items_total']); ?></td></tr>
        <tr><td colspan="3">Services</td><td class="num"><?php echo peso($booking['services_total']); ?></td></tr>
        <tr><td colspan="3">Delivery, setup &amp; pickup</td><td class="num"><?php echo peso($booking['delivery_fee']); ?></td></tr>
        <tr class="total-row"><td colspan="3">Total</td><td class="num"><?php echo peso($booking['total_amount']); ?></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="detail-grid">
    <div class="panel">
      <h2 class="panel-title">Payments <span class="muted small">paid <?php echo peso($booking['amount_paid']); ?> · balance <?php echo peso($booking['balance']); ?></span></h2>
      <?php if ($booking['payments']): ?>
        <table class="data-table data-table--compact">
          <thead><tr><th>Date</th><th>Method</th><th>Ref #</th><th class="num">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($booking['payments'] as $p): ?>
              <tr><td><?php echo fmt_date($p['paid_at'], 'M j, Y'); ?></td><td><?php echo e(PAYMENT_METHODS[$p['method']] ?? $p['method']); ?></td><td><?php echo e($p['reference_no']); ?></td><td class="num"><?php echo peso($p['amount']); ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($booking['status'] !== 'cancelled' && $booking['balance'] > 0): ?>
        <form method="POST" class="compact-form payment-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="payment" />
          <div class="time-row">
            <div class="form-row"><label>Amount</label><input type="number" name="amount" step="0.01" min="0.01" max="<?php echo e($booking['balance']); ?>" value="<?php echo e(number_format($booking['amount_paid'] > 0 ? $booking['balance'] : min($booking['balance'], round($booking['total_amount'] * DOWN_PAYMENT_RATE, 2)), 2, '.', '')); ?>" required /></div>
            <div class="form-row"><label>Method</label>
              <select name="method"><?php foreach (PAYMENT_METHODS as $k => $label): ?><option value="<?php echo e($k); ?>"><?php echo e($label); ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row"><label>Date</label><input type="date" name="paid_at" value="<?php echo today(); ?>" /></div>
          </div>
          <div class="time-row">
            <div class="form-row"><label>Reference #</label><input type="text" name="reference_no" maxlength="100" /></div>
            <div class="form-row"><label>Note</label><input type="text" name="payment_note" maxlength="255" placeholder="e.g. Down payment" /></div>
          </div>
          <button type="submit" class="btn-primary">Record payment</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title">Internal notes <span class="muted small">(admins only)</span></h2>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_notes" />
        <div class="form-row"><textarea name="admin_notes" rows="4"><?php echo e($booking['admin_notes']); ?></textarea></div>
        <button type="submit" class="btn-ghost">Save notes</button>
      </form>

      <h2 class="panel-title" style="margin-top:1.5rem">Appointments</h2>
      <?php if ($booking['appointments']): ?>
        <ul class="plain-list">
          <?php foreach ($booking['appointments'] as $a): ?>
            <li><?php echo e($a['purpose']); ?> — <?php echo fmt_date($a['appointment_date'], 'M j'); ?>, <?php echo fmt_time($a['appointment_time']); ?> <?php echo status_badge($a['status']); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted small">None linked to this booking.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">Timeline</h2>
    <ol class="timeline">
      <?php foreach ($booking['logs'] as $log): ?>
        <li>
          <span class="timeline-when"><?php echo fmt_date($log['created_at'], 'M j, Y g:i A'); ?></span>
          <span class="timeline-what"><?php echo e($log['note'] ?: ucfirst($log['action'])); ?> <?php if ($log['by_name']): ?><span class="muted small">· <?php echo e($log['by_name']); ?></span><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
