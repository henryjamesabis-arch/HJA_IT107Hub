<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
page_header('Privacy Notice', 'other');
?>
<section class="card">
  <h1>Privacy Notice</h1>
  <p class="muted">In accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

  <h2>What we collect and why</h2>
  <table class="table">
    <tr><th>Data</th><th>Type under RA 10173</th><th>Why it is needed</th></tr>
    <tr><td>ID Number, full name, name extension</td><td>Personal information</td><td>To identify you and your patient record.</td></tr>
    <tr><td>Birthdate and age</td><td>Personal information</td><td>To confirm you are an adult account holder.</td></tr>
    <tr><td>Address (purok, barangay, municipality, country, ZIP)</td><td>Personal information</td><td>To locate your barangay for follow-up of bite cases.</td></tr>
    <tr><td>Email address</td><td>Personal information</td><td>To send one-time codes and security alerts.</td></tr>
    <tr><td>Username, hashed password, hashed security answers</td><td>Personal information</td><td>To authenticate you. These are stored hashed, never as plain text.</td></tr>
    <tr><td>Contact number</td><td>Personal information</td><td>To reach you about your appointment.</td></tr>
    <tr><td>Blood type</td><td>Sensitive personal information (health)</td><td>For your patient record. Stored encrypted (AES-256).</td></tr>
    <tr><td>Previous anti-rabies vaccination, known allergies</td><td>Sensitive personal information (health)</td><td>So the nurse can choose the right treatment safely.</td></tr>
    <tr><td>Appointment category, animal type, exposure date and details, preferred date</td><td>Sensitive personal information (health)</td><td>To schedule and prioritise post-exposure treatment.</td></tr>
    <tr><td>IP address and browser, with date and time of activity</td><td>Personal information</td><td>To detect and investigate attacks on accounts.</td></tr>
  </table>

  <h2>How it is protected</h2>
  <p>Passwords, security answers and one-time codes are hashed. Your blood type is encrypted. All database queries are parameterised, forms are protected against forged requests, and sessions expire when idle.</p>

  <h2>How long we keep it</h2>
  <p>Account and treatment records are kept while your account is active and for as long as required for public-health records. Activity logs are kept for one year for security review.</p>

  <h2>Your rights and who to contact</h2>
  <p>You may ask to access, correct or delete your data. Contact the Barangay Data Protection Officer at the Barangay Health Center.</p>
</section>
<?php page_footer(); ?>
