<?php
require_once 'includes/db.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'account.php');
}

require_once 'includes/login-form.php';

$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '');
$pageTitle = "Log In | Romero's Party Needs";
$activePage = "login";
require_once 'includes/header.php';
?>

<header class="page-header">
  <div class="awning awning--sm" aria-hidden="true"></div>
  <div class="container page-header-content">
    <h1 class="page-title">Log in</h1>
    <p class="page-sub">Welcome back. Log in to book, track your events and schedule appointments.</p>
  </div>
</header>

<section class="contact-section container">

  <?php if ($loginSubmitted && $loginError !== ''): ?>
    <p class="field-error" style="display:block; margin-bottom: 1.5rem;">
      <?php echo e($loginError); ?>
    </p>
  <?php endif; ?>

  <form id="loginForm" class="js-validate" method="POST" action="login.php" novalidate>
    <input type="hidden" name="login_submit" value="1" />
    <input type="hidden" name="next" value="<?php echo e($next); ?>" />
    <?php echo csrf_field(); ?>

    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo old('email'); ?>" />
      <span class="field-error" data-for="email">Please enter your email.</span>
    </div>

    <div class="form-row">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" />
      <span class="field-error" data-for="password">Please enter your password.</span>
    </div>

    <button type="submit" class="btn-primary">Log in</button>
  </form>

  <p style="margin-top: 1.5rem; font-size: 0.95rem;">
    Don't have an account? <a href="signup.php<?php echo $next ? '?next=' . urlencode($next) : ''; ?>">Sign up</a>.
  </p>
</section>

<?php require_once 'includes/footer.php'; ?>
