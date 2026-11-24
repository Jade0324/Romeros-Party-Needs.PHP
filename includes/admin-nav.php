<?php
// Admin tab bar. Each admin page sets $adminPage before including this.
/** @var mysqli $conn */
$pendingCount = (int) db_value($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$newInquiryCount = (int) db_value($conn, "SELECT COUNT(*) FROM inquiries WHERE status = 'new'");

$adminTabs = [
    'dashboard'    => ['Dashboard', 'index.php', 0],
    'bookings'     => ['Bookings', 'bookings.php', $pendingCount],
    'calendar'     => ['Calendar', 'calendar.php', 0],
    'inventory'    => ['Inventory', 'inventory.php', 0],
    'services'     => ['Services', 'services.php', 0],
    'appointments' => ['Appointments', 'appointments.php', 0],
    'inquiries'    => ['Inquiries', 'inquiries.php', $newInquiryCount],
    'customers'    => ['Customers', 'customers.php', 0],
    'reports'      => ['Reports', 'reports.php', 0],
    'users'        => ['Accounts', 'users.php', 0],
    'system'       => ['System', 'system.php', 0],
];
?>
<div class="admin-bar">
  <div class="container">
    <span class="admin-bar-label">Admin</span>
    <nav class="admin-tabs" aria-label="Admin sections">
      <?php foreach ($adminTabs as $key => [$label, $href, $count]): ?>
        <a href="<?php echo url('admin/' . $href); ?>" class="admin-tab <?php echo ($adminPage ?? '') === $key ? 'active' : ''; ?>">
          <?php echo e($label); ?>
          <?php if ($count > 0): ?><span class="admin-count"><?php echo $count; ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
