<?php
// ==========================================================================
// Admin → Reports: how the business did in a year.
//  - "Booked value" counts bookings by their EVENT date (cancelled ones left
//    out), i.e. the work done that month.
//  - "Collected" counts payments by the day they were RECEIVED.
// Both can be downloaded as CSV for the family's own records.
// ==========================================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

// ---- Which year -------------------------------------------------------------
$years = array_map('intval', array_column(db_all(
    $conn,
    "SELECT DISTINCT YEAR(event_date) AS y FROM bookings
     UNION SELECT DISTINCT YEAR(paid_at) FROM payments
     UNION SELECT YEAR(CURDATE())
     ORDER BY y DESC"
), 'y'));
$year = (int) ($_GET['year'] ?? date('Y'));
if (!in_array($year, $years, true)) {
    $year = (int) date('Y');
}
$yearStart = "$year-01-01";
$nextYearStart = ($year + 1) . '-01-01';

// ---- Month by month -----------------------------------------------------------
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = ['label' => date('M', mktime(0, 0, 0, $m, 1)), 'bookings' => 0, 'value' => 0.0, 'cancelled' => 0, 'collected' => 0.0];
}
foreach (db_all(
    $conn,
    "SELECT MONTH(event_date) AS m,
            SUM(status <> 'cancelled') AS bookings,
            SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END) AS value,
            SUM(status = 'cancelled') AS cancelled
     FROM bookings WHERE event_date >= ? AND event_date < ?
     GROUP BY MONTH(event_date)",
    "ss",
    [$yearStart, $nextYearStart]
) as $r) {
    $months[(int) $r['m']]['bookings'] = (int) $r['bookings'];
    $months[(int) $r['m']]['value'] = (float) $r['value'];
    $months[(int) $r['m']]['cancelled'] = (int) $r['cancelled'];
}
foreach (db_all(
    $conn,
    "SELECT MONTH(paid_at) AS m, SUM(amount) AS collected FROM payments
     WHERE paid_at >= ? AND paid_at < ? GROUP BY MONTH(paid_at)",
    "ss",
    [$yearStart, $nextYearStart]
) as $r) {
    $months[(int) $r['m']]['collected'] = (float) $r['collected'];
}

$totals = [
    'bookings' => array_sum(array_column($months, 'bookings')),
    'value' => array_sum(array_column($months, 'value')),
    'cancelled' => array_sum(array_column($months, 'cancelled')),
    'collected' => array_sum(array_column($months, 'collected')),
];
$totals['average'] = $totals['bookings'] ? $totals['value'] / $totals['bookings'] : 0;
$allBookings = $totals['bookings'] + $totals['cancelled'];
$totals['cancel_rate'] = $allBookings ? $totals['cancelled'] / $allBookings : 0;

// ---- What was rented, which services, which events --------------------------
$topItems = db_all(
    $conn,
    "SELECT i.name, c.name AS category, SUM(bi.quantity) AS units, COUNT(DISTINCT b.id) AS bookings, SUM(bi.subtotal) AS revenue
     FROM booking_items bi
     JOIN bookings b ON b.id = bi.booking_id
     JOIN inventory_items i ON i.id = bi.item_id
     JOIN categories c ON c.id = i.category_id
     WHERE b.status <> 'cancelled' AND b.event_date >= ? AND b.event_date < ?
     GROUP BY i.id, i.name, c.name
     ORDER BY revenue DESC, units DESC
     LIMIT 10",
    "ss",
    [$yearStart, $nextYearStart]
);
$serviceStats = db_all(
    $conn,
    "SELECT s.name, s.provider, COUNT(*) AS bookings, SUM(bs.price) AS revenue
     FROM booking_services bs
     JOIN bookings b ON b.id = bs.booking_id
     JOIN services s ON s.id = bs.service_id
     WHERE b.status <> 'cancelled' AND b.event_date >= ? AND b.event_date < ?
     GROUP BY s.id, s.name, s.provider
     ORDER BY revenue DESC",
    "ss",
    [$yearStart, $nextYearStart]
);
$eventTypes = db_all(
    $conn,
    "SELECT event_type, COUNT(*) AS bookings, SUM(total_amount) AS value
     FROM bookings
     WHERE status <> 'cancelled' AND event_date >= ? AND event_date < ?
     GROUP BY event_type ORDER BY bookings DESC, value DESC",
    "ss",
    [$yearStart, $nextYearStart]
);

// ---- Downloads ---------------------------------------------------------------
$export = $_GET['export'] ?? '';
if ($export === 'monthly') {
    $rows = [];
    foreach ($months as $m => $row) {
        $rows[] = [sprintf('%04d-%02d', $year, $m), $row['bookings'], number_format($row['value'], 2, '.', ''), $row['cancelled'], number_format($row['collected'], 2, '.', '')];
    }
    $rows[] = ['Total', $totals['bookings'], number_format($totals['value'], 2, '.', ''), $totals['cancelled'], number_format($totals['collected'], 2, '.', '')];
    csv_download("monthly-summary-$year.csv", ['Month', 'Bookings', 'Booked value', 'Cancelled', 'Collected'], $rows);
}
if ($export === 'payments') {
    $rows = db_all(
        $conn,
        "SELECT p.paid_at, b.reference, u.full_name, b.event_date, p.method, p.reference_no, p.amount, r.full_name AS recorded_by
         FROM payments p
         JOIN bookings b ON b.id = p.booking_id
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users r ON r.id = p.recorded_by
         WHERE p.paid_at >= ? AND p.paid_at < ?
         ORDER BY p.paid_at, p.id",
        "ss",
        [$yearStart, $nextYearStart]
    );
    foreach ($rows as &$r) {
        $r['method'] = PAYMENT_METHODS[$r['method']] ?? $r['method'];
    }
    unset($r);
    csv_download("payments-$year.csv", ['Paid on', 'Booking', 'Customer', 'Event date', 'Method', 'Payment reference', 'Amount', 'Recorded by'], $rows);
}

// ---- Chart scale: round the top of the axis up to a clean number ---------------
function nice_ceiling(float $value): float
{
    if ($value <= 0) {
        return 1000;
    }
    $magnitude = 10 ** floor(log10($value));
    foreach ([1, 2, 2.5, 5, 10] as $step) {
        if ($value <= $step * $magnitude) {
            return $step * $magnitude;
        }
    }
    return 10 * $magnitude;
}
function compact_peso(float $value): string
{
    if ($value >= 1000000) return CURRENCY . rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M';
    if ($value >= 10000) return CURRENCY . rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K';
    return CURRENCY . number_format($value);
}
$chartMax = nice_ceiling(max(array_column($months, 'value')));
$bestMonth = null; // the one month whose value gets a direct label
foreach ($months as $m => $row) {
    if ($row['value'] > 0 && ($bestMonth === null || $row['value'] > $months[$bestMonth]['value'])) {
        $bestMonth = $m;
    }
}
$itemMax = $topItems ? max(array_map(fn($i) => (float) $i['revenue'], $topItems)) : 0;

$pageTitle = "Reports | Admin";
$activePage = "admin";
$adminPage = "reports";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Reports <span class="muted"><?php echo $year; ?></span></h1>
    <div class="admin-head-actions">
      <form method="GET" class="inline-form">
        <label class="small" for="year">Year</label>
        <select id="year" name="year" onchange="this.form.submit()">
          <?php foreach ($years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn-ghost">Show</button></noscript>
      </form>
      <a class="btn-ghost" href="?<?php echo e(http_build_query(['year' => $year, 'export' => 'monthly'])); ?>">Monthly summary CSV</a>
      <a class="btn-ghost" href="?<?php echo e(http_build_query(['year' => $year, 'export' => 'payments'])); ?>">Payments CSV</a>
    </div>
  </div>

  <div class="stat-cards">
    <div class="stat-card">
      <span class="stat-card-value"><?php echo $totals['bookings']; ?></span>
      <span class="stat-card-label">Events booked</span>
    </div>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo peso($totals['value']); ?></span>
      <span class="stat-card-label">Booked value</span>
    </div>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo peso($totals['collected']); ?></span>
      <span class="stat-card-label">Collected</span>
    </div>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo peso($totals['average']); ?></span>
      <span class="stat-card-label">Average per event</span>
    </div>
    <div class="stat-card">
      <span class="stat-card-value"><?php echo round($totals['cancel_rate'] * 100); ?>%</span>
      <span class="stat-card-label">Cancelled (<?php echo $totals['cancelled']; ?>)</span>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">Booked value by month</h2>
    <p class="panel-note">Bookings counted in the month of the event, cancelled ones left out.<?php if ($bestMonth): ?> Busiest month: <strong><?php echo date('F', mktime(0, 0, 0, $bestMonth, 1)); ?></strong>.<?php endif; ?></p>

    <div class="rpt-chart" role="img" aria-label="Column chart of booked value per month in <?php echo $year; ?>. The same numbers are in the table below.">
      <div class="rpt-axis" aria-hidden="true">
        <span><?php echo compact_peso($chartMax); ?></span>
        <span><?php echo compact_peso($chartMax / 2); ?></span>
        <span><?php echo CURRENCY; ?>0</span>
      </div>
      <div class="rpt-plot">
        <?php foreach ($months as $m => $row): $pct = $chartMax > 0 ? $row['value'] / $chartMax * 100 : 0; ?>
          <div class="rpt-col" tabindex="0" aria-label="<?php echo e($row['label'] . ': ' . peso($row['value']) . ', ' . $row['bookings'] . ' events'); ?>">
            <?php if ($m === $bestMonth): ?><span class="rpt-col-value" style="bottom: <?php echo round($pct, 2); ?>%"><?php echo compact_peso($row['value']); ?></span><?php endif; ?>
            <span class="rpt-col-bar" style="height: <?php echo round($pct, 2); ?>%"></span>
            <span class="rpt-tip" role="tooltip" style="bottom: calc(<?php echo round(min($pct, 55), 2); ?>% + 0.6rem)">
              <strong><?php echo date('F', mktime(0, 0, 0, $m, 1)) . ' ' . $year; ?></strong>
              <span><?php echo peso($row['value']); ?> booked</span>
              <span><?php echo $row['bookings']; ?> event<?php echo $row['bookings'] === 1 ? '' : 's'; ?> · <?php echo peso($row['collected']); ?> collected</span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="rpt-months" aria-hidden="true">
        <?php foreach ($months as $row): ?><span><?php echo e($row['label']); ?></span><?php endforeach; ?>
      </div>
    </div>

    <details class="rpt-table-toggle">
      <summary>Show as a table</summary>
      <table class="data-table data-table--compact">
        <thead><tr><th>Month</th><th class="num">Events</th><th class="num">Booked value</th><th class="num">Cancelled</th><th class="num">Collected</th></tr></thead>
        <tbody>
          <?php foreach ($months as $m => $row): ?>
            <tr>
              <td><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></td>
              <td class="num"><?php echo $row['bookings']; ?></td>
              <td class="num"><?php echo peso($row['value']); ?></td>
              <td class="num"><?php echo $row['cancelled']; ?></td>
              <td class="num"><?php echo peso($row['collected']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="total-row"><td>Total</td><td class="num"><?php echo $totals['bookings']; ?></td><td class="num"><?php echo peso($totals['value']); ?></td><td class="num"><?php echo $totals['cancelled']; ?></td><td class="num"><?php echo peso($totals['collected']); ?></td></tr>
        </tfoot>
      </table>
    </details>
  </div>

  <div class="admin-grid">
    <div class="panel">
      <h2 class="panel-title">Top rental items</h2>
      <?php if ($topItems): ?>
        <div class="rpt-scroll">
        <table class="data-table rpt-bars">
          <thead><tr><th>Item</th><th class="num">Units</th><th class="num hide-sm">Events</th><th>Rental income</th></tr></thead>
          <tbody>
            <?php foreach ($topItems as $it): ?>
              <tr>
                <td><?php echo e($it['name']); ?><br /><span class="muted small"><?php echo e($it['category']); ?></span></td>
                <td class="num"><?php echo (int) $it['units']; ?></td>
                <td class="num hide-sm"><?php echo (int) $it['bookings']; ?></td>
                <td>
                  <div class="rpt-bar-cell">
                    <span class="rpt-hbar-track"><span class="rpt-hbar" style="width: <?php echo $itemMax > 0 ? round($it['revenue'] / $itemMax * 100, 2) : 0; ?>%"></span></span>
                    <span class="rpt-hbar-value"><?php echo peso($it['revenue']); ?></span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php else: ?>
        <p class="empty-state">No rentals booked for <?php echo $year; ?> yet.</p>
      <?php endif; ?>
    </div>

    <div class="admin-side">
      <div class="panel">
        <h2 class="panel-title">Hosts &amp; organizers</h2>
        <?php if ($serviceStats): ?>
          <table class="data-table data-table--compact">
            <thead><tr><th>Service</th><th class="num">Events</th><th class="num">Income</th></tr></thead>
            <tbody>
              <?php foreach ($serviceStats as $s): ?>
                <tr><td><?php echo e($s['name']); ?></td><td class="num"><?php echo (int) $s['bookings']; ?></td><td class="num"><?php echo peso($s['revenue']); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="empty-state">No services booked for <?php echo $year; ?> yet.</p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2 class="panel-title">Events by type</h2>
        <?php if ($eventTypes): ?>
          <table class="data-table data-table--compact">
            <thead><tr><th>Type</th><th class="num">Events</th><th class="num">Share</th></tr></thead>
            <tbody>
              <?php foreach ($eventTypes as $t): ?>
                <tr>
                  <td><?php echo e($t['event_type']); ?></td>
                  <td class="num"><?php echo (int) $t['bookings']; ?></td>
                  <td class="num"><?php echo $totals['bookings'] ? round($t['bookings'] / $totals['bookings'] * 100) : 0; ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="empty-state">No events for <?php echo $year; ?> yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
