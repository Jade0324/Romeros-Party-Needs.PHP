<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

/** The most of this item held by bookings on any single upcoming date. */
function max_future_reserved(mysqli $conn, int $itemId): int
{
    return (int) db_value(
        $conn,
        "SELECT COALESCE(MAX(t.reserved), 0) FROM (
            SELECT SUM(bi.quantity) AS reserved
            FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id
            WHERE bi.item_id = ? AND b.status IN ('pending','confirmed') AND b.event_date >= ?
            GROUP BY b.event_date
         ) t",
        "is",
        [$itemId, today()]
    );
}

$categories = db_all($conn, "SELECT * FROM categories ORDER BY sort_order");
$categoryIds = array_map('intval', array_column($categories, 'id'));
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $itemId = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = post_str('name');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $description = post_str('description');
        $unit = post_str('unit') ?: 'piece';
        $price = round((float) ($_POST['price'] ?? -1), 2);
        $qty = (int) ($_POST['total_quantity'] ?? -1);

        if ($name === '') $formErrors[] = 'Name is required.';
        if (!in_array($categoryId, $categoryIds, true)) $formErrors[] = 'Choose a category.';
        if ($price < 0) $formErrors[] = 'Price must be zero or more.';
        if ($qty < 0) $formErrors[] = 'Quantity must be zero or more.';
        if ($itemId && $qty >= 0 && $qty < ($held = max_future_reserved($conn, $itemId))) {
            $formErrors[] = "Upcoming bookings already hold $held of this item on one date, so the total can't go below $held.";
        }
        $current = $itemId ? db_one($conn, "SELECT image FROM inventory_items WHERE id = ?", "i", [$itemId]) : null;

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
            if ($itemId) {
                db_exec(
                    $conn,
                    "UPDATE inventory_items SET name = ?, category_id = ?, description = ?, unit = ?, price = ?, total_quantity = ?, image = ? WHERE id = ?",
                    "sissdisi",
                    [$name, $categoryId, $description ?: null, $unit, $price, $qty, $image, $itemId]
                );
                flash('success', "$name updated.");
            } else {
                db_exec(
                    $conn,
                    "INSERT INTO inventory_items (name, category_id, description, unit, price, total_quantity, image) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    "sissdis",
                    [$name, $categoryId, $description ?: null, $unit, $price, $qty, $image]
                );
                flash('success', "$name added to inventory.");
            }
            redirect('admin/inventory.php');
        } elseif ($upload['path'] ?? null) {
            delete_uploaded_image($upload['path']); // failed save: don't keep the new photo
        }
    }

    if ($action === 'toggle' && $itemId) {
        db_exec($conn, "UPDATE inventory_items SET is_active = 1 - is_active WHERE id = ?", "i", [$itemId]);
        flash('success', 'Item visibility updated. Existing bookings are not affected.');
        redirect('admin/inventory.php');
    }

    if ($action === 'delete' && $itemId) {
        if (db_value($conn, "SELECT COUNT(*) FROM booking_items WHERE item_id = ?", "i", [$itemId]) > 0) {
            flash('error', 'This item appears in bookings, so it can\'t be deleted. Hide it instead.');
        } else {
            delete_uploaded_image(db_value($conn, "SELECT image FROM inventory_items WHERE id = ?", "i", [$itemId]));
            db_exec($conn, "DELETE FROM inventory_items WHERE id = ?", "i", [$itemId]);
            flash('success', 'Item deleted.');
        }
        redirect('admin/inventory.php');
    }
}

$viewDate = clean_date($_GET['date'] ?? '') ?: today();
$items = get_item_availability($conn, $viewDate, 0, false);

$editing = null;
if (!empty($_GET['edit']) && isset($items[(int) $_GET['edit']])) {
    $editing = $items[(int) $_GET['edit']];
}
// On a failed save, show what they typed.
// Start from empty defaults so every field exists even if the POST left one out.
$blank = ['id' => 0, 'name' => '', 'category_id' => '', 'description' => '', 'unit' => 'piece', 'price' => '', 'total_quantity' => '', 'image' => null];
$form = array_merge($blank, $formErrors ? $_POST : ($editing ?? []));
if ($formErrors && !empty($_POST['id'])) {
    $form['image'] = db_value($conn, "SELECT image FROM inventory_items WHERE id = ?", "i", [(int) $_POST['id']]);
}

$pageTitle = "Inventory | Admin";
$activePage = "admin";
$adminPage = "inventory";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <div class="admin-head">
    <h1 class="admin-title">Inventory <a class="small admin-title-link" href="categories.php">Manage categories &rarr;</a></h1>
    <form method="GET" class="filter-bar">
      <label>Availability on <input type="date" name="date" value="<?php echo e($viewDate); ?>" onchange="this.form.submit()" /></label>
    </form>
  </div>

  <div class="admin-grid admin-grid--wide-left">
    <div class="panel panel--flush">
      <table class="data-table">
        <thead>
          <tr><th>Item</th><th class="num">Price</th><th class="num">Owned</th><th class="num">Booked <?php echo fmt_date($viewDate, 'M j'); ?></th><th class="num">Free</th><th></th></tr>
        </thead>
        <tbody>
          <?php $lastCat = null; foreach ($items as $it): ?>
            <?php if ($lastCat !== $it['category_name']): $lastCat = $it['category_name']; ?>
              <tr class="group-row"><td colspan="6"><?php echo e($lastCat); ?></td></tr>
            <?php endif; ?>
            <tr class="<?php echo $it['is_active'] ? '' : 'row-muted'; ?>">
              <td><?php if ($it['image']): ?><img class="thumb thumb--inline" src="<?php echo e(url($it['image'])); ?>" alt="" /><?php endif; ?><strong><?php echo e($it['name']); ?></strong><?php if (!$it['is_active']): ?> <span class="status-badge status-cancelled">Hidden</span><?php endif; ?><br /><span class="muted small"><?php echo e($it['description']); ?></span></td>
              <td class="num"><?php echo peso($it['price']); ?><br /><span class="muted small">/ <?php echo e($it['unit']); ?></span></td>
              <td class="num"><?php echo (int) $it['total_quantity']; ?></td>
              <td class="num"><?php echo $it['reserved']; ?></td>
              <td class="num"><strong class="<?php echo $it['available'] == 0 ? 'text-danger' : ''; ?>"><?php echo $it['available']; ?></strong>
                <div class="stock-bar"><span style="width: <?php echo $it['total_quantity'] ? round($it['reserved'] / $it['total_quantity'] * 100) : 0; ?>%"></span></div>
              </td>
              <td class="row-actions">
                <a href="?edit=<?php echo (int) $it['id']; ?>&date=<?php echo e($viewDate); ?>">Edit</a>
                <form method="POST" class="inline-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>" />
                  <button class="link-btn"><?php echo $it['is_active'] ? 'Hide' : 'Show'; ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?php echo !empty($form['id']) ? 'Edit item' : 'Add an item'; ?></h2>
      <?php if ($formErrors): ?>
        <div class="form-errors"><ul><?php foreach ($formErrors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>" />
        <div class="form-row"><label>Name</label><input type="text" name="name" required maxlength="100" value="<?php echo e($form['name']); ?>" /></div>
        <div class="form-row"><label>Category</label>
          <select name="category_id" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $form['category_id'] === (int) $c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>Short description</label><input type="text" name="description" maxlength="255" value="<?php echo e($form['description']); ?>" /></div>
        <div class="time-row">
          <div class="form-row"><label>Price (<?php echo CURRENCY; ?>)</label><input type="number" name="price" min="0" step="0.01" required value="<?php echo e($form['price']); ?>" /></div>
          <div class="form-row"><label>Per</label><input type="text" name="unit" maxlength="20" value="<?php echo e($form['unit']); ?>" /></div>
          <div class="form-row"><label>Qty owned</label><input type="number" name="total_quantity" min="0" required value="<?php echo e($form['total_quantity']); ?>" /></div>
        </div>
        <?php include __DIR__ . '/../includes/photo-field.php'; ?>
        <button type="submit" class="btn-primary"><?php echo !empty($form['id']) ? 'Save changes' : 'Add item'; ?></button>
        <?php if (!empty($form['id'])): ?>
          <a href="inventory.php" class="small" style="margin-left:.75rem">Cancel</a>
        <?php endif; ?>
      </form>

      <?php if (!empty($form['id'])): ?>
        <form method="POST" class="delete-form" data-confirm="Delete this item permanently?">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
          <button class="btn-danger-link">Delete item</button>
          <span class="label-hint">Only possible if it was never booked.</span>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
