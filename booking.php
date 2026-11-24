<?php
require_once 'includes/db.php';
require_once 'includes/booking-functions.php';

require_login();

$ref = strtoupper(trim($_GET['ref'] ?? ''));
$row = db_one($conn, "SELECT id, user_id FROM bookings WHERE reference = ?", "s", [$ref]);

// Customers only see their own bookings (admins can see all).
if (!$row || ((int) $row['user_id'] !== current_user_id() && !is_admin())) {
    render_error_page(404, 'Booking not found', 'Check the booking reference, or find it under My bookings.');
}

$booking = get_booking_details($conn, (int) $row['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    require_csrf();
    if ((int) $booking['user_id'] === current_user_id() && customer_can_cancel($booking)) {
        db_exec($conn, "UPDATE bookings SET status = 'cancelled' WHERE id = ?", "i", [$booking['id']]);
        add_booking_log($conn, (int) $booking['id'], 'cancelled', 'Cancelled by customer', current_user_id());
        // Appointments for a cancelled event aren't needed any more.
        db_exec($conn, "UPDATE appointments SET status = 'cancelled' WHERE booking_id = ? AND status = 'scheduled'", "i", [$booking['id']]);
        flash('success', "Booking {$booking['reference']} was cancelled. The items have been released.");
    } else {
        flash('error', 'This booking can no longer be cancelled online. Please contact us.');
    }
    redirect('booking.php?ref=' . urlencode($booking['reference']));
}

$isActive = in_array($booking['status'], ['pending', 'confirmed'], true);
$downPayment = round($booking['total_amount'] * DOWN_PAYMENT_RATE, 2);

$pageTitle = "Booking {$booking['reference']} | Romero's Party Needs";
$activePage = "account";
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <p class="page-eyebrow"><a href="account.php">&larr; My bookings</a></p>
    <h1 class="page-title">Booking <?php echo e($booking['reference']); ?></h1>
    <p class="page-sub"><?php echo e($booking['event_type']); ?> on <?php echo fmt_date($booking['event_date'], 'l, F j, Y'); ?></p>
  </div>
</header>

<section class="container detail-page">
  <div class="detail-status-bar">
    <div>
      <?php echo status_badge($booking['status']); ?>
      <?php echo status_badge($booking['payment_status']); ?>
    </div>
    <div class="detail-actions no-print">
      <button type="button" class="btn-ghost" onclick="window.print()">Print booking slip</button>
      <?php if ((int) $booking['user_id'] === current_user_id() && $booking['status'] === 'pending' && $booking['event_date'] >= today()): ?>
        <a class="btn-ghost" href="book.php?edit=<?php echo urlencode($booking['reference']); ?>">Edit booking</a>
      <?php endif; ?>
      <?php if ($isActive): ?>
        <a class="btn-ghost" href="appointment.php?booking=<?php echo urlencode($booking['reference']); ?>">Schedule a visit</a>
      <?php endif; ?>
      <?php if ((int) $booking['user_id'] === current_user_id() && customer_can_cancel($booking)): ?>
        <form method="POST" class="inline-form" data-confirm="Cancel booking <?php echo e($booking['reference']); ?>? This releases all reserved items.">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="cancel" />
          <button type="submit" class="btn-danger-link">Cancel booking</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($booking['status'] === 'pending'): ?>
    <p class="notice">We're reviewing your booking and will confirm it soon. Your items are held for this date in the meantime. You can still <a href="book.php?edit=<?php echo urlencode($booking['reference']); ?>">edit it</a> until it's confirmed.</p>
  <?php elseif ($booking['status'] === 'confirmed' && $booking['payment_status'] === 'unpaid'): ?>
    <p class="notice">Confirmed! A down payment of <strong><?php echo peso($downPayment); ?></strong> secures your booking. Our team will message you the payment details.</p>
  <?php endif; ?>

  <div class="detail-grid">
    <div class="panel">
      <h2 class="panel-title">Event &amp; setup schedule</h2>
      <dl class="detail-list">
        <div><dt>Date</dt><dd><?php echo fmt_date($booking['event_date'], 'l, F j, Y'); ?></dd></div>
        <div><dt>Crew arrives for setup</dt><dd><?php echo fmt_time($booking['setup_time']); ?></dd></div>
        <div><dt>Event time</dt><dd><?php echo fmt_time($booking['event_start']); ?> – <?php echo fmt_time($booking['event_end']); ?></dd></div>
        <div><dt>Pickup</dt><dd>After <?php echo fmt_time($booking['event_end']); ?></dd></div>
        <div><dt>Venue</dt><dd><?php echo e($booking['venue_address']); ?></dd></div>
        <div><dt>Guests</dt><dd><?php echo (int) $booking['guest_count']; ?></dd></div>
        <?php if ($booking['notes']): ?><div><dt>Your notes</dt><dd><?php echo nl2br(e($booking['notes'])); ?></dd></div><?php endif; ?>
      </dl>
    </div>

    <div class="panel">
      <h2 class="panel-title">Payment</h2>
      <dl class="detail-list">
        <div><dt>Total</dt><dd><strong><?php echo peso($booking['total_amount']); ?></strong></dd></div>
        <div><dt>Paid so far</dt><dd><?php echo peso($booking['amount_paid']); ?></dd></div>
        <div><dt>Balance</dt><dd><?php echo peso($booking['balance']); ?></dd></div>
        <div><dt>Suggested down payment</dt><dd><?php echo peso($downPayment); ?></dd></div>
      </dl>
      <?php if ($booking['payments']): ?>
        <table class="data-table data-table--compact">
          <thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($booking['payments'] as $p): ?>
              <tr>
                <td><?php echo fmt_date($p['paid_at'], 'M j, Y'); ?></td>
                <td><?php echo e(PAYMENT_METHODS[$p['method']] ?? $p['method']); ?></td>
                <td class="num"><?php echo peso($p['amount']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">What's included</h2>
    <table class="data-table">
      <thead><tr><th>Item / service</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($booking['items'] as $it): ?>
          <tr>
            <td><?php echo e($it['name']); ?> <span class="muted">· <?php echo e($it['category_name']); ?></span></td>
            <td class="num"><?php echo (int) $it['quantity']; ?></td>
            <td class="num"><?php echo peso($it['unit_price']); ?></td>
            <td class="num"><?php echo peso($it['subtotal']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($booking['services'] as $sv): ?>
          <tr>
            <td><?php echo e($sv['name']); ?> <span class="muted">· <?php echo e($sv['provider']); ?></span></td>
            <td class="num">1</td>
            <td class="num"><?php echo peso($sv['price']); ?></td>
            <td class="num"><?php echo peso($sv['price']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php if ($booking['delivery_fee'] > 0): ?>
          <tr><td colspan="3">Delivery, setup &amp; pickup</td><td class="num"><?php echo peso($booking['delivery_fee']); ?></td></tr>
        <?php endif; ?>
        <tr class="total-row"><td colspan="3">Total</td><td class="num"><?php echo peso($booking['total_amount']); ?></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="detail-grid">
    <div class="panel">
      <h2 class="panel-title">Appointments for this event</h2>
      <?php if ($booking['appointments']): ?>
        <ul class="plain-list">
          <?php foreach ($booking['appointments'] as $a): ?>
            <li>
              <strong><?php echo e($a['purpose']); ?></strong><br />
              <?php echo fmt_date($a['appointment_date']); ?> at <?php echo fmt_time($a['appointment_time']); ?>
              <?php echo status_badge($a['status']); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">None yet.<?php if ($isActive): ?> <a href="appointment.php?booking=<?php echo urlencode($booking['reference']); ?>">Book an ocular visit or planning consultation</a>.<?php endif; ?></p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title">Timeline</h2>
      <ol class="timeline">
        <?php foreach ($booking['logs'] as $log): ?>
          <li>
            <span class="timeline-when"><?php echo fmt_date($log['created_at'], 'M j, Y g:i A'); ?></span>
            <span class="timeline-what"><?php echo e($log['note'] ?: ucfirst($log['action'])); ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
