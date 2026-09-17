<?php
require_once 'includes/db.php';
require_once 'includes/login-form.php';

$pageTitle = "Log In | Romero's Party Needs";
$activePage = "login";
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Log in</h1>
    <p class="page-sub">Welcome back.</p>
  </div>
</header>

<section class="contact-section container">

  <?php if ($loginSubmitted && $loginError !== ''): ?>
    <p class="field-error" style="display:block; margin-bottom: 1.5rem;">
      <?php echo htmlspecialchars($loginError); ?>
    </p>
  <?php endif; ?>

  <form id="loginForm" method="POST" action="login.php" novalidate>
    <input type="hidden" name="login_submit" value="1" />

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required
             value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
      <span class="field-error" data-for="email">Please enter your email.</span>
    </div>

    <div class="form-row">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required />
      <span class="field-error" data-for="password">Please enter your password.</span>
    </div>

    <button type="submit" class="btn-primary">Log in</button>
  </form>

  <p style="margin-top: 1.5rem; font-size: 0.95rem;">
    Don't have an account? <a href="signup.php">Sign up</a>.
  </p>
</section>

<?php require_once 'includes/footer.php'; ?>