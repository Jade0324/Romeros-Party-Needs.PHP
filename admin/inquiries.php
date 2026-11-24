<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $to = $_POST['to'] ?? '';
    if (in_array($to, ['new', 'replied', 'closed'], true)) {
        db_exec($conn, "UPDATE inquiries SET status = ? WHERE id = ?", "si", [$to, (int) ($_POST['id'] ?? 0)]);
        flash('success', "Inquiry marked $to.");
    }
    redirect('admin/inquiries.php?' . http_build_query(['status' => $_POST['filter'] ?? '']));
}

$status = $_GET['status'] ?? '';
$validStatus = in_array($status, ['new', 'replied', 'closed'], true);

// Match inquiries to an account by user_id, or by email for guests who
// later signed up — so the admin can jump straight to their bookings.
$inquiries = db_all(
    $conn,
    "SELECT q.*, COALESCE(q.user_id, u.id) AS account_id,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = COALESCE(q.user_id, u.id)) AS booking_count
     FROM inquiries q
     LEFT JOIN users u ON u.email = q.email
     " . ($validStatus ? "WHERE q.status = ?" : "") . "
     ORDER BY q.status = 'new' DESC, q.created_at DESC LIMIT 300",
    $validStatus ? "s" : "",
    $validStatus ? [$status] : []
);

$pageTitle = "Inquiries | Admin";
$activePage = "admin";
$adminPage = "inquiries";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head"><h1 class="admin-title">Inquiries</h1></div>
  <div class="pill-row">
    <a class="pill <?php echo !$validStatus ? 'active' : ''; ?>" href="inquiries.php">All</a>
    <?php foreach (['new', 'replied', 'closed'] as $s): ?>
      <a class="pill <?php echo $status === $s ? 'active' : ''; ?>" href="?status=<?php echo $s; ?>"><?php echo ucfirst($s); ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($inquiries): ?>
    <div class="inquiry-list">
      <?php foreach ($inquiries as $q): ?>
        <article class="panel inquiry <?php echo $q['status'] === 'new' ? 'is-new' : ''; ?>">
          <header class="row-between">
            <div>
              <strong><?php echo e($q['name']); ?></strong> <?php echo status_badge($q['status']); ?><br />
              <span class="muted small">
                <a href="mailto:<?php echo e($q['email']); ?>?subject=<?php echo rawurlencode("Re: your inquiry with Romero's Party Needs"); ?>"><?php echo e($q['email']); ?></a>
                <?php if ($q['phone']): ?> · <a href="tel:<?php echo e($q['phone']); ?>"><?php echo e($q['phone']); ?></a><?php endif; ?>
                · sent <?php echo fmt_date($q['created_at'], 'M j, Y g:i A'); ?>
                <?php if ($q['event_date']): ?> · event date <?php echo fmt_date($q['event_date'], 'M j, Y'); ?><?php endif; ?>
              </span>
            </div>
            <form method="POST" class="inline-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo (int) $q['id']; ?>" />
              <input type="hidden" name="filter" value="<?php echo e($status); ?>" />
              <?php if ($q['status'] !== 'replied'): ?><button name="to" value="replied" class="link-btn">Mark replied</button><?php endif; ?>
              <?php if ($q['status'] !== 'closed'): ?><button name="to" value="closed" class="link-btn">Close</button><?php endif; ?>
              <?php if ($q['status'] !== 'new'): ?><button name="to" value="new" class="link-btn">Reopen</button><?php endif; ?>
            </form>
          </header>
          <p class="inquiry-body"><?php echo nl2br(e($q['inquiry'])); ?></p>
          <?php if ($q['account_id']): ?>
            <p class="small"><a href="bookings.php?user=<?php echo (int) $q['account_id']; ?>">Registered customer · <?php echo (int) $q['booking_count']; ?> booking(s) &rarr;</a></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="empty-state">No inquiries here.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
