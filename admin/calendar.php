<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

// ---- Block / unblock dates ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $date = clean_date(post_str('date'));
    if (($_POST['action'] ?? '') === 'block' && $date) {
        $reason = post_str('reason') ?: 'Closed';
        db_exec($conn, "INSERT IGNORE INTO blocked_dates (blocked_date, reason, created_by) VALUES (?, ?, ?)", "ssi", [$date, $reason, current_user_id()]);
        $existing = count_active_bookings($conn, $date);
        flash('success', fmt_date($date) . ' blocked for new bookings and appointments.' . ($existing ? " Note: $existing existing booking(s) on that date are not affected." : ''));
    }
    if (($_POST['action'] ?? '') === 'unblock' && $date) {
        db_exec($conn, "DELETE FROM blocked_dates WHERE blocked_date = ?", "s", [$date]);
        flash('success', fmt_date($date) . ' is open again.');
    }
    redirect('admin/calendar.php?' . http_build_query(array_filter(['month' => $_POST['month'] ?? '', 'day' => $_POST['day'] ?? ''])));
}

// ---- Which month / day to show -----------------------------------------
$day = clean_date($_GET['day'] ?? '') ?: null;
$month = $_GET['month'] ?? ($day ? substr($day, 0, 7) : date('Y-m'));
if (!is_string($month) || !preg_match('/^(\d{4})-(\d{2})$/', $month, $mm) || !checkdate((int) $mm[2], 1, (int) $mm[1])) {
    $month = date('Y-m');
}
$first = $month . '-01';
$last = date('Y-m-t', strtotime($first));
$prevMonth = date('Y-m', strtotime($first . ' -1 month'));
$nextMonth = date('Y-m', strtotime($first . ' +1 month'));

$bookingsByDay = [];
foreach (db_all(
    $conn,
    "SELECT b.id, b.reference, b.event_date, b.setup_time, b.status, b.event_type, u.full_name
     FROM bookings b JOIN users u ON u.id = b.user_id
     WHERE b.event_date BETWEEN ? AND ? AND b.status <> 'cancelled'
     ORDER BY b.setup_time",
    "ss",
    [$first, $last]
) as $b) {
    $bookingsByDay[$b['event_date']][] = $b;
}

$apptsByDay = [];
foreach (db_all(
    $conn,
    "SELECT a.*, u.full_name FROM appointments a JOIN users u ON u.id = a.user_id
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'scheduled' ORDER BY a.appointment_time",
    "ss",
    [$first, $last]
) as $a) {
    $apptsByDay[$a['appointment_date']][] = $a;
}

$blocked = [];
foreach (db_all($conn, "SELECT * FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?", "ss", [$first, $last]) as $bd) {
    $blocked[$bd['blocked_date']] = $bd['reason'];
}
$upcomingBlocked = db_all($conn, "SELECT * FROM blocked_dates WHERE blocked_date >= ? ORDER BY blocked_date LIMIT 20", "s", [today()]);

// ---- Day plan (when a day is selected) ----------------------------------
$dayBookings = $dayAppointments = $loadList = $dayServices = [];
if ($day) {
    $dayBookings = db_all(
        $conn,
        "SELECT b.*, u.full_name, u.phone FROM bookings b JOIN users u ON u.id = b.user_id
         WHERE b.event_date = ? AND b.status IN ('pending','confirmed','completed') ORDER BY b.setup_time",
        "s",
        [$day]
    );
    $dayAppointments = db_all(
        $conn,
        "SELECT a.*, u.full_name, u.phone FROM appointments a JOIN users u ON u.id = a.user_id
         WHERE a.appointment_date = ? AND a.status = 'scheduled' ORDER BY a.appointment_time",
        "s",
        [$day]
    );
    // Everything that has to go on the truck that day, across all bookings.
    $loadList = db_all(
        $conn,
        "SELECT i.name, i.unit, i.total_quantity, c.name AS category_name, SUM(bi.quantity) AS qty
         FROM booking_items bi
         JOIN bookings b ON b.id = bi.booking_id
         JOIN inventory_items i ON i.id = bi.item_id
         JOIN categories c ON c.id = i.category_id
         WHERE b.event_date = ? AND b.status IN ('pending','confirmed')
         GROUP BY i.id, i.name, i.unit, i.total_quantity, c.name, c.sort_order
         ORDER BY c.sort_order, i.name",
        "s",
        [$day]
    );
    $dayServices = db_all(
        $conn,
        "SELECT s.name, s.daily_capacity, COUNT(*) AS used
         FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id JOIN services s ON s.id = bs.service_id
         WHERE b.event_date = ? AND b.status IN ('pending','confirmed')
         GROUP BY s.id, s.name, s.daily_capacity",
        "s",
        [$day]
    );
}

$pageTitle = "Calendar | Admin";
$activePage = "admin";
$adminPage = "calendar";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Calendar</h1>
    <div class="cal-month-nav">
      <a class="btn-ghost" href="?month=<?php echo $prevMonth; ?>">&lsaquo; Prev</a>
      <strong><?php echo date('F Y', strtotime($first)); ?></strong>
      <a class="btn-ghost" href="?month=<?php echo $nextMonth; ?>">Next &rsaquo;</a>
      <?php if ($month !== date('Y-m')): ?><a class="small" href="calendar.php">Today</a><?php endif; ?>
    </div>
  </div>

  <div class="admin-cal">
    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>
      <div class="admin-cal-wd"><?php echo $wd; ?></div>
    <?php endforeach; ?>

    <?php for ($i = 0, $pad = (int) date('w', strtotime($first)); $i < $pad; $i++): ?>
      <div class="admin-cal-day is-pad"></div>
    <?php endfor; ?>

    <?php for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime($d . ' +1 day'))):
        $count = count(array_filter($bookingsByDay[$d] ?? [], fn($b) => $b['status'] !== 'completed'));
        $classes = ['admin-cal-day'];
        if ($d === today()) $classes[] = 'is-today';
        if ($d === $day) $classes[] = 'is-selected';
        if (isset($blocked[$d])) $classes[] = 'is-blocked';
        if ($count >= MAX_BOOKINGS_PER_DAY) $classes[] = 'is-full';
        if ($d < today()) $classes[] = 'is-past';
    ?>
      <a class="<?php echo implode(' ', $classes); ?>" href="?month=<?php echo $month; ?>&day=<?php echo $d; ?>">
        <span class="admin-cal-num"><?php echo (int) substr($d, 8); ?>
          <?php if ($count): ?><span class="admin-cal-count"><?php echo $count; ?>/<?php echo MAX_BOOKINGS_PER_DAY; ?></span><?php endif; ?>
        </span>
        <?php if (isset($blocked[$d])): ?><span class="cal-chip cal-chip--blocked">Blocked</span><?php endif; ?>
        <?php foreach ($bookingsByDay[$d] ?? [] as $b): ?>
          <span class="cal-chip cal-chip--<?php echo e($b['status']); ?>"><?php echo fmt_time($b['setup_time']); ?> <?php echo e($b['event_type']); ?></span>
        <?php endforeach; ?>
        <?php foreach ($apptsByDay[$d] ?? [] as $a): ?>
          <span class="cal-chip cal-chip--appt"><?php echo fmt_time($a['appointment_time']); ?> Appt</span>
        <?php endforeach; ?>
      </a>
    <?php endfor; ?>
  </div>
  <p class="cal-key small muted">
    <span class="cal-chip cal-chip--pending">Pending</span>
    <span class="cal-chip cal-chip--confirmed">Confirmed</span>
    <span class="cal-chip cal-chip--completed">Completed</span>
    <span class="cal-chip cal-chip--appt">Appointment</span>
    <span class="cal-chip cal-chip--blocked">Blocked</span>
    · Click a day for the day plan and truck load list.
  </p>

  <?php if ($day): ?>
    <div class="panel day-plan" id="day-plan">
      <div class="admin-head">
        <h2 class="panel-title">Day plan — <?php echo fmt_date($day, 'l, F j, Y'); ?></h2>
        <form method="POST" class="inline-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="date" value="<?php echo e($day); ?>" />
          <input type="hidden" name="month" value="<?php echo e($month); ?>" />
          <input type="hidden" name="day" value="<?php echo e($day); ?>" />
          <?php if (isset($blocked[$day])): ?>
            <span class="muted small">Blocked: <?php echo e($blocked[$day]); ?></span>
            <input type="hidden" name="action" value="unblock" />
            <button class="btn-ghost">Unblock date</button>
          <?php elseif ($day >= today()): ?>
            <input type="hidden" name="action" value="block" />
            <input type="text" name="reason" placeholder="Reason (optional)" maxlength="255" />
            <button class="btn-ghost">Block this date</button>
          <?php endif; ?>
        </form>
      </div>

      <div class="detail-grid">
        <div>
          <h3 class="item-group-title">Setups (<?php echo count($dayBookings); ?>)</h3>
          <?php if ($dayBookings): ?>
            <ul class="plain-list">
              <?php foreach ($dayBookings as $b): ?>
                <li>
                  <strong><?php echo fmt_time($b['setup_time']); ?> setup</strong> · event <?php echo fmt_time($b['event_start']); ?>–<?php echo fmt_time($b['event_end']); ?>
                  <?php echo status_badge($b['status']); ?><br />
                  <a href="booking.php?id=<?php echo (int) $b['id']; ?>"><?php echo e($b['reference']); ?></a> — <?php echo e($b['event_type']); ?> for <?php echo e($b['full_name']); ?> (<?php echo e($b['phone']); ?>)<br />
                  <span class="muted small"><?php echo e($b['venue_address']); ?> · <?php echo (int) $b['guest_count']; ?> guests</span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">No bookings.</p>
          <?php endif; ?>

          <h3 class="item-group-title">Appointments (<?php echo count($dayAppointments); ?>)</h3>
          <?php if ($dayAppointments): ?>
            <ul class="plain-list">
              <?php foreach ($dayAppointments as $a): ?>
                <li><strong><?php echo fmt_time($a['appointment_time']); ?></strong> — <?php echo e($a['purpose']); ?> with <?php echo e($a['full_name']); ?> (<?php echo e($a['phone']); ?>)</li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">No appointments.</p>
          <?php endif; ?>
        </div>

        <div>
          <h3 class="item-group-title">Truck load list</h3>
          <?php if ($loadList): ?>
            <table class="data-table data-table--compact">
              <thead><tr><th>Item</th><th class="num">Needed</th><th class="num">Left in stock</th></tr></thead>
              <tbody>
                <?php foreach ($loadList as $l): $left = $l['total_quantity'] - $l['qty']; ?>
                  <tr>
                    <td><?php echo e($l['name']); ?> <span class="muted small">· <?php echo e($l['category_name']); ?></span></td>
                    <td class="num"><strong><?php echo (int) $l['qty']; ?></strong> <?php echo e($l['unit']); ?></td>
                    <td class="num <?php echo $left <= 0 ? 'text-danger' : ''; ?>"><?php echo $left; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="muted">Nothing to load.</p>
          <?php endif; ?>

          <?php if ($dayServices): ?>
            <h3 class="item-group-title">Service teams</h3>
            <ul class="plain-list">
              <?php foreach ($dayServices as $s): ?>
                <li><?php echo e($s['name']); ?>: <strong><?php echo (int) $s['used']; ?>/<?php echo (int) $s['daily_capacity']; ?></strong> booked</li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h2 class="panel-title">Blocked dates</h2>
    <form method="POST" class="filter-bar">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="block" />
      <input type="hidden" name="month" value="<?php echo e($month); ?>" />
      <input type="date" name="date" min="<?php echo today(); ?>" required />
      <input type="text" name="reason" placeholder="Reason, e.g. Holy Week" maxlength="255" />
      <button type="submit" class="btn-ghost">Block date</button>
    </form>
    <?php if ($upcomingBlocked): ?>
      <ul class="plain-list">
        <?php foreach ($upcomingBlocked as $bd): ?>
          <li class="row-between">
            <span><strong><?php echo fmt_date($bd['blocked_date']); ?></strong> — <?php echo e($bd['reason']); ?></span>
            <form method="POST" class="inline-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="unblock" />
              <input type="hidden" name="date" value="<?php echo e($bd['blocked_date']); ?>" />
              <input type="hidden" name="month" value="<?php echo e($month); ?>" />
              <button class="btn-danger-link">Unblock</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">No upcoming blocked dates.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
