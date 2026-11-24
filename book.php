<?php
require_once 'includes/db.php';
require_once 'includes/booking-functions.php';

require_login();

$errors = [];

// Admins can take bookings for walk-in / phone customers.
$customers = is_admin()
    ? db_all($conn, "SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name")
    : [];

// ---- Edit mode: book.php?edit=RPN-XXXXXX -----------------------------------
// Customers can change their own booking while it's still pending; admins can
// also change confirmed ones. The booking's own reservation is left out of
// the availability numbers so it doesn't block itself.
$editing = null;
if (!empty($_GET['edit'])) {
    $editing = db_one($conn, "SELECT * FROM bookings WHERE reference = ?", "s", [strtoupper(trim($_GET['edit']))]);
    $isOwner = $editing && (int) $editing['user_id'] === current_user_id();
    $canEdit = $editing && $editing['event_date'] >= today() && (
        (is_admin() && in_array($editing['status'], ['pending', 'confirmed'], true)) ||
        ($isOwner && $editing['status'] === 'pending')
    );
    if (!$canEdit) {
        if (!$editing || (!$isOwner && !is_admin())) {
            render_error_page(404, 'Booking not found', 'Check the reference, or see your bookings.');
        }
        flash('error', 'This booking can no longer be changed online. Please contact us for changes.');
        redirect(is_admin() ? 'admin/booking.php?id=' . (int) $editing['id'] : 'booking.php?ref=' . urlencode($editing['reference']));
    }
}
$excludeId = $editing ? (int) $editing['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $eventType = post_str('event_type');
    $eventDate = clean_date(post_str('event_date'));
    $guestCount = (int) ($_POST['guest_count'] ?? 0);
    $venue = post_str('venue_address');
    $setupTime = clean_time(post_str('setup_time'));
    $startTime = clean_time(post_str('event_start'));
    $endTime = clean_time(post_str('event_end'));
    $notes = post_str('notes');

    $bookFor = current_user_id();
    if ($editing) {
        $bookFor = (int) $editing['user_id']; // an edit never changes the owner
    } elseif (is_admin() && !empty($_POST['customer_id'])) {
        $bookFor = (int) $_POST['customer_id'];
        if (!db_value($conn, "SELECT COUNT(*) FROM users WHERE id = ?", "i", [$bookFor])) {
            $errors[] = 'Please choose a valid customer.';
        }
    }

    // Quantities come in as items[item_id] = qty; keep only positive whole numbers.
    $requestedItems = [];
    foreach ((array) ($_POST['items'] ?? []) as $itemId => $qty) {
        $qty = (int) $qty;
        if ($qty > 0) {
            $requestedItems[(int) $itemId] = $qty;
        }
    }
    $requestedServices = array_values(array_unique(array_map('intval', (array) ($_POST['services'] ?? []))));

    // ---- Basic field checks ------------------------------------------------
    if (!in_array($eventType, EVENT_TYPES, true)) $errors[] = 'Please choose the type of event.';
    if (!$eventDate) $errors[] = 'Please choose a valid event date.';
    if ($guestCount < 1 || $guestCount > 2000) $errors[] = 'Please enter the number of guests (1–2000).';
    if ($venue === '' || mb_strlen($venue) > 255) $errors[] = 'Please enter the venue address.';
    if (!$setupTime || !$startTime || !$endTime) {
        $errors[] = 'Please fill in the setup, start and end times.';
    } elseif (!($setupTime < $startTime && $startTime < $endTime)) {
        $errors[] = 'Setup must be before the event starts, and the event must end after it starts (same day).';
    }
    if (!$requestedItems && !$requestedServices) {
        $errors[] = 'Please add at least one rental item or service.';
    }

    if (!$errors) {
        // ---- Availability check + save, as one transaction ----------------
        // Locking this date's booking rows (FOR UPDATE) makes a second
        // customer booking the same date wait until we're done. Without it,
        // two people could both grab the last tent at the same moment.
        mysqli_begin_transaction($conn);
        try {
            // When editing, lock the old date too (its items are being released).
            $oldDate = $editing['event_date'] ?? $eventDate;
            db_all($conn, "SELECT id FROM bookings WHERE event_date IN (?, ?) FOR UPDATE", "ss", [$eventDate, $oldDate]);

            // Keeping the same date on an edit is always fine (the slot is
            // already ours); a new date has to pass the normal date rules.
            $keepingDate = $editing && $eventDate === $editing['event_date'];
            if (!$keepingDate && ($dateError = event_date_error($conn, $eventDate, $excludeId))) {
                $errors[] = $dateError;
            }

            $available = get_item_availability($conn, $eventDate, $excludeId);
            $itemsTotal = 0;
            foreach ($requestedItems as $itemId => $qty) {
                if (!isset($available[$itemId])) {
                    $errors[] = 'One of the selected items is no longer offered.';
                    continue;
                }
                $item = $available[$itemId];
                if ($qty > $item['available']) {
                    $errors[] = "Only {$item['available']} × {$item['name']} left on " . fmt_date($eventDate) . " (you asked for $qty).";
                }
                $itemsTotal += $qty * (float) $item['price'];
            }

            $serviceRows = get_service_availability($conn, $eventDate, $excludeId);
            $servicesTotal = 0;
            foreach ($requestedServices as $serviceId) {
                if (!isset($serviceRows[$serviceId])) {
                    $errors[] = 'One of the selected services is no longer offered.';
                    continue;
                }
                if ($serviceRows[$serviceId]['remaining'] < 1) {
                    $errors[] = $serviceRows[$serviceId]['name'] . ' is fully booked on ' . fmt_date($eventDate) . '.';
                }
                $servicesTotal += (float) $serviceRows[$serviceId]['price'];
            }

            if ($errors) {
                mysqli_rollback($conn);
            } else {
                $deliveryFee = $requestedItems ? DELIVERY_FEE : 0;
                $total = $itemsTotal + $servicesTotal + $deliveryFee;

                if ($editing) {
                    // Update the booking and replace its items/services.
                    $bookingId = (int) $editing['id'];
                    $reference = $editing['reference'];
                    db_exec(
                        $conn,
                        "UPDATE bookings SET event_type = ?, event_date = ?, setup_time = ?, event_start = ?, event_end = ?,
                                venue_address = ?, guest_count = ?, notes = ?, items_total = ?, services_total = ?,
                                delivery_fee = ?, total_amount = ?
                         WHERE id = ?",
                        "ssssssisddddi",
                        [$eventType, $eventDate, $setupTime, $startTime, $endTime, $venue, $guestCount,
                         $notes ?: null, $itemsTotal, $servicesTotal, $deliveryFee, $total, $bookingId]
                    );
                    db_exec($conn, "DELETE FROM booking_items WHERE booking_id = ?", "i", [$bookingId]);
                    db_exec($conn, "DELETE FROM booking_services WHERE booking_id = ?", "i", [$bookingId]);
                } else {
                    $reference = generate_booking_reference($conn);
                    $bookingId = db_exec(
                        $conn,
                        "INSERT INTO bookings (reference, user_id, event_type, event_date, setup_time, event_start, event_end,
                                               venue_address, guest_count, notes, items_total, services_total, delivery_fee, total_amount)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        "sissssssisdddd",
                        [$reference, $bookFor, $eventType, $eventDate, $setupTime, $startTime, $endTime,
                         $venue, $guestCount, $notes ?: null, $itemsTotal, $servicesTotal, $deliveryFee, $total]
                    );
                }

                foreach ($requestedItems as $itemId => $qty) {
                    $price = (float) $available[$itemId]['price'];
                    db_exec(
                        $conn,
                        "INSERT INTO booking_items (booking_id, item_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)",
                        "iiidd",
                        [$bookingId, $itemId, $qty, $price, $qty * $price]
                    );
                }
                foreach ($requestedServices as $serviceId) {
                    db_exec(
                        $conn,
                        "INSERT INTO booking_services (booking_id, service_id, price) VALUES (?, ?, ?)",
                        "iid",
                        [$bookingId, $serviceId, (float) $serviceRows[$serviceId]['price']]
                    );
                }

                if ($editing) {
                    $note = ((int) $editing['user_id'] === current_user_id() ? 'Booking edited by customer' : 'Booking edited by admin')
                        . ($eventDate !== $editing['event_date'] ? ' — moved from ' . fmt_date($editing['event_date']) . ' to ' . fmt_date($eventDate) : '')
                        . (abs($total - (float) $editing['total_amount']) > 0.001 ? '; new total ' . number_format($total, 2) : '');
                    add_booking_log($conn, $bookingId, 'updated', $note, current_user_id());
                } else {
                    $how = $bookFor === current_user_id() ? 'Booking submitted online' : 'Booking created by admin on behalf of customer';
                    add_booking_log($conn, $bookingId, 'created', $how, current_user_id());
                }

                mysqli_commit($conn);

                flash('success', $editing ? "Booking $reference updated." : "Booking $reference received! We'll review it and confirm shortly.");
                redirect(is_admin() ? "admin/booking.php?id=$bookingId" : "booking.php?ref=$reference");
            }
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $errors[] = 'Something went wrong saving your booking. Please try again.';
            error_log('Booking failed: ' . $ex->getMessage());
        }
    }
}

// ---- Data for the form -----------------------------------------------------
// Opening an edit: fill the form with the booking as it is now.
if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST = [
        'event_type' => $editing['event_type'],
        'event_date' => $editing['event_date'],
        'guest_count' => $editing['guest_count'],
        'venue_address' => $editing['venue_address'],
        'setup_time' => substr($editing['setup_time'], 0, 5),
        'event_start' => substr($editing['event_start'], 0, 5),
        'event_end' => substr($editing['event_end'], 0, 5),
        'notes' => $editing['notes'],
        'items' => array_column(db_all($conn, "SELECT item_id, quantity FROM booking_items WHERE booking_id = ?", "i", [$excludeId]), 'quantity', 'item_id'),
        'services' => array_column(db_all($conn, "SELECT service_id FROM booking_services WHERE booking_id = ?", "i", [$excludeId]), 'service_id'),
    ];
}

$selectedDate = clean_date($_POST['event_date'] ?? '') ?: '';
$categories = db_all($conn, "SELECT * FROM categories ORDER BY sort_order");
$items = get_item_availability($conn, $selectedDate ?: null, $excludeId);
$services = get_service_availability($conn, $selectedDate ?: null, $excludeId);

$itemsByCategory = [];
foreach ($items as $item) {
    $itemsByCategory[$item['category_id']][] = $item;
}

$postedItems = (array) ($_POST['items'] ?? []);
$postedServices = array_map('intval', (array) ($_POST['services'] ?? []));
$minDate = date('Y-m-d', strtotime('+' . MIN_LEAD_DAYS . ' days'));
$maxDate = date('Y-m-d', strtotime('+' . MAX_ADVANCE_DAYS . ' days'));

$pageTitle = ($editing ? "Edit Booking {$editing['reference']}" : "Book Your Event") . " | Romero's Party Needs";
$activePage = "book";
$pageScripts = ['booking.js'];
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="container page-header-content">
    <?php if ($editing): ?>
      <p class="page-eyebrow"><a href="<?php echo is_admin() ? 'admin/booking.php?id=' . $excludeId : 'booking.php?ref=' . urlencode($editing['reference']); ?>">&larr; Back to booking</a></p>
      <h1 class="page-title">Edit booking <?php echo e($editing['reference']); ?></h1>
      <p class="page-sub">Change the date, items or services. The numbers below already count what this booking is holding.</p>
    <?php else: ?>
      <h1 class="page-title">Book your event</h1>
      <p class="page-sub">Pick a date to see what's available, choose your rentals and services, and send your booking for confirmation.</p>
    <?php endif; ?>
  </div>
</header>

<section class="container booking-page">
  <?php if ($errors): ?>
    <div class="form-errors" role="alert">
      <strong>Please fix the following:</strong>
      <ul><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form id="bookingForm" method="POST" action="book.php<?php echo $editing ? '?edit=' . urlencode($editing['reference']) : ''; ?>" novalidate
        data-exclude="<?php echo $editing ? e($editing['reference']) : ''; ?>"
        data-keep-date="<?php echo $editing ? e($editing['event_date']) : ''; ?>">
    <?php echo csrf_field(); ?>
    <div class="booking-layout">
      <div class="booking-main">

        <!-- Step 1: event details -------------------------------------- -->
        <fieldset class="panel">
          <legend class="panel-title"><span class="step-num">1</span> Event details</legend>

          <?php if (is_admin() && !$editing): ?>
            <div class="form-row">
              <label for="customer_id">Booking for <span class="label-hint">(admin: pick a customer, or leave blank to book under your account)</span></label>
              <select id="customer_id" name="customer_id">
                <option value="">— My own account —</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) ($_POST['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : ''; ?>>
                    <?php echo e($c['full_name'] . ' (' . $c['email'] . ')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="booking-date-row">
            <div class="booking-calendar" id="bookingCalendar" aria-label="Availability calendar">
              <div class="cal-head">
                <button type="button" class="cal-nav" data-dir="-1" aria-label="Previous month">&lsaquo;</button>
                <span class="cal-label"></span>
                <button type="button" class="cal-nav" data-dir="1" aria-label="Next month">&rsaquo;</button>
              </div>
              <div class="cal-grid"></div>
              <div class="cal-legend">
                <span><i class="dot dot-open"></i>Open</span>
                <span><i class="dot dot-limited"></i>Almost full</span>
                <span><i class="dot dot-full"></i>Full / closed</span>
              </div>
            </div>

            <div class="booking-date-fields">
              <div class="form-row">
                <label for="event_date">Event date</label>
                <input type="date" id="event_date" name="event_date" required min="<?php echo ($editing && $editing['event_date'] < $minDate) ? e($editing['event_date']) : $minDate; ?>" max="<?php echo $maxDate; ?>" value="<?php echo e($selectedDate); ?>" />
                <span class="date-status" id="dateStatus" aria-live="polite"></span>
              </div>
              <div class="form-row">
                <label for="event_type">Type of event</label>
                <select id="event_type" name="event_type" required>
                  <option value="">Choose…</option>
                  <?php foreach (EVENT_TYPES as $type): ?>
                    <option <?php echo ($_POST['event_type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-row">
                <label for="guest_count">Number of guests</label>
                <input type="number" id="guest_count" name="guest_count" min="1" max="2000" required value="<?php echo old('guest_count'); ?>" />
                <span class="label-hint" id="guestHint"></span>
              </div>
            </div>
          </div>

          <div class="form-row">
            <label for="venue_address">Venue address</label>
            <input type="text" id="venue_address" name="venue_address" required maxlength="255" placeholder="Street, barangay, city" value="<?php echo old('venue_address'); ?>" />
          </div>

          <div class="time-row">
            <div class="form-row">
              <label for="setup_time">Crew setup time</label>
              <input type="time" id="setup_time" name="setup_time" required value="<?php echo old('setup_time', '08:00'); ?>" />
            </div>
            <div class="form-row">
              <label for="event_start">Event starts</label>
              <input type="time" id="event_start" name="event_start" required value="<?php echo old('event_start', '12:00'); ?>" />
            </div>
            <div class="form-row">
              <label for="event_end">Event ends</label>
              <input type="time" id="event_end" name="event_end" required value="<?php echo old('event_end', '17:00'); ?>" />
            </div>
          </div>
          <p class="label-hint">We arrive at the setup time and pick everything up after the event ends. Allow at least 2 hours for setup with tents.</p>
        </fieldset>

        <!-- Step 2: rentals ------------------------------------------ -->
        <fieldset class="panel">
          <legend class="panel-title"><span class="step-num">2</span> Rentals</legend>
          <p class="panel-note" id="itemsNote"><?php echo $selectedDate ? '' : 'Choose a date above to see how many of each item are free.'; ?></p>

          <?php foreach ($categories as $cat): if (empty($itemsByCategory[$cat['id']])) continue; ?>
            <h3 class="item-group-title"><?php echo e($cat['name']); ?></h3>
            <div class="item-list">
              <?php foreach ($itemsByCategory[$cat['id']] as $item):
                  $id = (int) $item['id'];
                  $qty = max(0, (int) ($postedItems[$id] ?? 0)); ?>
                <div class="item-row" data-item-id="<?php echo $id; ?>">
                  <div class="item-info">
                    <?php if ($item['image']): ?><img class="item-thumb" src="<?php echo e($item['image']); ?>" alt="" loading="lazy" /><?php endif; ?>
                    <span class="item-name"><?php echo e($item['name']); ?></span>
                    <span class="item-price"><?php echo peso($item['price']); ?> / <?php echo e($item['unit']); ?></span>
                  </div>
                  <span class="avail-badge" data-avail-for="<?php echo $id; ?>">
                    <?php echo $selectedDate ? $item['available'] . ' available' : $item['total_quantity'] . ' owned'; ?>
                  </span>
                  <div class="qty-control">
                    <button type="button" class="qty-btn" data-step="-1" aria-label="Less">&minus;</button>
                    <input type="number" class="qty-input" name="items[<?php echo $id; ?>]" min="0"
                           max="<?php echo $selectedDate ? $item['available'] : $item['total_quantity']; ?>"
                           value="<?php echo $qty; ?>"
                           data-price="<?php echo e($item['price']); ?>" data-name="<?php echo e($item['name']); ?>"
                           aria-label="Quantity of <?php echo e($item['name']); ?>" />
                    <button type="button" class="qty-btn" data-step="1" aria-label="More">+</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </fieldset>

        <!-- Step 3: services ----------------------------------------- -->
        <fieldset class="panel">
          <legend class="panel-title"><span class="step-num">3</span> Hosts &amp; organizers <span class="label-hint">(optional, by South Flaneur Events)</span></legend>
          <div class="service-options">
            <?php foreach ($services as $svc): $sid = (int) $svc['id']; ?>
              <label class="service-option" data-service-id="<?php echo $sid; ?>">
                <input type="checkbox" name="services[]" value="<?php echo $sid; ?>"
                       data-price="<?php echo e($svc['price']); ?>" data-name="<?php echo e($svc['name']); ?>"
                       <?php echo in_array($sid, $postedServices, true) ? 'checked' : ''; ?> />
                <span class="service-option-body">
                  <span class="service-option-name"><?php echo e($svc['name']); ?></span>
                  <span class="service-option-desc"><?php echo e($svc['description']); ?></span>
                  <span class="service-option-price"><?php echo peso($svc['price']); ?></span>
                  <span class="service-slot"></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="panel">
          <legend class="panel-title"><span class="step-num">4</span> Anything else?</legend>
          <div class="form-row">
            <label for="notes">Notes for our team <span class="label-hint">(motif colours, venue access, special requests)</span></label>
            <textarea id="notes" name="notes" rows="3"><?php echo old('notes'); ?></textarea>
          </div>
        </fieldset>
      </div>

      <!-- Live summary ------------------------------------------------- -->
      <aside class="booking-summary">
        <div class="panel summary-panel">
          <h2 class="panel-title">Your booking</h2>
          <p class="summary-date" id="summaryDate">No date selected</p>
          <ul class="summary-lines" id="summaryLines">
            <li class="summary-empty">Nothing added yet.</li>
          </ul>
          <dl class="summary-totals">
            <div><dt>Delivery, setup &amp; pickup</dt><dd id="summaryDelivery"><?php echo peso(0); ?></dd></div>
            <div class="summary-total"><dt>Total</dt><dd id="summaryTotal"><?php echo peso(0); ?></dd></div>
            <div class="summary-dp"><dt>Down payment (<?php echo (int) (DOWN_PAYMENT_RATE * 100); ?>%)</dt><dd id="summaryDown"><?php echo peso(0); ?></dd></div>
          </dl>
          <button type="submit" class="btn-primary btn-block" id="submitBooking"><?php echo $editing ? 'Save changes' : 'Submit booking'; ?></button>
          <p class="fine-print">Your booking stays <strong>pending</strong> until we confirm it. Items are held for you in the meantime. Payment details are sent once confirmed.</p>
        </div>
      </aside>
    </div>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>
