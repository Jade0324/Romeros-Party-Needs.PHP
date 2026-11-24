<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

$customers = db_all(
    $conn,
    "SELECT u.id, u.full_name, u.email, u.phone, u.created_at,
            COUNT(b.id) AS bookings,
            SUM(b.status IN ('pending','confirmed') AND b.event_date >= ?) AS active,
            MAX(b.event_date) AS last_event,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN bookings b2 ON b2.id = p.booking_id WHERE b2.user_id = u.id) AS total_paid
     FROM users u
     LEFT JOIN bookings b ON b.user_id = u.id
     WHERE u.role = 'customer' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
     GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at
     ORDER BY MAX(b.event_date) IS NULL, MAX(b.event_date) DESC, u.full_name",
    "ssss",
    [today(), $like, $like, $like]
);

$pageTitle = "Customers | Admin";
$activePage = "admin";
$adminPage = "customers";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Customers <span class="muted">(<?php echo count($customers); ?>)</span></h1>
    <form method="GET" class="filter-bar">
      <input type="search" name="q" placeholder="Name, email or phone" value="<?php echo e($q); ?>" />
      <button class="btn-ghost">Search</button>
    </form>
  </div>

  <div class="panel panel--flush">
    <?php if ($customers): ?>
      <table class="data-table">
        <thead><tr><th>Customer</th><th>Contact</th><th class="num">Bookings</th><th class="num">Active</th><th>Latest event</th><th class="num">Total paid</th><th>Since</th></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><a href="bookings.php?user=<?php echo (int) $c['id']; ?>"><strong><?php echo e($c['full_name']); ?></strong></a></td>
              <td class="small"><a href="mailto:<?php echo e($c['email']); ?>"><?php echo e($c['email']); ?></a><br /><a href="tel:<?php echo e($c['phone']); ?>"><?php echo e($c['phone']); ?></a></td>
              <td class="num"><?php echo (int) $c['bookings']; ?></td>
              <td class="num"><?php echo (int) $c['active']; ?></td>
              <td><?php echo fmt_date($c['last_event'], 'M j, Y'); ?></td>
              <td class="num"><?php echo peso($c['total_paid']); ?></td>
              <td class="small muted"><?php echo fmt_date($c['created_at'], 'M Y'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-state">No customers found.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
