<?php
require_once 'includes/db.php';
require_once 'includes/contact-form.php';

$pageTitle = "Contact | Romero's Party Needs";
$activePage = "contact";
require_once 'includes/header.php';
?>

<header class="page-header">
  
  <div class="container page-header-content">
    <h1 class="page-title">Get in touch</h1>
    <p class="page-sub">
      Tell us about your event and we'll get back to you with a plan
      and a quote.
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
      <?php echo htmlspecialchars($formError); ?>
    </p>
  <?php endif; ?>

  <form id="contactForm" method="POST" action="contact.php" novalidate>
    <input type="hidden" name="contact_submit" value="1" />

    <div class="form-row">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required
        value="<?php echo (isset($_POST['name']) && !$formSuccess) ? htmlspecialchars($_POST['name']) : ''; ?>" />
      <span class="field-error" data-for="name">Please enter your name.</span>
    </div>

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required
        value="<?php echo (isset($_POST['email']) && !$formSuccess) ? htmlspecialchars($_POST['email']) : ''; ?>" />
      <span class="field-error" data-for="email">Please enter a valid email address.</span>
    </div>

    <div class="form-row">
      <label for="phone">Phone</label>
      <input type="tel" id="phone" name="phone"
        value="<?php echo (isset($_POST['phone']) && !$formSuccess) ? htmlspecialchars($_POST['phone']) : ''; ?>" />
    </div>

    <div class="form-row">
      <label for="eventDate">Event date</label>
      <input type="date" id="eventDate" name="eventDate" required
        value="<?php echo (isset($_POST['eventDate']) && !$formSuccess) ? htmlspecialchars($_POST['eventDate']) : ''; ?>" />
      <span class="field-error" data-for="eventDate">Please select your event date.</span>
    </div>

    <div class="form-row">
      <label for="message">What do you need?</label>
      <textarea id="message" name="message" rows="5" required><?php echo (isset($_POST['message']) && !$formSuccess) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
      <span class="field-error" data-for="message">Please tell us a bit about your event.</span>
    </div>

    <button type="submit" class="btn-primary">Send message</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>