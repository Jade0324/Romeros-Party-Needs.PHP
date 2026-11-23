<?php
$pageTitle = "About Us | Romero's Party Needs";
$activePage = "about";
require_once 'includes/header.php';
?>

    <header class="page-header">
      <div class="awning awning--sm" aria-hidden="true"></div>
      <div class="container page-header-content">
        <h1 class="page-title">Services &amp; rentals</h1>
        <p class="page-sub">
          Everything for the setup, and everyone to run the day. Mix and match,
          or book it all in one call.
        </p>
      </div>
    </header>

    <section class="offerings container">
      <h2 class="section-label">Party equipment rentals</h2>
      <div class="offerings-row offerings-row--4">
        <div class="offering">
          <h3>Tables &amp; chairs</h3>
          <p>
            Round and rectangular tables with chairs to match, in quantities for
            intimate gatherings or full receptions.
          </p>
        </div>
        <div class="offering">
          <h3>Tents</h3>
          <p>
            Weatherproof tents in a range of sizes, set up and taken down by our
            crew so you don't have to.
          </p>
        </div>
        <div class="offering">
          <h3>Signage</h3>
          <p>
            Custom welcome signs, seating charts, and directional signage
            designed around your event.
          </p>
        </div>
        <div class="offering">
          <h3>Catering setups</h3>
          <p>
            Buffet tables, serving stations, and linens ready for your caterer
            to plate and serve.
          </p>
        </div>
      </div>
    </section>

    <section class="mini-gallery mini-gallery--tight container">
      <div class="mini-gallery-grid mini-gallery-grid--6">
        <img
          src="assets/img/rpn-tbch.jpg"
          alt="Red and green Christmas party table setup"
        />
        <img
          src="assets/img/rpn-tbch-2.jpg"
          alt="Pink poolside reception seating"
        />
        <img
          src="assets/img/rpn-tbch-3.jpg"
          alt="Poolside patio table and chair setup"
        />
        <img
          src="assets/img/rpn-tbch-5.jpg"
          alt="Black and gold table setup on a patio"
        />
        <img
          src="assets/img/rpn-bd.jpg"
          alt="Catering table with pink skirting and chafing dish"
        />
        <img
          src="assets/img/rpn-bd-2.jpg"
          alt="70th birthday balloon backdrop"
        />
      </div>
    </section>

    <section class="offerings offerings--alt">
      <div class="container">
        <h2 class="section-label">Professional services</h2>
        <p class="partner-note">
          Hosting and event coordination is handled by
          <a
            href="https://www.facebook.com/southflaneurevents"
            target="_blank"
            rel="noopener"
            >South Flaneur Events</a
          >, our partner company run by family and close friends — booked
          through us as one seamless package.
        </p>
        <div class="offerings-row offerings-row--3">
          <div class="offering">
            <img
              class="offering-photo"
              src="assets/img/sf-host-2.jpg"
              alt="South Flaneur Events hosts on stage"
            />
            <h3>Event hosting</h3>
            <p>
              Hosts who keep the program on schedule and guests engaged, from
              welcome to send-off.
            </p>
          </div>
          <div class="offering">
            <img
              class="offering-photo"
              src="assets/img/sf-org.jpg"
              alt="South Flaneur Events coordinators with a newlywed couple"
            />
            <h3>Wedding organizers</h3>
            <p>
              Full-day coordination so you can be a guest at your own wedding,
              not the one running it.
            </p>
          </div>
          <div class="offering">
            <img
              class="offering-photo"
              src="assets/img/sf-host.jpg"
              alt="South Flaneur Events host engaging the crowd"
            />
            <h3>Party organizers</h3>
            <p>
              Planning and on-site coordination for birthdays, anniversaries,
              and other celebrations.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="cta-band">
      <div class="container cta-band-content">
        <h2>Ready to book?</h2>
        <p>
          Tell us your date and headcount, and we'll put together a package that
          fits.
        </p>
        <a href="contact.php" class="btn-primary">Get in touch</a>
      </div>
    </section>

<?php require_once 'includes/footer.php'; ?>