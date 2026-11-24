<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking-functions.php';
require_admin();

/** "Tables & Chairs" -> "tables-chairs" (used in links, must be unique). */
function make_slug(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return $slug !== '' ? $slug : 'category';
}

$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $cid = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = post_str('name');
        $description = post_str('description');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $existing = $cid ? db_one($conn, "SELECT * FROM categories WHERE id = ?", "i", [$cid]) : null;

        if ($name === '') $formErrors[] = 'Name is required.';
        if ($cid && !$existing) $formErrors[] = 'That category no longer exists.';

        // Unique slug: add -2, -3 ... if the name is already used.
        $slug = $base = make_slug($name);
        for ($n = 2; db_value($conn, "SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?", "si", [$slug, $cid]) > 0; $n++) {
            $slug = "$base-$n";
        }

        $upload = save_uploaded_image('photo');
        if (isset($upload['error'])) $formErrors[] = $upload['error'];

        if (!$formErrors) {
            $image = $existing['image'] ?? null;
            if ($upload['path']) {
                delete_uploaded_image($image);
                $image = $upload['path'];
            } elseif (!empty($_POST['remove_photo'])) {
                delete_uploaded_image($image);
                $image = null;
            }

            if ($cid) {
                db_exec($conn, "UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, sort_order = ? WHERE id = ?",
                    "ssssii", [$name, $slug, $description ?: null, $image, $sort, $cid]);
                flash('success', "$name updated.");
            } else {
                db_exec($conn, "INSERT INTO categories (name, slug, description, image, sort_order) VALUES (?, ?, ?, ?, ?)",
                    "ssssi", [$name, $slug, $description ?: null, $image, $sort]);
                flash('success', "$name added. You can now add items to it in Inventory.");
            }
            redirect('admin/categories.php');
        } elseif ($upload['path'] ?? null) {
            delete_uploaded_image($upload['path']); // don't keep a photo for a failed save
        }
    }

    if ($action === 'delete' && $cid) {
        if (db_value($conn, "SELECT COUNT(*) FROM inventory_items WHERE category_id = ?", "i", [$cid]) > 0) {
            flash('error', 'Move or delete the items in this category first.');
        } else {
            delete_uploaded_image(db_value($conn, "SELECT image FROM categories WHERE id = ?", "i", [$cid]));
            db_exec($conn, "DELETE FROM categories WHERE id = ?", "i", [$cid]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

$categories = db_all(
    $conn,
    "SELECT c.*, COUNT(i.id) AS item_count, SUM(i.is_active) AS active_count
     FROM categories c LEFT JOIN inventory_items i ON i.category_id = c.id
     GROUP BY c.id, c.name, c.slug, c.description, c.image, c.sort_order
     ORDER BY c.sort_order, c.name"
);
$editing = null;
foreach ($categories as $c) {
    if ((int) $c['id'] === (int) ($_GET['edit'] ?? 0)) $editing = $c;
}
$blank = ['id' => 0, 'name' => '', 'description' => '', 'image' => null, 'sort_order' => count($categories) + 1];
$form = array_merge($blank, $formErrors ? $_POST : ($editing ?? []));
if ($formErrors && $editing) $form['image'] = $editing['image'];

$pageTitle = "Categories | Admin";
$activePage = "admin";
$adminPage = "inventory";
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin-nav.php';
?>

<section class="container admin-page">
  <p class="small"><a href="inventory.php">&larr; Inventory</a></p>
  <div class="admin-head"><h1 class="admin-title">Rental categories</h1></div>

  <div class="admin-grid admin-grid--wide-left">
    <div class="panel panel--flush">
      <table class="data-table">
        <thead><tr><th>Photo</th><th>Category</th><th class="num">Order</th><th class="num">Items</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><?php if ($c['image']): ?><img class="thumb" src="<?php echo e(url($c['image'])); ?>" alt="" /><?php else: ?><span class="thumb thumb--empty"></span><?php endif; ?></td>
              <td><strong><?php echo e($c['name']); ?></strong><br /><span class="muted small"><?php echo e($c['description']); ?></span></td>
              <td class="num"><?php echo (int) $c['sort_order']; ?></td>
              <td class="num"><?php echo (int) $c['active_count']; ?><span class="muted small"> / <?php echo (int) $c['item_count']; ?></span></td>
              <td class="row-actions"><a href="?edit=<?php echo (int) $c['id']; ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2 class="panel-title"><?php echo $form['id'] ? 'Edit category' : 'Add a category'; ?></h2>
      <?php if ($formErrors): ?>
        <div class="form-errors"><ul><?php foreach ($formErrors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
        <div class="form-row"><label>Name</label><input type="text" name="name" required maxlength="60" value="<?php echo e($form['name']); ?>" /></div>
        <div class="form-row"><label>Description</label><input type="text" name="description" maxlength="255" value="<?php echo e($form['description']); ?>" /></div>
        <div class="form-row"><label>Display order <span class="label-hint">(1 = shown first)</span></label><input type="number" name="sort_order" value="<?php echo (int) $form['sort_order']; ?>" /></div>
        <?php include __DIR__ . '/../includes/photo-field.php'; ?>
        <button type="submit" class="btn-primary"><?php echo $form['id'] ? 'Save changes' : 'Add category'; ?></button>
        <?php if ($form['id']): ?><a href="categories.php" class="small" style="margin-left:.75rem">Cancel</a><?php endif; ?>
      </form>
      <?php if ($form['id']): ?>
        <form method="POST" class="delete-form" data-confirm="Delete this category?">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
          <button class="btn-danger-link">Delete category</button>
          <span class="label-hint">Only when it has no items.</span>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
