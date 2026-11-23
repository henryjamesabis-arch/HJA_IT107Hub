<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
if (is_logged_in()) redirect('home.php');
page_header('Home', 'home');
?>
<section class="hero">
  <div class="hero-text">
    <span class="pill"><i class="dot"></i> Barangay Health Center &middot; Registration open</span>
    <h1>Animal bite care,<br><span class="grad">fast, safe and organized.</span></h1>
    <p class="lead">Register once, book your anti-rabies appointment online, and let the health center keep track of your treatment.</p>
    <svg class="ecg" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true">
      <path class="ecg-base" d="M0 30 H70 L82 30 L92 8 L104 52 L116 30 H190 L202 30 L212 12 L222 46 L232 30 H310 L322 30 L332 10 L344 50 L354 30 H400" pathLength="400"/>
      <path class="ecg-live" d="M0 30 H70 L82 30 L92 8 L104 52 L116 30 H190 L202 30 L212 12 L222 46 L232 30 H310 L322 30 L332 10 L344 50 L354 30 H400" pathLength="400"/>
    </svg>
    <div class="cta">
      <a class="btn btn-glow" href="register.php">Register as Resident</a>
      <a class="btn btn-ghost" href="login.php">Log-in</a>
    </div>
    <ul class="trust"><li>Email-verified accounts</li><li>Encrypted health data</li><li>Free for residents</li></ul>
  </div>

  <aside class="hero-card card">
    <h2 class="first">Bitten or scratched?</h2>
    <p class="muted">Do this right away.</p>
    <ol class="timeline">
      <li><span class="num">1</span><p>Wash the wound with soap and running water for at least 15 minutes.</p></li>
      <li><span class="num">2</span><p>Register your account and verify your email.</p></li>
      <li><span class="num">3</span><p>Book your appointment with the bite details.</p></li>
      <li><span class="num">4</span><p>Visit the health center for your vaccine schedule.</p></li>
    </ol>
  </aside>
</section>

<section class="services">
  <article class="service card"><span class="ico">&#128197;</span><h3>Book online</h3><p>Pick your appointment category and preferred date.</p></article>
  <article class="service card"><span class="ico">&#129657;</span><h3>Track every visit</h3><p>See whether each appointment is pending, confirmed or done.</p></article>
  <article class="service card"><span class="ico">&#128737;</span><h3>Protected record</h3><p>One-time codes at log-in and encrypted health data (RA 10173).</p></article>
</section>
<p class="muted center">Read our <a href="privacy.php">Privacy Notice</a>.</p>
<?php page_footer(); ?>
