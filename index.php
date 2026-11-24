<?php
require_once 'includes/db.php';
$pageTitle = "Romero's Party Needs | Party Rentals & Event Services";
$activePage = "home";
require_once 'includes/header.php';
?>

    <div class="awning awning--sm" aria-hidden="true"></div>

    <header class="hero">
      <div class="container hero-grid">
        <div class="hero-copy">
          <h1 class="hero-title">Make your event unforgettable</h1>
          <p class="hero-sub">
            Tables, chairs, tents, and signage for the setup — plus the hosts
            and organizers to run the day. One family business, everything your
            celebration needs.
          </p>
          <div class="hero-actions">
            <a href="book.php" class="btn-primary">Book your event</a>
            <a href="services.php" class="btn-ghost">See rentals &amp; prices</a>
          </div>
          <div class="hero-stats">
            <span class="stat-chip stat-chip--blue">10+ years</span>
            <span class="stat-chip stat-chip--red">500+ events</span>
            <span class="stat-chip stat-chip--green">Family-run</span>
          </div>
        </div>
        <div class="hero-collage">
          <img
            class="collage-main"
            src="assets/img/rpn-tbch-4.jpg"
            alt="Elegant banquet hall setup by Romero's Party Needs"
          />
          <img
            class="collage-small"
            src="assets/img/sf-org.jpg"
            alt="Coordinators with a newlywed couple"
          />
          <span class="collage-badge">Every detail, handled</span>
        </div>
      </div>
    </header>

    <section class="offerings container">
      <div class="offerings-row">
        <div class="offering">
          <img
            class="offering-photo"
            src="assets/img/rpn-tbch-5.jpg"
            alt="Table and chair rental setup by Romero's Party Needs"
          />
          <h3>Party rentals</h3>
          <p>
            Tables, chairs, tents, catering setups, and custom signage for any
            occasion.
          </p>
        </div>
        <div class="offering">
          <img
            class="offering-photo"
            src="assets/img/sf-host.jpg"
            alt="Event host on stage at a party"
          />
          <h3>Event hosting</h3>
          <p>
            Professional hosts who keep guests entertained and the program
            moving smoothly.
          </p>
        </div>
        <div class="offering">
          <img
            class="offering-photo"
            src="assets/img/sf-org.jpg"
            alt="Event organizers with a newlywed couple"
          />
          <h3>Event organizers</h3>
          <p>
            Wedding and party organizers who handle the details so you can enjoy
            the day.
          </p>
        </div>
      </div>
    </section>

    <section class="mini-gallery">
      <div class="container">
        <h2 class="section-label section-label--center">A few recent setups</h2>
        <div class="mini-gallery-grid">
          <img
            src="assets/img/rpn-bd-2.jpg"
            alt="Birthday backdrop and balloon decor"
          />
          <img
            src="assets/img/rpn-tbch-2.jpg"
            alt="Poolside reception seating in pink"
          />
          <img
            src="assets/img/rpn-tbch.jpg"
            alt="Christmas party table setup in red and green"
          />
          <img
            src="assets/img/rpn-tbch-3.jpg"
            alt="Poolside patio seating setup"
          />
        </div>
      </div>
    </section>

<section class="how-it-works">
      <div class="container">
        <h2 class="section-label section-label--center">How booking works</h2>
        <ol class="steps">
          <li class="step">
            <span class="step-num">1</span>
            <h3>Pick your date</h3>
            <p>The calendar shows which dates are open. We need at least <?php echo MIN_LEAD_DAYS; ?> days' notice.</p>
          </li>
          <li class="step">
            <span class="step-num">2</span>
            <h3>Choose what you need</h3>
            <p>See exactly how many chairs, tables, tents and hosts are free on your date, with live pricing.</p>
          </li>
          <li class="step">
            <span class="step-num">3</span>
            <h3>We confirm &amp; set up</h3>
            <p>We confirm your booking, schedule an ocular visit if needed, and our crew sets up before your event.</p>
          </li>
        </ol>
        <div class="text-center">
          <a href="book.php" class="btn-primary">Start a booking</a>
        </div>
      </div>
    </section>

<?php require_once 'includes/footer.php'; ?>