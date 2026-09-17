<?php
require_once 'includes/db.php';
require_once 'includes/signup-form.php';

$pageTitle = "Sign Up | Romero's Party Needs";
$activePage = "signup";
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Create an account</h1>
    <p class="page-sub">Sign up to track your inquiries and bookings.</p>
  </div>
</header>

<section class="contact-section container">

  <?php if ($signupSubmitted && $signupSuccess): ?>
    <p class="form-success is-visible">
      Account created! You can now
      <a href="login.php">log in</a>.
    </p>
  <?php elseif ($signupSubmitted && $signupError !== ''): ?>
    <p class="field-error" style="display:block; margin-bottom: 1.5rem;">
      <?php echo htmlspecialchars($signupError); ?>
    </p>
  <?php endif; ?>

  <?php if (!($signupSubmitted && $signupSuccess)): ?>
  <form id="signupForm" method="POST" action="signup.php" novalidate>
    <input type="hidden" name="signup_submit" value="1" />

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required
             value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
      <span class="field-error" data-for="email">Please enter a valid email address.</span>
    </div>

    <div class="form-row">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="6" />
      <span class="field-error" data-for="password">Password must be at least 6 characters.</span>
    </div>

    <div class="form-row">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="6" />
      <span class="field-error" data-for="confirm_password">Passwords must match.</span>
    </div>

    <button type="submit" class="btn-primary">Sign up</button>
  </form>

  <p style="margin-top: 1.5rem; font-size: 0.95rem;">
    Already have an account? <a href="login.php">Log in</a>.
  </p>
  <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>