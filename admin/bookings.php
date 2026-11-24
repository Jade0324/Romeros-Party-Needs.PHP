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

$fromSql = " FROM bookings b JOIN users u ON u.id = b.user_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$orderSql = " ORDER BY (b.status IN ('pending','confirmed') AND b.event_date >= CURDATE()) DESC,
                       CASE WHEN b.event_date >= CURDATE() THEN b.event_date END ASC,
                       b.event_date DESC, b.id DESC";
$selectSql = "SELECT b.*, u.full_name, u.email, u.phone,
                     COALESCE((SELECT SUM(amount) FROM payments p WHERE p.booking_id = b.id), 0) AS paid" . $fromSql . $orderSql;

// The filters currently in use, so links (pages, export) keep them.
$filterQuery = array_filter(['status' => $status, 'view' => $view, 'q' => $q, 'from' => $from, 'to' => $to, 'user' => $userFilter ?: '']);

// ---- Export: every booking matching the filters, as a spreadsheet ----------
if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    foreach (db_all($conn, $selectSql, $types, $params) as $b) {
        $paid = (float) $b['paid'];
        $rows[] = [
            $b['reference'], $b['event_date'], substr($b['setup_time'], 0, 5), substr($b['event_start'], 0, 5), substr($b['event_end'], 0, 5),
            $b['event_type'], (int) $b['guest_count'], $b['full_name'], $b['email'], $b['phone'], $b['venue_address'],
            $b['status'], $b['items_total'], $b['services_total'], $b['delivery_fee'], $b['total_amount'],
            number_format($paid, 2, '.', ''), number_format(max(0, (float) $b['total_amount'] - $paid), 2, '.', ''),
            payment_status((float) $b['total_amount'], $paid), $b['created_at'],
        ];
    }
    csv_download('bookings-' . today() . '.csv', [
        'Reference', 'Event date', 'Setup', 'Start', 'End', 'Event type', 'Guests', 'Customer', 'Email', 'Phone', 'Venue',
        'Status', 'Items total', 'Services total', 'Delivery fee', 'Total', 'Paid', 'Balance', 'Payment', 'Booked on',
    ], $rows);
}

// ---- One page of results --------------------------------------------------
$perPage = 50;
$totalFound = (int) db_value($conn, "SELECT COUNT(*)" . $fromSql, $types, $params);
$pages = max(1, (int) ceil($totalFound / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$bookings = db_all($conn, $selectSql . " LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $types, $params);

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
    <h1 class="admin-title">Bookings<?php if ($filteredCustomer): ?> <span class="muted">for <?php echo e($filteredCustomer['full_name']); ?></span><?php endif; ?> <span class="muted">(<?php echo $totalFound; ?>)</span></h1>
    <div class="admin-head-actions">
      <?php if ($totalFound): ?>
        <a class="btn-ghost" href="?<?php echo e(http_build_query(['export' => 'csv'] + $filterQuery)); ?>" title="Download the bookings shown (all pages) for Excel or Google Sheets">Export CSV</a>
      <?php endif; ?>
      <a class="btn-primary" href="<?php echo url('book.php'); ?>">+ New booking</a>
    </div>
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
  <?php echo pagination_links($page, $perPage, $totalFound, $filterQuery); ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
