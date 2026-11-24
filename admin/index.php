<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$today = today();
$in7 = date('Y-m-d', strtotime('+7 days'));
$in30 = date('Y-m-d', strtotime('+30 days'));

$stats = [
    'pending' => (int) db_value($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
    'upcoming' => (int) db_value($conn, "SELECT COUNT(*) FROM bookings WHERE status IN ('pending','confirmed') AND event_date BETWEEN ? AND ?", "ss", [$today, $in30]),
    'collected' => (float) db_value($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(paid_at, '%Y-%m') = ?", "s", [date('Y-m')]),
    'outstanding' => (float) db_value(
        $conn,
        "SELECT COALESCE(SUM(b.total_amount - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id), 0)), 0)
         FROM bookings b WHERE b.status IN ('pending','confirmed')"
    ),
    'inquiries' => (int) db_value($conn, "SELECT COUNT(*) FROM inquiries WHERE status = 'new'"),
];

// The crew's week: every setup in the next 7 days.
$schedule = db_all(
    $conn,
    "SELECT b.*, u.full_name, u.phone,
            (SELECT COALESCE(SUM(quantity), 0) FROM booking_items WHERE booking_id = b.id) AS item_count
     FROM bookings b JOIN users u ON u.id = b.user_id
     WHERE b.status IN ('pending','confirmed') AND b.event_date BETWEEN ? AND ?
     ORDER BY b.event_date, b.setup_time",
    "ss",
    [$today, $in7]
);

$appointments = db_all(
    $conn,
    "SELECT a.*, u.full_name, u.phone, b.reference, b.id AS bid
     FROM appointments a JOIN users u ON u.id = a.user_id
     LEFT JOIN bookings b ON b.id = a.booking_id
     WHERE a.status = 'scheduled' AND a.appointment_date BETWEEN ? AND ?
     ORDER BY a.appointment_date, a.appointment_time",
    "ss",
    [$today, $in7]
);

// Items that are 80%+ reserved on some date in the next 30 days.
$stockWatch = db_all(
    $conn,
    "SELECT b.event_date, i.id, i.name, i.total_quantity, SUM(bi.quantity) AS reserved
     FROM booking_items bi
     JOIN bookings b ON b.id = bi.booking_id
     JOIN inventory_items i ON i.id = bi.item_id
     WHERE b.status IN ('pending','confirmed') AND b.event_date BETWEEN ? AND ?
     GROUP BY b.event_date, i.id, i.name, i.total_quantity
     HAVING SUM(bi.quantity) >= 0.8 * i.total_quantity
     ORDER BY b.event_date, i.name",
    "ss",
    [$today, $in30]
);

$recentInquiries = db_all($conn, "SELECT * FROM inquiries WHERE status = 'new' ORDER BY created_at DESC LIMIT 5");

$pageTitle = "Admin Dashboard | Romero's Party Needs";
$activePage = "admin";
$adminPage = "dashboard";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening'); ?>, <?php echo e(explode(' ', $_SESSION['user_name'])[0]); ?></h1>
    <a class="btn-primary" href="<?php echo url('book.php'); ?>">+ New booking</a>
  </div>

  <div class="stat-cards">
    <a class="stat-card" href="bookings.php?status=pending">
      <span class="stat-card-value"><?php echo $stats['pending']; ?></span>
      <span class="stat-card-label">Bookings to confirm</span>
    </a>
    <a class="stat-card" href="bookings.php?view=upcoming">
      <span class="stat-card-value"><?php echo $stats['upcoming']; ?></span>
      <span class="stat-card-label">Events in next 30 days</span>
    </a>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo peso($stats['collected']); ?></span>
      <span class="stat-card-label">Collected in <?php echo date('F'); ?></span>
    </div>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo peso($stats['outstanding']); ?></span>
      <span class="stat-card-label">Outstanding balance</span>
    </div>
    <a class="stat-card" href="inquiries.php">
      <span class="stat-card-value"><?php echo $stats['inquiries']; ?></span>
      <span class="stat-card-label">New inquiries</span>
    </a>
  </div>

  <div class="admin-grid">
    <div class="panel">
      <h2 class="panel-title">Setup schedule — next 7 days</h2>
      <?php if ($schedule): ?>
        <table class="data-table">
          <thead><tr><th>Date</th><th>Setup</th><th>Booking</th><th>Venue</th><th class="num">Items</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($schedule as $b): ?>
              <tr>
                <td><a href="calendar.php?day=<?php echo e($b['event_date']); ?>"><?php echo fmt_date($b['event_date'], 'D M j'); ?></a></td>
                <td><?php echo fmt_time($b['setup_time']); ?></td>
                <td><a href="booking.php?id=<?php echo (int) $b['id']; ?>"><?php echo e($b['reference']); ?></a><br /><span class="muted small"><?php echo e($b['full_name']); ?> · <?php echo e($b['event_type']); ?></span></td>
                <td class="small"><?php echo e($b['venue_address']); ?></td>
                <td class="num"><?php echo (int) $b['item_count']; ?></td>
                <td><?php echo status_badge($b['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty-state">No setups in the next 7 days.</p>
      <?php endif; ?>
    </div>

    <div class="admin-side">
      <div class="panel">
        <h2 class="panel-title">Appointments this week</h2>
        <?php if ($appointments): ?>
          <ul class="plain-list">
            <?php foreach ($appointments as $a): ?>
              <li>
                <strong><?php echo fmt_date($a['appointment_date'], 'D M j'); ?>, <?php echo fmt_time($a['appointment_time']); ?></strong><br />
                <?php echo e($a['purpose']); ?> — <?php echo e($a['full_name']); ?>
                <?php if ($a['bid']): ?><a class="small" href="booking.php?id=<?php echo (int) $a['bid']; ?>">(<?php echo e($a['reference']); ?>)</a><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty-state">None scheduled.</p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2 class="panel-title">Stock watch — next 30 days</h2>
        <?php if ($stockWatch): ?>
          <ul class="plain-list">
            <?php foreach ($stockWatch as $s): $left = $s['total_quantity'] - $s['reserved']; ?>
              <li>
                <a href="calendar.php?day=<?php echo e($s['event_date']); ?>"><?php echo fmt_date($s['event_date'], 'M j'); ?></a> —
                <?php echo e($s['name']); ?>:
                <strong class="<?php echo $left <= 0 ? 'text-danger' : ''; ?>"><?php echo $left; ?> left</strong>
                <span class="muted small">of <?php echo (int) $s['total_quantity']; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty-state">No items running low.</p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2 class="panel-title">New inquiries</h2>
        <?php if ($recentInquiries): ?>
          <ul class="plain-list">
            <?php foreach ($recentInquiries as $q): ?>
              <li><strong><?php echo e($q['name']); ?></strong> <span class="muted small"><?php echo fmt_date($q['created_at'], 'M j'); ?></span><br /><?php echo e(mb_strimwidth($q['inquiry'], 0, 80, '…')); ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="small" href="inquiries.php">All inquiries &rarr;</a>
        <?php else: ?>
          <p class="empty-state">You're all caught up.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
