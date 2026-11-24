<?php
require_once 'includes/db.php';

if (is_logged_in()) {
    redirect('account.php');
}

require_once 'includes/signup-form.php';

$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '');
$pageTitle = "Sign Up | Romero's Party Needs";
$activePage = "signup";
require_once 'includes/header.php';
?>

<header class="page-header">
  
  <div class="container page-header-content">
    <h1 class="page-title">Create an account</h1>
    <p class="page-sub">Sign up to book rentals and services, and track every booking in one place.</p>
  </div>
</header>

<section class="contact-section container">

  <?php if ($signupSubmitted && $signupError !== ''): ?>
    <p class="field-error" style="display:block; margin-bottom: 1.5rem;">
      <?php echo e($signupError); ?>
    </p>
  <?php endif; ?>

  <form id="signupForm" class="js-validate" method="POST" action="signup.php" novalidate>
    <input type="hidden" name="signup_submit" value="1" />
    <input type="hidden" name="next" value="<?php echo e($next); ?>" />
    <?php echo csrf_field(); ?>

    <div class="form-row">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" required autocomplete="name" value="<?php echo old('full_name'); ?>" />
      <span class="field-error" data-for="full_name">Please enter your name.</span>
    </div>

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo old('email'); ?>" />
      <span class="field-error" data-for="email">Please enter a valid email address.</span>
    </div>

    <div class="form-row">
      <label for="phone">Mobile number</label>
      <input type="tel" id="phone" name="phone" required autocomplete="tel" placeholder="0917 123 4567" value="<?php echo old('phone'); ?>" />
      <span class="field-error" data-for="phone">We need a number to coordinate delivery and setup.</span>
    </div>

    <div class="form-row">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password" />
      <span class="field-error" data-for="password">Password must be at least 6 characters.</span>
    </div>

    <div class="form-row">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="6" data-match="#password" autocomplete="new-password" />
      <span class="field-error" data-for="confirm_password">Passwords must match.</span>
    </div>

      <button type="submit" class="btn-primary">Sign up</button>
    </form>

  <p style="margin-top: 1.5rem; font-size: 0.95rem;">
    Already have an account? <a href="login.php<?php echo $next ? '?next=' . urlencode($next) : ''; ?>">Log in</a>.
  </p>
</section>

<?php require_once 'includes/footer.php'; ?>
