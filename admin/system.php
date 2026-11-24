<?php
// ==========================================================================
// System check — open this right after deploying to a live server (or after
// cloning) to confirm PHP, the database, uploads and security settings are
// all set up correctly. Read-only: it doesn't change anything.
// ==========================================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$checks = []; // [group, label, status ok|warn|fail, detail]
function check(string $group, string $label, string $status, string $detail = ''): void
{
    global $checks;
    $checks[] = compact('group', 'label', 'status', 'detail');
}

// ---- PHP ---------------------------------------------------------------
check('Server', 'PHP version', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail', PHP_VERSION . ' (needs 8.0 or newer)');
foreach (['mysqli' => 'database access', 'fileinfo' => 'checking uploaded photos', 'mbstring' => 'text handling', 'json' => 'availability API'] as $ext => $why) {
    check('Server', "PHP extension: $ext", extension_loaded($ext) ? 'ok' : 'fail', $why);
}
check('Server', 'Upload size limit', 'ok', 'upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size'));
check('Server', 'Time zone', date_default_timezone_get() === 'Asia/Manila' ? 'ok' : 'warn', date_default_timezone_get() . ' · server time ' . date('M j, Y g:i A'));

// ---- Database ------------------------------------------------------------
$dbInfo = db_one($conn, "SELECT VERSION() AS v, DATABASE() AS db, NOW() AS now_db");
check('Database', 'Connection', 'ok', 'Connected to "' . $dbInfo['db'] . '" on ' . $dbInfo['v']);
$drift = abs(strtotime($dbInfo['now_db']) - time());
check('Database', 'Database clock', $drift < 3600 ? 'ok' : 'warn', $drift < 3600 ? 'Matches the PHP clock' : 'Differs from PHP by ' . round($drift / 3600, 1) . ' h. Demo data uses the database date; bookings use PHP time.');

$required = [
    'users' => ['is_active', 'last_login_at'], 'login_attempts' => [], 'categories' => [], 'inventory_items' => ['image'],
    'services' => [], 'blocked_dates' => [], 'bookings' => [], 'booking_items' => [], 'booking_services' => [],
    'payments' => [], 'booking_logs' => [], 'appointments' => [], 'inquiries' => ['user_id', 'status'],
];
$tables = array_column(db_all($conn, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"), 'TABLE_NAME');
$missingTables = array_diff(array_keys($required), $tables);
check('Database', 'Tables', $missingTables ? 'fail' : 'ok',
    $missingTables ? 'Missing: ' . implode(', ', $missingTables) . '. Import db_setup/db.sql (or the migrations).' : count($required) . ' of ' . count($required) . ' tables present');

$missingCols = [];
foreach ($required as $table => $cols) {
    if (!in_array($table, $tables, true)) continue;
    foreach ($cols as $col) {
        $has = db_value($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", "ss", [$table, $col]);
        if (!$has) $missingCols[] = "$table.$col";
    }
}
check('Database', 'Schema up to date', $missingCols ? 'fail' : 'ok',
    $missingCols ? 'Missing columns: ' . implode(', ', $missingCols) . '. Run the files in db_setup/migrations/.' : 'All columns used by the app exist');

$counts = [];
foreach (['users', 'inventory_items', 'services', 'bookings', 'appointments', 'payments', 'inquiries'] as $t) {
    if (in_array($t, $tables, true)) $counts[$t] = (int) db_value($conn, "SELECT COUNT(*) FROM `$t`");
}

// ---- Files ---------------------------------------------------------------
$uploadDir = __DIR__ . '/../' . UPLOAD_DIR;
check('Files', 'Uploads folder', is_dir($uploadDir) && is_writable($uploadDir) ? 'ok' : 'fail',
    UPLOAD_DIR . (is_dir($uploadDir) ? (is_writable($uploadDir) ? ' is writable' : ' exists but is NOT writable (set permissions to 755)') : ' is missing'));
foreach (['includes/.htaccess', 'db_setup/.htaccess', UPLOAD_DIR . '.htaccess', '.htaccess'] as $f) {
    check('Files', "Protection file $f", file_exists(__DIR__ . '/../' . $f) ? 'ok' : 'fail', file_exists(__DIR__ . '/../' . $f) ? 'Present' : 'Missing — upload it from the repository');
}
check('Files', 'Local settings file', file_exists(__DIR__ . '/../includes/db.local.php') ? 'ok' : 'warn',
    file_exists(__DIR__ . '/../includes/db.local.php') ? 'includes/db.local.php is in use' : 'Not used — settings come from includes/db.php');

// ---- Security --------------------------------------------------------------
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
check('Security', 'Environment', APP_ENV === 'production' || $isLocal ? 'ok' : 'warn',
    APP_ENV . ($isLocal ? ' (local computer)' : (APP_ENV === 'production' ? '' : ' — set $app_env = "production" in db.local.php so visitors never see PHP errors')));
check('Security', 'Error display', ini_get('display_errors') && !$isLocal ? 'warn' : 'ok', ini_get('display_errors') ? 'PHP errors are shown on pages' : 'PHP errors are hidden and logged');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
check('Security', 'HTTPS', $https || $isLocal ? 'ok' : 'warn', $https ? 'Site is using HTTPS' : ($isLocal ? 'Not needed on localhost' : 'Turn on free SSL in your hosting panel so passwords are encrypted'));

$weakAdmins = [];
foreach (db_all($conn, "SELECT full_name, email, password FROM users WHERE role = 'admin' AND is_active = 1") as $a) {
    foreach (['admin123', 'password', '123456', 'admin'] as $weak) {
        if (password_verify($weak, $a['password'])) { $weakAdmins[] = $a['email']; break; }
    }
}
check('Security', 'Admin passwords', $weakAdmins ? ($isLocal ? 'warn' : 'fail') : 'ok',
    $weakAdmins ? 'Still using a demo/weak password: ' . implode(', ', $weakAdmins) . '. Change it in Accounts before going live.' : 'No admin uses a known demo password');
check('Security', 'Active admins', 'ok', (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1") . ' active admin account(s)');

// ---- App ----------------------------------------------------------------
check('App', 'Site address (BASE_URL)', 'ok', BASE_URL);
check('App', 'Business rules', 'ok', 'Delivery ' . peso(DELIVERY_FEE) . ' · ' . MAX_BOOKINGS_PER_DAY . ' setups/day · ' . MIN_LEAD_DAYS . ' days notice · ' . (int) (DOWN_PAYMENT_RATE * 100) . '% down payment');

$summary = array_count_values(array_column($checks, 'status'));
$grouped = [];
foreach ($checks as $c) $grouped[$c['group']][] = $c;

$pageTitle = "System Check | Admin";
$activePage = "admin";
$adminPage = "system";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <div>
      <h1 class="admin-title">System check</h1>
      <p class="muted">Run this after installing or deploying. It only reads settings; it doesn't change anything.</p>
    </div>
    <div class="check-summary">
      <span class="status-badge status-completed"><?php echo $summary['ok'] ?? 0; ?> OK</span>
      <span class="status-badge status-pending"><?php echo $summary['warn'] ?? 0; ?> warnings</span>
      <span class="status-badge status-unpaid"><?php echo $summary['fail'] ?? 0; ?> problems</span>
    </div>
  </div>

  <div class="admin-grid">
    <div>
      <?php foreach ($grouped as $group => $items): ?>
        <div class="panel panel--flush">
          <h2 class="panel-title check-group-title"><?php echo e($group); ?></h2>
          <table class="data-table">
            <tbody>
              <?php foreach ($items as $c): ?>
                <tr>
                  <td class="check-status"><span class="check-dot check-<?php echo $c['status']; ?>" aria-label="<?php echo $c['status']; ?>"></span></td>
                  <td><strong><?php echo e($c['label']); ?></strong></td>
                  <td class="small"><?php echo e($c['detail']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title">Records</h2>
      <dl class="detail-list detail-list--compact">
        <?php foreach ($counts as $t => $n): ?>
          <div><dt><?php echo e(ucwords(str_replace('_', ' ', $t))); ?></dt><dd><?php echo $n; ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <p class="fine-print">Deploying to a live server? Follow <strong>docs/DEPLOYMENT.md</strong> in the repository, then come back to this page until everything is green.</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
