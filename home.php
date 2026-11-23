<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
$u = require_login();                                  // every protected page checks the session first
$isAdmin = ($u['role'] === 'admin');

// Cancel one of MY OWN appointments (user_id is part of the WHERE, so nobody can cancel someone else's)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('cancel_id') !== '') {
    csrf_verify();
    $st = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')");
    $st->execute([(int)post_str('cancel_id'), $u['id_number']]);
    if ($st->rowCount() === 1) {
        log_event('APPOINTMENT_UPDATED', $u['username'], $u['id_number'], 'cancelled #' . (int)post_str('cancel_id'));
        flash('Appointment cancelled.', 'success');
    }
    redirect('home.php');
}

$blood = decrypt_data($u['blood_type']);               // decrypted only to show it to its owner
$initials = strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['family_name'], 0, 1));

// the three summary numbers at the top
if ($isAdmin) {
    $a = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $b = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending','confirmed') AND preferred_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)")->fetchColumn();
    $c = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND is_verified = 1")->fetchColumn();
    $tiles = ['Pending requests' => $a, 'Appointments in 7 days' => $b, 'Verified residents' => $c];
} else {
    $q = $pdo->prepare("SELECT SUM(status IN ('pending','confirmed') AND preferred_date >= CURDATE()) AS up, SUM(status = 'completed') AS done, COUNT(*) AS total FROM appointments WHERE user_id = ?");
    $q->execute([$u['id_number']]);
    $r = $q->fetch();
    $tiles = ['Upcoming' => (int)$r['up'], 'Completed' => (int)$r['done'], 'Total booked' => (int)$r['total']];
    $ap = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY preferred_date DESC, id DESC LIMIT 20');
    $ap->execute([$u['id_number']]);
    $appointments = $ap->fetchAll();
}
page_header('Home', 'other');
?>
<section class="welcome card">
  <div class="avatar"><?= e($initials) ?></div>
  <div class="welcome-text">
    <h1>Welcome back, <?= e($u['first_name']) ?>!</h1>
    <p class="muted">ID Number <b><?= e($u['id_number']) ?></b> &middot; <?= $isAdmin ? 'Administrator' : 'Resident' ?></p>
  </div>
  <div class="welcome-actions">
    <?php if ($isAdmin): ?>
      <a class="btn" href="admin_appointments.php">Upcoming Appointments</a>
      <a class="btn btn-ghost" href="dashboard.php">Security Dashboard</a>
    <?php else: ?>
      <a class="btn btn-glow" href="book_appointment.php">Book an Appointment</a>
    <?php endif; ?>
  </div>
</section>

<div class="cards">
  <?php foreach ($tiles as $label => $n): ?><div class="stat"><span><?= e($label) ?></span><b><?= (int)$n ?></b></div><?php endforeach; ?>
</div>

<div class="cols">
  <section class="card">
    <h2 class="first">My profile</h2>
    <dl class="profile">
      <div><dt>Name</dt><dd><?= e(trim($u['first_name'] . ' ' . $u['middle_name'] . ' ' . $u['family_name'] . ' ' . $u['name_extension'])) ?></dd></div>
      <div><dt>Age</dt><dd><?= (int)$u['age'] ?></dd></div>
      <div><dt>Address</dt><dd><?= e("{$u['purok_street']}, {$u['barangay']}, {$u['municipality']}, {$u['country']} {$u['zip_code']}") ?></dd></div>
      <div><dt>Email</dt><dd><?= e($u['email']) ?></dd></div>
      <div><dt>Contact Number</dt><dd><?= e($u['contact_number']) ?></dd></div>
      <div><dt>Blood Type</dt><dd><?= e($blood ?? 'Unavailable') ?></dd></div>
      <div><dt>Previous anti-rabies vaccination</dt><dd><?= e($u['prior_vaccination']) ?></dd></div>
      <div><dt>Known allergies</dt><dd><?= e($u['known_allergies'] ?? '-') ?></dd></div>
    </dl>
  </section>

  <?php if (!$isAdmin): ?>
  <section class="card">
    <h2 class="first">My appointments</h2>
    <?php if (!$appointments): ?>
      <p class="muted">No appointments yet. Click "Book an Appointment" to schedule your visit.</p>
    <?php endif; ?>
    <?php foreach ($appointments as $a): $ts = strtotime($a['preferred_date']); ?>
      <div class="appt">
        <div class="appt-date"><b><?= e(date('d', $ts)) ?></b><span><?= e(date('M', $ts)) ?></span></div>
        <div class="appt-body">
          <b><?= e($a['category']) ?></b>
          <span><?= e($a['animal_type']) ?> &middot; exposed <?= e($a['exposure_date']) ?></span>
        </div>
        <span class="tag tag-<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span>
        <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
          <form method="post" action="home.php"><?= csrf_field() ?><input type="hidden" name="cancel_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-small btn-secondary" type="submit">Cancel</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
