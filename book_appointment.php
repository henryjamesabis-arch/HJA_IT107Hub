<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
$u = require_login();
if ($u['role'] === 'admin') redirect('home.php');       // admins review appointments, they do not book them

$in = ['category' => '', 'animal_type' => '', 'exposure_date' => '', 'exposure_details' => '', 'preferred_date' => ''];
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($in) as $k) $in[$k] = trim(post_str($k));
    if ($m = v_dropdown($in['category'], APPOINTMENT_CATEGORIES, 'Please select a valid appointment category.')) $errs['category'] = $m;
    if ($m = v_dropdown($in['animal_type'], ANIMAL_TYPES, 'Please select a valid animal type.')) $errs['animal_type'] = $m;
    if ($m = v_exposure_date($in['exposure_date']))       $errs['exposure_date'] = $m;
    if ($m = v_exposure_details($in['exposure_details'])) $errs['exposure_details'] = $m;
    if ($m = v_preferred_date($in['preferred_date']))     $errs['preferred_date'] = $m;

    if (!$errs) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status IN ('pending','confirmed') AND preferred_date >= CURDATE()");
        $c->execute([$u['id_number']]);
        if ((int)$c->fetchColumn() >= 3) $errs['preferred_date'] = 'You already have 3 upcoming appointments. Cancel one first.';
    }
    if (!$errs) {
        $pdo->prepare('INSERT INTO appointments (user_id, category, animal_type, exposure_date, exposure_details, preferred_date) VALUES (?,?,?,?,?,?)')
            ->execute([$u['id_number'], $in['category'], $in['animal_type'], $in['exposure_date'], $in['exposure_details'] ?: null, $in['preferred_date']]);
        log_event('APPOINTMENT_BOOKED', $u['username'], $u['id_number'], $in['category'] . ' on ' . $in['preferred_date']);
        flash('Appointment booked. The health center will review it.', 'success');
        redirect('home.php');
    }
}
page_header('Book Appointment', 'other');
?>
<section class="card wide">
<?php auth_head('&#128197;', 'Book an Appointment', 'Tell us about the bite or scratch and pick a date.'); ?>
<form method="post" action="book_appointment.php" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <?= select_field('category', 'Appointment Category', APPOINTMENT_CATEGORIES, $in['category'], $errs) ?>
    <?= select_field('animal_type', 'Animal Type', ANIMAL_TYPES, $in['animal_type'], $errs) ?>
    <?= field('exposure_date', 'Exposure Date (bite or scratch)', 'date', $in['exposure_date'], $errs, true, 'max="' . date('Y-m-d') . '"') ?>
    <?= field('preferred_date', 'Preferred Appointment Date', 'date', $in['preferred_date'], $errs, true, 'min="' . date('Y-m-d') . '"') ?>
  </div>
  <?= field('exposure_details', 'Exposure Details', 'text', $in['exposure_details'], $errs, 'optional', 'maxlength="100" data-v="details"') ?>
  <button class="btn" type="submit">Book Appointment</button>
  <a class="btn btn-secondary" href="home.php">Back</a>
</form>
</section>
<?php page_footer(); ?>
