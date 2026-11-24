<?php
require_once 'includes/db.php';
require_once 'includes/booking-functions.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = post_str('full_name');
        $phone = post_str('phone');
        if ($name === '' || !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
            flash('error', 'Please enter your name and a valid phone number.');
        } else {
            db_exec($conn, "UPDATE users SET full_name = ?, phone = ? WHERE id = ?", "ssi", [$name, $phone, $uid]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Profile updated.');
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $hash = db_value($conn, "SELECT password FROM users WHERE id = ?", "i", [$uid]);

        if (!password_verify($current, (string) $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'Your new password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'The new passwords don\'t match.');
        } else {
            db_exec($conn, "UPDATE users SET password = ? WHERE id = ?", "si", [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    }

    if ($action === 'cancel_appointment') {
        $apptId = (int) ($_POST['appointment_id'] ?? 0);
        db_exec(
            $conn,
            "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'scheduled'",
            "ii",
            [$apptId, $uid]
        );
        flash('success', 'Appointment cancelled.');
    }
    redirect('account.php');
}

$me = db_one($conn, "SELECT * FROM users WHERE id = ?", "i", [$uid]);

$bookings = db_all(
    $conn,
    "SELECT b.*, COALESCE((SELECT SUM(amount) FROM payments p WHERE p.booking_id = b.id), 0) AS paid
     FROM bookings b WHERE b.user_id = ? ORDER BY b.event_date DESC",
    "i",
    [$uid]
);
$upcoming = array_filter($bookings, fn($b) => $b['event_date'] >= today() && in_array($b['status'], ['pending', 'confirmed'], true));
$history = array_filter($bookings, fn($b) => !($b['event_date'] >= today() && in_array($b['status'], ['pending', 'confirmed'], true)));
$upcoming = array_reverse($upcoming); // soonest first

$appointments = db_all(
    $conn,
    "SELECT a.*, b.reference FROM appointments a
     LEFT JOIN bookings b ON b.id = a.booking_id
     WHERE a.user_id = ? AND a.appointment_date >= ? ORDER BY a.appointment_date, a.appointment_time",
    "is",
    [$uid, today()]
);
$inquiries = db_all($conn, "SELECT * FROM inquiries WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", "i", [$uid]);

$pageTitle = "My Account | Romero's Party Needs";
$activePage = "account";
require_once 'includes/header.php';

function booking_card(array $b): void { ?>
  <a class="booking-card" href="booking.php?ref=<?php echo urlencode($b['reference']); ?>">
    <div class="booking-card-date">
      <span class="booking-card-month"><?php echo fmt_date($b['event_date'], 'M'); ?></span>
      <span class="booking-card-day"><?php echo fmt_date($b['event_date'], 'j'); ?></span>
    </div>
    <div class="booking-card-body">
      <span class="booking-card-title"><?php echo e($b['event_type']); ?> · <?php echo e($b['reference']); ?></span>
      <span class="muted"><?php echo e($b['venue_address']); ?> · setup <?php echo fmt_time($b['setup_time']); ?></span>
    </div>
    <div class="booking-card-meta">
      <?php echo status_badge($b['status']); ?>
      <span class="booking-card-total"><?php echo peso($b['total_amount']); ?></span>
      <?php if ($b['status'] !== 'cancelled'): ?>
        <span class="muted small">Paid <?php echo peso($b['paid']); ?></span>
      <?php endif; ?>
    </div>
  </a>
<?php }
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Hi, <?php echo e(explode(' ', $me['full_name'])[0]); ?></h1>
    <p class="page-sub">Your events, appointments and messages with us — all in one place.</p>
  </div>
</header>

<section class="container account-page">
  <div class="quick-actions">
    <a class="quick-action" href="book.php"><strong>Book an event</strong><span>Rentals, hosts &amp; organizers</span></a>
    <a class="quick-action" href="appointment.php"><strong>Book an appointment</strong><span>Ocular visit or consultation</span></a>
    <a class="quick-action" href="contact.php"><strong>Ask a question</strong><span>We reply within a day</span></a>
  </div>

  <div class="account-grid">
    <div class="account-main">
      <h2 class="section-label">Upcoming events</h2>
      <?php if ($upcoming): ?>
        <div class="booking-cards"><?php foreach ($upcoming as $b) booking_card($b); ?></div>
      <?php else: ?>
        <p class="empty-state">No upcoming events. <a href="book.php">Book one now</a>.</p>
      <?php endif; ?>

      <h2 class="section-label">Appointments</h2>
      <?php if ($appointments): ?>
        <ul class="appt-list">
          <?php foreach ($appointments as $a): ?>
            <li class="appt-item">
              <div>
                <strong><?php echo e($a['purpose']); ?></strong>
                <?php if ($a['reference']): ?><span class="muted"> · for <a href="booking.php?ref=<?php echo urlencode($a['reference']); ?>"><?php echo e($a['reference']); ?></a></span><?php endif; ?>
                <br /><span class="muted"><?php echo fmt_date($a['appointment_date']); ?> at <?php echo fmt_time($a['appointment_time']); ?></span>
              </div>
              <div class="appt-actions">
                <?php echo status_badge($a['status']); ?>
                <?php if ($a['status'] === 'scheduled'): ?>
                  <form method="POST" class="inline-form" data-confirm="Cancel this appointment?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel_appointment" />
                    <input type="hidden" name="appointment_id" value="<?php echo (int) $a['id']; ?>" />
                    <button type="submit" class="btn-danger-link">Cancel</button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="empty-state">No upcoming appointments.</p>
      <?php endif; ?>

      <?php if ($history): ?>
        <h2 class="section-label">Past &amp; cancelled</h2>
        <div class="booking-cards booking-cards--muted"><?php foreach ($history as $b) booking_card($b); ?></div>
      <?php endif; ?>
    </div>

    <aside class="account-side">
      <div class="panel">
        <h2 class="panel-title">Profile</h2>
        <form method="POST">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="profile" />
          <div class="form-row">
            <label for="full_name">Name</label>
            <input type="text" id="full_name" name="full_name" required value="<?php echo e($me['full_name']); ?>" />
          </div>
          <div class="form-row">
            <label>Email</label>
            <input type="email" value="<?php echo e($me['email']); ?>" disabled />
          </div>
          <div class="form-row">
            <label for="phone">Mobile number</label>
            <input type="tel" id="phone" name="phone" required value="<?php echo e($me['phone']); ?>" />
          </div>
          <button type="submit" class="btn-ghost">Save profile</button>
        </form>
      </div>

      <div class="panel">
        <h2 class="panel-title">Change password</h2>
        <form method="POST" class="js-validate" novalidate>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="password" />
          <div class="form-row">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password" />
            <span class="field-error">Enter your current password.</span>
          </div>
          <div class="form-row">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password" />
            <span class="field-error">At least 6 characters.</span>
          </div>
          <div class="form-row">
            <label for="confirm_new_password">Confirm new password</label>
            <input type="password" id="confirm_new_password" name="confirm_password" required minlength="6" data-match="#new_password" autocomplete="new-password" />
            <span class="field-error">Passwords must match.</span>
          </div>
          <button type="submit" class="btn-ghost">Change password</button>
        </form>
      </div>

      <?php if ($inquiries): ?>
        <div class="panel">
          <h2 class="panel-title">Your messages</h2>
          <ul class="plain-list">
            <?php foreach ($inquiries as $q): ?>
              <li>
                <span class="muted small"><?php echo fmt_date($q['created_at'], 'M j, Y'); ?></span>
                <?php echo status_badge($q['status']); ?><br />
                <?php echo e(mb_strimwidth($q['inquiry'], 0, 90, '…')); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
