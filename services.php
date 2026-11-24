<?php
require_once 'includes/db.php';
require_once 'includes/booking-functions.php';

// Pulled live from the database, so anything the admin adds/edits in
// Admin > Inventory or Admin > Services shows up here automatically.
$categories = db_all($conn, "SELECT * FROM categories ORDER BY sort_order");
$items = get_item_availability($conn, null);
$services = get_service_availability($conn, null);

$itemsByCategory = [];
foreach ($items as $item) {
    $itemsByCategory[$item['category_id']][] = $item;
}

$pageTitle = "Services & Rentals | Romero's Party Needs";
$activePage = "services";
$pageScripts = ['services.js'];
require_once 'includes/header.php';
?>

    <header class="page-header">
      
      <div class="container page-header-content">
        <h1 class="page-title">Services &amp; rentals</h1>
        <p class="page-sub">
          Everything for the setup, and everyone to run the day. Mix and match,
          or book it all in one go.
        </p>
      </div>
    </header>

    <section class="container">
      <form class="date-check" id="dateCheck" onsubmit="return false;">
        <label for="checkDate">Check availability for a date</label>
        <input type="date" id="checkDate" min="<?php echo date('Y-m-d', strtotime('+' . MIN_LEAD_DAYS . ' days')); ?>" />
        <span class="date-check-result" id="dateCheckResult" aria-live="polite"></span>
      </form>
    </section>

    <section class="offerings container">
      <h2 class="section-label">Party equipment rentals</h2>

      <?php foreach ($categories as $cat): if (empty($itemsByCategory[$cat['id']])) continue; ?>
        <div class="rental-category">
          <div class="rental-category-head">
            <?php if ($cat['image']): ?>
              <img src="<?php echo e($cat['image']); ?>" alt="<?php echo e($cat['name']); ?> by Romero's Party Needs" />
            <?php endif; ?>
            <div>
              <h3><?php echo e($cat['name']); ?></h3>
              <p><?php echo e($cat['description']); ?></p>
            </div>
          </div>
          <ul class="price-list">
            <?php foreach ($itemsByCategory[$cat['id']] as $item): ?>
              <li data-item-id="<?php echo (int) $item['id']; ?>">
                <span class="price-list-name">
                  <?php if ($item['image']): ?><img class="price-list-thumb" src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy" /><?php endif; ?>
                  <?php echo e($item['name']); ?>
                  <small><?php echo e($item['description']); ?></small>
                </span>
                <span class="price-list-stock" data-total="<?php echo (int) $item['total_quantity']; ?>">
                  <?php echo (int) $item['total_quantity']; ?> in inventory
                </span>
                <span class="price-list-price"><?php echo peso($item['price']); ?> <small>/ <?php echo e($item['unit']); ?></small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <p class="fine-print">
        Rental prices are per event day. A flat <?php echo peso(DELIVERY_FEE); ?> delivery, setup and
        pickup fee is added to bookings with rental items.
      </p>
    </section>

    <section class="mini-gallery mini-gallery--tight container">
      <div class="mini-gallery-grid mini-gallery-grid--6">
        <img loading="lazy" decoding="async" src="assets/img/rpn-tbch.jpg" alt="Red and green Christmas party table setup" />
        <img loading="lazy" decoding="async" src="assets/img/rpn-tbch-2.jpg" alt="Pink poolside reception seating" />
        <img loading="lazy" decoding="async" src="assets/img/rpn-tbch-3.jpg" alt="Poolside patio table and chair setup" />
        <img loading="lazy" decoding="async" src="assets/img/rpn-tbch-5.jpg" alt="Black and gold table setup on a patio" />
        <img loading="lazy" decoding="async" src="assets/img/rpn-bd.jpg" alt="Catering table with pink skirting and chafing dish" />
        <img loading="lazy" decoding="async" src="assets/img/rpn-bd-2.jpg" alt="70th birthday balloon backdrop" />
      </div>
    </section>

    <section class="offerings offerings--alt">
      <div class="container">
        <h2 class="section-label">Professional services</h2>
        <p class="partner-note">
          Hosting and event coordination is handled by
          <a href="https://www.facebook.com/southflaneurevents" target="_blank" rel="noopener">South Flaneur Events</a>,
          our partner company run by family and close friends — booked
          through us as one seamless package.
        </p>
        <div class="offerings-row offerings-row--3">
          <?php foreach ($services as $svc): ?>
            <div class="offering" data-service-id="<?php echo (int) $svc['id']; ?>">
              <?php if ($svc['image']): ?>
                <img loading="lazy" decoding="async" class="offering-photo" src="<?php echo e($svc['image']); ?>" alt="<?php echo e($svc['name']); ?>" />
              <?php endif; ?>
              <h3><?php echo e($svc['name']); ?></h3>
              <p><?php echo e($svc['description']); ?></p>
              <p class="offering-price"><?php echo peso($svc['price']); ?> <small>per event</small></p>
              <span class="service-slot" aria-live="polite"></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="cta-band">
      <div class="container cta-band-content">
        <h2>Ready to book?</h2>
        <p>
          Pick your date, choose your items and services, and see your total
          before you submit.
        </p>
        <a href="book.php" class="btn-primary">Book now</a>
      </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
