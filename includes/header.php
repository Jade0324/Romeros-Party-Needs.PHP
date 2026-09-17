<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) { $pageTitle = "Romero's Party Needs"; }
if (!isset($activePage)) { $activePage = ""; }
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $pageTitle; ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <nav class="navbar navbar-expand-lg sticky-top site-nav">
    <div class="container">
      <a class="navbar-brand" href="index.php">Romero's Party Needs</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $activePage === 'services' ? 'active' : ''; ?>" href="services.php">Services &amp; Rentals</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $activePage === 'about' ? 'active' : ''; ?>" href="about.php">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $activePage === 'contact' ? 'active' : ''; ?>" href="contact.php">Contact</a>
          </li>

          <?php if ($isLoggedIn): ?>
            <li class="nav-item">
              <span class="nav-link" style="opacity: 0.75;">
                <?php echo htmlspecialchars($_SESSION['user_email']); ?>
              </span>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="logout.php">Log out</a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link <?php echo $activePage === 'login' ? 'active' : ''; ?>" href="login.php">Log in</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?php echo $activePage === 'signup' ? 'active' : ''; ?>" href="signup.php">Sign up</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>