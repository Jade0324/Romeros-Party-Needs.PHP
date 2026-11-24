<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

$me = current_user_id();
$formErrors = [];

/** How many active admins would remain if $exceptId stopped being one. */
function other_active_admins(mysqli $conn, int $exceptId): int
{
    return (int) db_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", "i", [$exceptId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $uid = (int) ($_POST['id'] ?? 0);

    // ---- Create or update an account -----------------------------------
    if ($action === 'save') {
        $name = post_str('full_name');
        $email = strtolower(post_str('email'));
        $phone = post_str('phone');
        $role = in_array($_POST['role'] ?? '', ['customer', 'admin'], true) ? $_POST['role'] : 'customer';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $existing = $uid ? db_one($conn, "SELECT * FROM users WHERE id = ?", "i", [$uid]) : null;

        if ($uid && !$existing) $formErrors[] = 'That account no longer exists.';
        if ($name === '') $formErrors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $formErrors[] = 'Enter a valid email address.';
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) $formErrors[] = 'Enter a valid phone number.';
        if (!$uid && strlen($password) < 6) $formErrors[] = 'Set a password of at least 6 characters for the new account.';
        if ($uid && $password !== '' && strlen($password) < 6) $formErrors[] = 'A new password must be at least 6 characters.';
        if (db_value($conn, "SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?", "si", [$email, $uid]) > 0) {
            $formErrors[] = 'Another account already uses that email.';
        }

        // Safety rules so the business can never lock itself out.
        if ($uid === $me && ($role !== 'admin' || !$active)) {
            $formErrors[] = 'You can\'t remove your own admin access or deactivate yourself. Ask another admin.';
        }
        if ($existing && $existing['role'] === 'admin' && ($role !== 'admin' || !$active) && other_active_admins($conn, $uid) === 0) {
            $formErrors[] = 'This is the last active admin account, so it must stay an active admin.';
        }

        if (!$formErrors) {
            if ($uid) {
                db_exec($conn, "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE id = ?",
                    "ssssii", [$name, $email, $phone ?: null, $role, $active, $uid]);
                if ($password !== '') {
                    db_exec($conn, "UPDATE users SET password = ? WHERE id = ?", "si", [password_hash($password, PASSWORD_DEFAULT), $uid]);
                }
                $changes = [];
                if ($existing['role'] !== $role) $changes[] = "role is now $role";
                if ((int) $existing['is_active'] !== $active) $changes[] = $active ? 'reactivated' : 'deactivated';
                if ($password !== '') $changes[] = 'password reset';
                flash('success', "$name updated" . ($changes ? ' (' . implode(', ', $changes) . ')' : '') . '.');
            } else {
                db_exec($conn, "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                    "sssssi", [$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
                flash('success', "Account created for $name. Share the password with them privately.");
            }
            redirect('admin/users.php');
        }
    }

    // ---- Quick activate / deactivate -----------------------------------
    if ($action === 'toggle' && $uid) {
        $target = db_one($conn, "SELECT * FROM users WHERE id = ?", "i", [$uid]);
        if (!$target) {
            flash('error', 'Account not found.');
        } elseif ($uid === $me) {
            flash('error', 'You can\'t deactivate your own account.');
        } elseif ($target['role'] === 'admin' && $target['is_active'] && other_active_admins($conn, $uid) === 0) {
            flash('error', 'This is the last active admin account.');
        } else {
            db_exec($conn, "UPDATE users SET is_active = 1 - is_active WHERE id = ?", "i", [$uid]);
            flash('success', $target['full_name'] . ($target['is_active'] ? ' deactivated. They can no longer log in.' : ' reactivated.'));
        }
        redirect('admin/users.php?' . http_build_query(array_filter(['role' => $_POST['f_role'] ?? '', 'status' => $_POST['f_status'] ?? '', 'q' => $_POST['f_q'] ?? ''])));
    }

    // ---- Delete (only accounts with no history) --------------------------
    if ($action === 'delete' && $uid) {
        $history = (int) db_value($conn, "SELECT (SELECT COUNT(*) FROM bookings WHERE user_id = ?) + (SELECT COUNT(*) FROM appointments WHERE user_id = ?)", "ii", [$uid, $uid]);
        if ($uid === $me) {
            flash('error', 'You can\'t delete your own account.');
        } elseif ($history > 0) {
            flash('error', 'This account has bookings or appointments, so it is kept for records. Deactivate it instead.');
        } elseif (db_value($conn, "SELECT role FROM users WHERE id = ?", "i", [$uid]) === 'admin' && other_active_admins($conn, $uid) === 0) {
            flash('error', 'This is the last active admin account.');
        } else {
            db_exec($conn, "DELETE FROM users WHERE id = ?", "i", [$uid]);
            flash('success', 'Account deleted.');
        }
        redirect('admin/users.php');
    }
}

// ---- List ---------------------------------------------------------------
$fRole = in_array($_GET['role'] ?? '', ['customer', 'admin'], true) ? $_GET['role'] : '';
$fStatus = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? '');

$where = ['(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'];
$like = "%$q%";
$types = 'sss';
$params = [$like, $like, $like];
if ($fRole) { $where[] = 'u.role = ?'; $types .= 's'; $params[] = $fRole; }
if ($fStatus) { $where[] = 'u.is_active = ?'; $types .= 'i'; $params[] = $fStatus === 'active' ? 1 : 0; }

$users = db_all(
    $conn,
    "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count
     FROM users u WHERE " . implode(' AND ', $where) . "
     ORDER BY u.role = 'admin' DESC, u.is_active DESC, u.full_name",
    $types,
    $params
);

$editing = null;
if (!empty($_GET['edit'])) {
    $editing = db_one($conn, "SELECT * FROM users WHERE id = ?", "i", [(int) $_GET['edit']]);
}
$blank = ['id' => 0, 'full_name' => '', 'email' => '', 'phone' => '', 'role' => 'customer', 'is_active' => 1];
$form = array_merge($blank, $formErrors ? $_POST : ($editing ?? []));
if ($formErrors) {
    $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
}
$isEdit = (int) $form['id'] > 0;
$editingSelf = $isEdit && (int) $form['id'] === $me;

$counts = db_one($conn, "SELECT SUM(role = 'admin') AS admins, SUM(role = 'customer') AS customers, SUM(is_active = 0) AS inactive FROM users");

$pageTitle = "Accounts | Admin";
$activePage = "admin";
$adminPage = "users";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Accounts
      <span class="muted small"><?php echo (int) $counts['admins']; ?> admin · <?php echo (int) $counts['customers']; ?> customers<?php if ($counts['inactive']): ?> · <?php echo (int) $counts['inactive']; ?> deactivated<?php endif; ?></span>
    </h1>
    <?php if ($isEdit): ?><a class="btn-primary" href="users.php">+ New account</a><?php endif; ?>
  </div>

  <form class="filter-bar" method="GET">
    <input type="search" name="q" placeholder="Name, email or phone" value="<?php echo e($q); ?>" />
    <select name="role">
      <option value="">All roles</option>
      <option value="customer" <?php echo $fRole === 'customer' ? 'selected' : ''; ?>>Customers</option>
      <option value="admin" <?php echo $fRole === 'admin' ? 'selected' : ''; ?>>Admins</option>
    </select>
    <select name="status">
      <option value="">Any status</option>
      <option value="active" <?php echo $fStatus === 'active' ? 'selected' : ''; ?>>Active</option>
      <option value="inactive" <?php echo $fStatus === 'inactive' ? 'selected' : ''; ?>>Deactivated</option>
    </select>
    <button class="btn-ghost">Filter</button>
    <?php if ($q || $fRole || $fStatus): ?><a class="small" href="users.php">Clear</a><?php endif; ?>
  </form>

  <div class="admin-grid admin-grid--wide-left">
    <div class="panel panel--flush">
      <?php if ($users): ?>
        <table class="data-table data-table--compact">
          <thead><tr><th>Account</th><th>Role</th><th>Status</th><th class="num">Bookings</th><th>Last login</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): $isMe = (int) $u['id'] === $me; ?>
              <tr class="<?php echo $u['is_active'] ? '' : 'row-muted'; ?>">
                <td>
                  <strong><?php echo e($u['full_name']); ?></strong><?php if ($isMe): ?> <span class="muted small">(you)</span><?php endif; ?><br />
                  <span class="muted small"><?php echo e($u['email']); ?><?php if ($u['phone']): ?><br /><?php echo e($u['phone']); ?><?php endif; ?></span>
                </td>
                <td><span class="role-badge role-<?php echo e($u['role']); ?>"><?php echo e(ucfirst($u['role'])); ?></span></td>
                <td><?php echo $u['is_active'] ? '<span class="status-badge status-completed">Active</span>' : '<span class="status-badge status-cancelled">Deactivated</span>'; ?></td>
                <td class="num">
                  <?php if ($u['booking_count']): ?><a href="bookings.php?user=<?php echo (int) $u['id']; ?>"><?php echo (int) $u['booking_count']; ?></a><?php else: ?>0<?php endif; ?>
                </td>
                <td class="small nowrap"><?php echo $u['last_login_at'] ? fmt_date($u['last_login_at'], 'M j, Y') . '<br /><span class="muted">' . fmt_date($u['last_login_at'], 'g:i A') . '</span>' : '<span class="muted">Never</span>'; ?></td>
                <td class="row-actions"><div class="action-stack">
                  <a href="?edit=<?php echo (int) $u['id']; ?>">Edit</a>
                  <?php if (!$isMe): ?>
                    <form method="POST" class="inline-form">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="toggle" />
                      <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
                      <input type="hidden" name="f_role" value="<?php echo e($fRole); ?>" />
                      <input type="hidden" name="f_status" value="<?php echo e($fStatus); ?>" />
                      <input type="hidden" name="f_q" value="<?php echo e($q); ?>" />
                      <button class="link-btn <?php echo $u['is_active'] ? 'link-danger' : ''; ?>"
                        <?php if ($u['is_active']): ?>data-confirm="Deactivate <?php echo e($u['full_name']); ?>? They won't be able to log in."<?php endif; ?>>
                        <?php echo $u['is_active'] ? 'Deactivate' : 'Reactivate'; ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty-state">No accounts match.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?php echo $isEdit ? 'Edit account' : 'Create an account'; ?></h2>
      <?php if ($formErrors): ?>
        <div class="form-errors"><ul><?php foreach ($formErrors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST" autocomplete="off">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
        <div class="form-row"><label>Full name</label><input type="text" name="full_name" required maxlength="100" value="<?php echo e($form['full_name']); ?>" /></div>
        <div class="form-row"><label>Email</label><input type="email" name="email" required maxlength="150" value="<?php echo e($form['email']); ?>" /></div>
        <div class="form-row"><label>Mobile number</label><input type="tel" name="phone" maxlength="30" value="<?php echo e($form['phone']); ?>" /></div>
        <div class="form-row">
          <label>Role</label>
          <select name="role" <?php echo $editingSelf ? 'disabled' : ''; ?>>
            <option value="customer" <?php echo $form['role'] === 'customer' ? 'selected' : ''; ?>>Customer — can book and track events</option>
            <option value="admin" <?php echo $form['role'] === 'admin' ? 'selected' : ''; ?>>Admin — full access to this back office</option>
          </select>
          <?php if ($editingSelf): ?><input type="hidden" name="role" value="admin" /><span class="label-hint">You can't change your own role.</span><?php endif; ?>
        </div>
        <div class="form-row">
          <label for="password"><?php echo $isEdit ? 'Reset password' : 'Password'; ?> <span class="label-hint"><?php echo $isEdit ? '(leave blank to keep the current one)' : '(at least 6 characters)'; ?></span></label>
          <input type="text" id="password" name="password" minlength="6" autocomplete="new-password" <?php echo $isEdit ? '' : 'required'; ?> />
        </div>
        <label class="check-row">
          <input type="checkbox" name="is_active" value="1" <?php echo $form['is_active'] ? 'checked' : ''; ?> <?php echo $editingSelf ? 'disabled' : ''; ?> />
          Account is active (can log in)
        </label>
        <?php if ($editingSelf): ?><input type="hidden" name="is_active" value="1" /><?php endif; ?>
        <button type="submit" class="btn-primary"><?php echo $isEdit ? 'Save changes' : 'Create account'; ?></button>
        <?php if ($isEdit): ?><a href="users.php" class="small" style="margin-left:.75rem">Cancel</a><?php endif; ?>
      </form>

      <?php if ($isEdit && !$editingSelf): ?>
        <form method="POST" class="delete-form" data-confirm="Delete this account permanently?">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
          <button class="btn-danger-link">Delete account</button>
          <span class="label-hint">Only for accounts with no bookings or appointments.</span>
        </form>
      <?php endif; ?>

      <p class="fine-print">Admins can see every booking, payment and customer detail. Only give admin access to people running the business.</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
