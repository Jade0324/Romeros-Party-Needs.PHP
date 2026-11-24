<?php
require_once 'includes/db.php';
require_once 'includes/contact-form.php';

// Logged-in customers get their details pre-filled.
$prefill = ['name' => '', 'email' => '', 'phone' => ''];
if (is_logged_in()) {
    $me = db_one($conn, "SELECT full_name, email, phone FROM users WHERE id = ?", "i", [current_user_id()]);
    if ($me) {
        $prefill = ['name' => $me['full_name'], 'email' => $me['email'], 'phone' => $me['phone']];
    }
}

$pageTitle = "Contact | Romero's Party Needs";
$activePage = "contact";
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Get in touch</h1>
    <p class="page-sub">
      Questions before you book? Tell us about your event and we'll get back
      to you with a plan and a quote.
    </p>
  </div>
</header>

<section class="contact-section container">

  <?php if ($formSubmitted && $formSuccess): ?>
    <p class="form-success is-visible">
      Thanks — your message has been sent. We'll be in touch shortly.
    </p>
  <?php elseif ($formSubmitted && $formError !== ''): ?>
    <p class="field-error" style="display:block; margin-bottom: 1.5rem;">
      <?php echo e($formError); ?>
    </p>
  <?php endif; ?>

  <p class="contact-tip">
    Already know your date and what you need?
    <a href="book.php">Book online</a> to see live availability and reserve your items.
  </p>

  <form id="contactForm" class="js-validate" method="POST" action="contact.php" novalidate>
    <input type="hidden" name="contact_submit" value="1" />
    <?php echo csrf_field(); ?>

    <div class="form-row">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required value="<?php echo old('name', $prefill['name']); ?>" />
      <span class="field-error" data-for="name">Please enter your name.</span>
    </div>

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?php echo old('email', $prefill['email']); ?>" />
      <span class="field-error" data-for="email">Please enter a valid email address.</span>
    </div>

    <div class="form-row">
      <label for="phone">Phone</label>
      <input type="tel" id="phone" name="phone" value="<?php echo old('phone', $prefill['phone']); ?>" />
    </div>

    <div class="form-row">
      <label for="eventDate">Event date <span class="label-hint">(if you have one)</span></label>
      <input type="date" id="eventDate" name="eventDate" value="<?php echo old('eventDate'); ?>" />
    </div>

    <div class="form-row">
      <label for="message">What do you need?</label>
      <textarea id="message" name="message" rows="5" required><?php echo old('message'); ?></textarea>
      <span class="field-error" data-for="message">Please tell us a bit about your event.</span>
    </div>

    <button type="submit" class="btn-primary">Send message</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>
