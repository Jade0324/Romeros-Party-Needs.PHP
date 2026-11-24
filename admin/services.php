<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

/** The most times this service is booked on any single upcoming date. */
function max_future_service_use(mysqli $conn, int $serviceId): int
{
    return (int) db_value(
        $conn,
        "SELECT COALESCE(MAX(t.n), 0) FROM (
            SELECT COUNT(*) AS n FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id
            WHERE bs.service_id = ? AND b.status IN ('pending','confirmed') AND b.event_date >= ?
            GROUP BY b.event_date
         ) t",
        "is",
        [$serviceId, today()]
    );
}

$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $sid = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = post_str('name');
        $description = post_str('description');
        $price = round((float) ($_POST['price'] ?? -1), 2);
        $capacity = (int) ($_POST['daily_capacity'] ?? 0);
        $provider = post_str('provider') ?: 'South Flaneur Events';

        if ($name === '') $formErrors[] = 'Name is required.';
        if ($price < 0) $formErrors[] = 'Price must be zero or more.';
        if ($capacity < 1) $formErrors[] = 'Daily capacity must be at least 1.';
        if ($sid && $capacity >= 1 && $capacity < ($used = max_future_service_use($conn, $sid))) {
            $formErrors[] = "An upcoming date already has $used bookings for this service, so capacity can't go below $used.";
        }
        $current = $sid ? db_one($conn, "SELECT image FROM services WHERE id = ?", "i", [$sid]) : null;

        $upload = save_uploaded_image('photo');
        if (isset($upload['error'])) $formErrors[] = $upload['error'];

        if (!$formErrors) {
            // Keep the current photo unless a new one was uploaded or "Remove photo" was ticked.
            $image = $current['image'] ?? null;
            if ($upload['path']) {
                delete_uploaded_image($image);
                $image = $upload['path'];
            } elseif (!empty($_POST['remove_photo'])) {
                delete_uploaded_image($image);
                $image = null;
            }
            if ($sid) {
                db_exec($conn, "UPDATE services SET name = ?, description = ?, price = ?, daily_capacity = ?, provider = ?, image = ? WHERE id = ?",
                    "ssdissi", [$name, $description ?: null, $price, $capacity, $provider, $image, $sid]);
                flash('success', "$name updated.");
            } else {
                db_exec($conn, "INSERT INTO services (name, description, price, daily_capacity, provider, image) VALUES (?, ?, ?, ?, ?, ?)",
                    "ssdiss", [$name, $description ?: null, $price, $capacity, $provider, $image]);
                flash('success', "$name added.");
            }
            redirect('admin/services.php');
        } elseif ($upload['path'] ?? null) {
            delete_uploaded_image($upload['path']);
        }
    }

    if ($action === 'toggle' && $sid) {
        db_exec($conn, "UPDATE services SET is_active = 1 - is_active WHERE id = ?", "i", [$sid]);
        flash('success', 'Service visibility updated.');
        redirect('admin/services.php');
    }

    if ($action === 'delete' && $sid) {
        if (db_value($conn, "SELECT COUNT(*) FROM booking_services WHERE service_id = ?", "i", [$sid]) > 0) {
            flash('error', 'This service appears in bookings, so it can\'t be deleted. Hide it instead.');
        } else {
            delete_uploaded_image(db_value($conn, "SELECT image FROM services WHERE id = ?", "i", [$sid]));
            db_exec($conn, "DELETE FROM services WHERE id = ?", "i", [$sid]);
            flash('success', 'Service deleted.');
        }
        redirect('admin/services.php');
    }
}

$services = db_all(
    $conn,
    "SELECT s.*,
            (SELECT COUNT(*) FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.service_id = s.id AND b.status IN ('pending','confirmed') AND b.event_date >= ?) AS upcoming
     FROM services s ORDER BY s.price",
    "s",
    [today()]
);
$editing = null;
foreach ($services as $s) {
    if ((int) $s['id'] === (int) ($_GET['edit'] ?? 0)) $editing = $s;
}
// Start from empty defaults so every field exists even if the POST left one out.
$blank = ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'daily_capacity' => 1, 'provider' => 'South Flaneur Events', 'image' => ''];
$form = array_merge($blank, $formErrors ? $_POST : ($editing ?? []));
if ($formErrors && !empty($_POST['id'])) {
    $form['image'] = db_value($conn, "SELECT image FROM services WHERE id = ?", "i", [(int) $_POST['id']]);
}

$pageTitle = "Services | Admin";
$activePage = "admin";
$adminPage = "services";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head"><h1 class="admin-title">Services</h1></div>

  <div class="admin-grid admin-grid--wide-left">
    <div class="panel panel--flush">
      <table class="data-table">
        <thead><tr><th>Service</th><th class="num">Price</th><th class="num">Per day</th><th class="num">Upcoming</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($services as $s): ?>
            <tr class="<?php echo $s['is_active'] ? '' : 'row-muted'; ?>">
              <td><strong><?php echo e($s['name']); ?></strong><?php if (!$s['is_active']): ?> <span class="status-badge status-cancelled">Hidden</span><?php endif; ?><br /><span class="muted small"><?php echo e($s['provider']); ?> · <?php echo e($s['description']); ?></span></td>
              <td class="num"><?php echo peso($s['price']); ?></td>
              <td class="num"><?php echo (int) $s['daily_capacity']; ?></td>
              <td class="num"><?php echo (int) $s['upcoming']; ?></td>
              <td class="row-actions">
                <a href="?edit=<?php echo (int) $s['id']; ?>">Edit</a>
                <form method="POST" class="inline-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>" />
                  <button class="link-btn"><?php echo $s['is_active'] ? 'Hide' : 'Show'; ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?php echo !empty($form['id']) ? 'Edit service' : 'Add a service'; ?></h2>
      <?php if ($formErrors): ?>
        <div class="form-errors"><ul><?php foreach ($formErrors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>" />
        <div class="form-row"><label>Name</label><input type="text" name="name" required maxlength="100" value="<?php echo e($form['name']); ?>" /></div>
        <div class="form-row"><label>Description</label><input type="text" name="description" maxlength="255" value="<?php echo e($form['description']); ?>" /></div>
        <div class="time-row">
          <div class="form-row"><label>Price (<?php echo CURRENCY; ?>)</label><input type="number" name="price" min="0" step="0.01" required value="<?php echo e($form['price']); ?>" /></div>
          <div class="form-row"><label>Events per day</label><input type="number" name="daily_capacity" min="1" required value="<?php echo e($form['daily_capacity']); ?>" /></div>
        </div>
        <div class="form-row"><label>Provider</label><input type="text" name="provider" maxlength="100" value="<?php echo e($form['provider']); ?>" /></div>
        <?php include __DIR__ . '/../includes/photo-field.php'; ?>
        <p class="label-hint">"Events per day" is how many events the team can cover on the same date. Once it's reached, the service shows as fully booked for that date.</p>
        <button type="submit" class="btn-primary"><?php echo !empty($form['id']) ? 'Save changes' : 'Add service'; ?></button>
        <?php if (!empty($form['id'])): ?><a href="services.php" class="small" style="margin-left:.75rem">Cancel</a><?php endif; ?>
      </form>

      <?php if (!empty($form['id'])): ?>
        <form method="POST" class="delete-form" data-confirm="Delete this service permanently?">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
          <button class="btn-danger-link">Delete service</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
