<?php
require_once __DIR__ . '/db.php';

if (!isset($pageTitle)) { $pageTitle = BUSINESS_NAME; }
if (!isset($activePage)) { $activePage = ""; }

if (!function_exists('nav_active')) {
    function nav_active(string $page): string
    {
        global $activePage;
        return $activePage === $page ? 'active' : '';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo e($pageTitle); ?></title>
  <meta name="description" content="<?php echo e($metaDescription ?? "Romero's Party Needs rents tables, chairs, tents, signage and catering setups, with hosts and organizers from South Flaneur Events. Check dates and book online."); ?>" />
  <meta name="theme-color" content="#3e6e8e" />
  <link rel="icon" href="<?php echo url('assets/img/favicon.svg'); ?>" type="image/svg+xml" />

  <!-- Open the connections to the CDNs early so CSS and fonts arrive sooner. -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>" />
</head>
<body>
  <nav class="navbar navbar-expand-lg sticky-top site-nav">
    <div class="container">
      <a class="navbar-brand" href="<?php echo url('index.php'); ?>"><?php echo e(BUSINESS_NAME); ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link <?php echo nav_active('home'); ?>" href="<?php echo url('index.php'); ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link <?php echo nav_active('services'); ?>" href="<?php echo url('services.php'); ?>">Services &amp; Rentals</a></li>
          <li class="nav-item"><a class="nav-link <?php echo nav_active('about'); ?>" href="<?php echo url('about.php'); ?>">About Us</a></li>
          <li class="nav-item"><a class="nav-link <?php echo nav_active('contact'); ?>" href="<?php echo url('contact.php'); ?>">Contact</a></li>

          <?php if (is_logged_in()): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?php echo in_array($activePage, ['account', 'appointment', 'admin'], true) ? 'active' : ''; ?>"
                 href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo e(explode(' ', $_SESSION['user_name'] ?? 'Account')[0]); ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php if (is_admin()): ?>
                  <li><a class="dropdown-item" href="<?php echo url('admin/index.php'); ?>">Admin dashboard</a></li>
                  <li><hr class="dropdown-divider" /></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?php echo url('account.php'); ?>">My bookings</a></li>
                <li><a class="dropdown-item" href="<?php echo url('appointment.php'); ?>">Book an appointment</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?php echo url('logout.php'); ?>">Log out</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link <?php echo nav_active('login'); ?>" href="<?php echo url('login.php'); ?>">Log in</a></li>
            <li class="nav-item"><a class="nav-link <?php echo nav_active('signup'); ?>" href="<?php echo url('signup.php'); ?>">Sign up</a></li>
          <?php endif; ?>

          <li class="nav-item ms-lg-2">
            <a class="btn-primary btn-nav <?php echo nav_active('book'); ?>" href="<?php echo url('book.php'); ?>">Book now</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <?php $flashes = take_flashes(); if ($flashes): ?>
    <div class="flash-stack container">
      <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?php echo e($f['type']); ?>" role="status">
          <span><?php echo e($f['message']); ?></span>
          <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
