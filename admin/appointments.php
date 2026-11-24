<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $apptId = (int) ($_POST['id'] ?? 0);
    $to = $_POST['to'] ?? '';
    if (in_array($to, ['completed', 'cancelled', 'scheduled'], true)) {
        $appt = db_one($conn, "SELECT * FROM appointments WHERE id = ?", "i", [$apptId]);
        if ($appt) {
            if ($to === 'scheduled') {
                // Re-opening: make sure nobody else took the slot meanwhile.
                $taken = db_value($conn, "SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status = 'scheduled' AND id <> ?",
                    "ssi", [$appt['appointment_date'], $appt['appointment_time'], $apptId]);
                if ($taken) {
                    flash('error', 'That slot has been taken by another appointment.');
                    redirect('admin/appointments.php?' . http_build_query(['view' => $_POST['view'] ?? '']));
                }
            }
            db_exec($conn, "UPDATE appointments SET status = ? WHERE id = ?", "si", [$to, $apptId]);
            if ($appt['booking_id']) {
                add_booking_log($conn, (int) $appt['booking_id'], 'appointment', "{$appt['purpose']} marked $to", current_user_id());
            }
            flash('success', "Appointment marked $to.");
        }
    }
    redirect('admin/appointments.php?' . http_build_query(['view' => $_POST['view'] ?? '']));
}

$view = in_array($_GET['view'] ?? '', ['upcoming', 'past', 'all'], true) ? $_GET['view'] : 'upcoming';
$where = ['upcoming' => 'WHERE a.appointment_date >= ?', 'past' => 'WHERE a.appointment_date < ?', 'all' => ''][$view];
$order = $view === 'upcoming' ? 'a.appointment_date, a.appointment_time' : 'a.appointment_date DESC, a.appointment_time DESC';

$appointments = db_all(
    $conn,
    "SELECT a.*, u.full_name, u.email, u.phone, b.reference, b.event_date
     FROM appointments a JOIN users u ON u.id = a.user_id
     LEFT JOIN bookings b ON b.id = a.booking_id
     $where ORDER BY $order LIMIT 300",
    $where ? "s" : "",
    $where ? [today()] : []
);

$pageTitle = "Appointments | Admin";
$activePage = "admin";
$adminPage = "appointments";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head"><h1 class="admin-title">Appointments</h1></div>
  <div class="pill-row">
    <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $k => $label): ?>
      <a class="pill <?php echo $view === $k ? 'active' : ''; ?>" href="?view=<?php echo $k; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
  </div>

  <div class="panel panel--flush">
    <?php if ($appointments): ?>
      <table class="data-table">
        <thead><tr><th>When</th><th>Purpose</th><th>Customer</th><th>Booking</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr class="<?php echo $a['status'] === 'cancelled' ? 'row-muted' : ''; ?>">
              <td><strong><?php echo fmt_date($a['appointment_date'], 'D, M j'); ?></strong><br /><?php echo fmt_time($a['appointment_time']); ?></td>
              <td><?php echo e($a['purpose']); ?><?php if ($a['notes']): ?><br /><span class="muted small"><?php echo e($a['notes']); ?></span><?php endif; ?></td>
              <td><?php echo e($a['full_name']); ?><br /><span class="muted small"><a href="tel:<?php echo e($a['phone']); ?>"><?php echo e($a['phone']); ?></a></span></td>
              <td><?php if ($a['booking_id']): ?><a href="booking.php?id=<?php echo (int) $a['booking_id']; ?>"><?php echo e($a['reference']); ?></a><br /><span class="muted small">event <?php echo fmt_date($a['event_date'], 'M j'); ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
              <td><?php echo status_badge($a['status']); ?></td>
              <td class="row-actions">
                <form method="POST" class="inline-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>" />
                  <input type="hidden" name="view" value="<?php echo e($view); ?>" />
                  <?php if ($a['status'] === 'scheduled'): ?>
                    <button name="to" value="completed" class="link-btn">Done</button>
                    <button name="to" value="cancelled" class="link-btn link-danger" data-confirm="Cancel this appointment?">Cancel</button>
                  <?php else: ?>
                    <button name="to" value="scheduled" class="link-btn">Reopen</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No appointments here.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
