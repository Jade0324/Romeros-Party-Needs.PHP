<?php
require_once 'includes/db.php';
$pageTitle = "About Us | Romero's Party Needs";
$activePage = "about";
require_once 'includes/header.php';
?>

<header class="page-header">
  
  <div class="container page-header-content">
    <h1 class="page-title">About us</h1>
    <p class="page-sub">A family business, built one event at a time.</p>
  </div>
</header>

<!-- Placeholder history — swap in your real founding story and dates. -->
<section class="story container">
  <img
    class="story-photo"
    src="assets/img/rpn-tbch-5.jpg"
    alt="A Romero's Party Needs table and chair setup"
  />
  <p>
    Romero's Party Needs started the way a lot of family businesses do: with
    a set of folding tables, a borrowed truck, and a favor for a relative's
    birthday. Word got around the neighborhood faster than the tables could
    be cleaned and stacked, and what began as helping out family turned into
    helping out the whole community.
  </p>
  <p>
    Today the business still runs the way it started — hands-on, and
    involved in every event from setup to the last chair packed away. Every
    tent, table, and signage order still gets checked by someone in the
    family before it goes out the door.
  </p>
  <p>
    What hasn't changed is the reason for doing it: making someone else's
    celebration easier, so they get to actually be at their own party
    instead of running it.
  </p>
</section>

<section class="stat-strip">
  <div class="container stat-row">
    <div class="stat">
      <span class="stat-number">10+</span>
      <span class="stat-label">years in business</span>
    </div>
    <div class="stat">
      <span class="stat-number">500+</span>
      <span class="stat-label">events served</span>
    </div>
    <div class="stat">
      <span class="stat-number">100%</span>
      <span class="stat-label">family-run</span>
    </div>
  </div>
</section>

<section class="partner container">
  <h2 class="section-label">Our partner in hosting</h2>
  <div class="partner-row">
    <div class="partner-photos">
      <img
        src="assets/img/sf-org.jpg"
        alt="South Flaneur Events coordinators with a newlywed couple"
      />
      <img
        src="assets/img/sf-host-2.jpg"
        alt="South Flaneur Events hosts on stage"
      />
    </div>
    <p>
      For hosting, wedding coordination, and full event organizing, we work
      hand-in-hand with
      <a
        href="https://www.facebook.com/southflaneurevents"
        target="_blank"
        rel="noopener"
        >South Flaneur Events</a
      >
      — a company run by family and close friends. They're not part of
      Romero's Party Needs itself, but the partnership means you can book
      rentals and a full hosting team through one call.
    </p>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>