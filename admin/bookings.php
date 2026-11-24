<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$status = $_GET['status'] ?? '';
$view = $_GET['view'] ?? '';
$q = trim($_GET['q'] ?? '');
$from = clean_date($_GET['from'] ?? '') ?: '';
$to = clean_date($_GET['to'] ?? '') ?: '';
$userFilter = (int) ($_GET['user'] ?? 0);

// Build the WHERE clause from whichever filters are filled in.
$where = [];
$types = '';
$params = [];
if (in_array($status, BOOKING_STATUSES, true)) {
    $where[] = 'b.status = ?'; $types .= 's'; $params[] = $status;
}
if ($view === 'upcoming') {
    $where[] = "b.status IN ('pending','confirmed') AND b.event_date >= ?"; $types .= 's'; $params[] = today();
}
if ($from) { $where[] = 'b.event_date >= ?'; $types .= 's'; $params[] = $from; }
if ($to)   { $where[] = 'b.event_date <= ?'; $types .= 's'; $params[] = $to; }
if ($userFilter) { $where[] = 'b.user_id = ?'; $types .= 'i'; $params[] = $userFilter; }
if ($q !== '') {
    $where[] = '(b.reference LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR b.venue_address LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$sql = "SELECT b.*, u.full_name, u.email,
               COALESCE((SELECT SUM(amount) FROM payments p WHERE p.booking_id = b.id), 0) AS paid
        FROM bookings b JOIN users u ON u.id = b.user_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY (b.status IN ('pending','confirmed') AND b.event_date >= CURDATE()) DESC,
                  CASE WHEN b.event_date >= CURDATE() THEN b.event_date END ASC,
                  b.event_date DESC
        LIMIT 300";
$bookings = db_all($conn, $sql, $types, $params);

$filteredCustomer = $userFilter ? db_one($conn, "SELECT full_name FROM users WHERE id = ?", "i", [$userFilter]) : null;

$pageTitle = "Bookings | Admin";
$activePage = "admin";
$adminPage = "bookings";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';

function filter_link(string $label, array $query, bool $active): string
{
    return '<a class="pill ' . ($active ? 'active' : '') . '" href="bookings.php' . ($query ? '?' . http_build_query($query) : '') . '">' . e($label) . '</a>';
}
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Bookings<?php if ($filteredCustomer): ?> <span class="muted">for <?php echo e($filteredCustomer['full_name']); ?></span><?php endif; ?></h1>
    <a class="btn-primary" href="<?php echo url('book.php'); ?>">+ New booking</a>
  </div>

  <div class="pill-row">
    <?php
      echo filter_link('All', [], !$status && !$view);
      echo filter_link('Upcoming', ['view' => 'upcoming'], $view === 'upcoming');
      foreach (BOOKING_STATUSES as $s) {
          echo filter_link(ucfirst($s), ['status' => $s], $status === $s);
      }
    ?>
  </div>

  <form class="filter-bar" method="GET">
    <?php if ($status): ?><input type="hidden" name="status" value="<?php echo e($status); ?>" /><?php endif; ?>
    <?php if ($userFilter): ?><input type="hidden" name="user" value="<?php echo $userFilter; ?>" /><?php endif; ?>
    <input type="search" name="q" placeholder="Reference, customer, venue…" value="<?php echo e($q); ?>" />
    <label>From <input type="date" name="from" value="<?php echo e($from); ?>" /></label>
    <label>To <input type="date" name="to" value="<?php echo e($to); ?>" /></label>
    <button type="submit" class="btn-ghost">Filter</button>
    <?php if ($q || $from || $to || $userFilter): ?><a href="bookings.php" class="small">Clear</a><?php endif; ?>
  </form>

  <div class="panel panel--flush">
    <?php if ($bookings): ?>
      <table class="data-table">
        <thead>
          <tr><th>Reference</th><th>Event date</th><th>Customer</th><th>Event</th><th>Status</th><th class="num">Total</th><th class="num">Paid</th><th>Payment</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr class="<?php echo $b['status'] === 'cancelled' ? 'row-muted' : ''; ?>">
              <td><a href="booking.php?id=<?php echo (int) $b['id']; ?>"><strong><?php echo e($b['reference']); ?></strong></a></td>
              <td><?php echo fmt_date($b['event_date'], 'M j, Y'); ?><br /><span class="muted small">setup <?php echo fmt_time($b['setup_time']); ?></span></td>
              <td><?php echo e($b['full_name']); ?><br /><span class="muted small"><?php echo e($b['email']); ?></span></td>
              <td><?php echo e($b['event_type']); ?><br /><span class="muted small"><?php echo (int) $b['guest_count']; ?> guests</span></td>
              <td><?php echo status_badge($b['status']); ?></td>
              <td class="num"><?php echo peso($b['total_amount']); ?></td>
              <td class="num"><?php echo peso($b['paid']); ?></td>
              <td><?php echo status_badge(payment_status((float) $b['total_amount'], (float) $b['paid'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No bookings match these filters.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
