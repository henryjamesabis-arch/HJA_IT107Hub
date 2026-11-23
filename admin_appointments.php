<?php
// Admin-only: upcoming appointments. Status changes are normal data updates; the activity log itself is never edited.
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
$u = current_user();
if (!$u) redirect('login.php');
if ($u['role'] !== 'admin') {
    log_event('ADMIN_ACCESS_DENIED', $u['username'], $u['id_number'], 'admin_appointments.php');
    http_response_code(403);
    page_header('Access denied', 'other');
    echo '<section class="card"><h1>Access denied</h1><p>You do not have permission to view this page.</p></section>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $status = post_str('status');
    if (in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {      // whitelist
        $st = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $st->execute([$status, (int)post_str('id')]);
        if ($st->rowCount() === 1) log_event('APPOINTMENT_UPDATED', $u['username'], $u['id_number'], "#" . (int)post_str('id') . " -> $status");
    }
    redirect('admin_appointments.php');
}

$sql = 'SELECT a.*, u.first_name, u.family_name, u.age, u.barangay, u.contact_number, u.prior_vaccination, u.known_allergies
        FROM appointments a JOIN users u ON u.id_number = a.user_id ';
$upcoming = $pdo->query($sql . "WHERE a.preferred_date >= CURDATE() AND a.status IN ('pending','confirmed') ORDER BY a.preferred_date, a.id")->fetchAll();
$recent = $pdo->query($sql . "WHERE a.preferred_date < CURDATE() OR a.status IN ('completed','cancelled') ORDER BY a.preferred_date DESC, a.id DESC LIMIT 20")->fetchAll();

function appt_rows(array $rows, bool $actions): void {
    if (!$rows) { echo '<tr><td colspan="8" class="muted">Nothing to show.</td></tr>'; return; }
    foreach ($rows as $a) {
        echo '<tr><td>' . e($a['preferred_date']) . '</td><td>' . e($a['first_name'] . ' ' . $a['family_name']) . ' (' . (int)$a['age'] . ')</td>'
           . '<td>' . e($a['barangay']) . '</td><td>' . e($a['contact_number']) . '</td><td>' . e($a['category']) . '</td>'
           . '<td>' . e($a['animal_type']) . '<br><span class="muted">' . e($a['exposure_date']) . ' ' . e($a['exposure_details'] ?? '') . '</span></td>'
           . '<td><span class="tag tag-' . e($a['status']) . '">' . e(ucfirst($a['status'])) . '</span></td><td>';
        if ($actions) {
            foreach (['confirmed' => 'Confirm', 'completed' => 'Complete', 'cancelled' => 'Cancel'] as $val => $lbl) {
                if ($val === $a['status']) continue;
                echo '<form method="post" action="admin_appointments.php" class="inline-form">' . csrf_field()
                   . '<input type="hidden" name="id" value="' . (int)$a['id'] . '"><input type="hidden" name="status" value="' . e($val) . '">'
                   . '<button class="btn btn-small' . ($val === 'cancelled' ? ' btn-secondary' : '') . '" type="submit">' . e($lbl) . '</button></form> ';
            }
        }
        echo '</td></tr>';
    }
}
$n = $pdo->query("SELECT SUM(status = 'pending') AS p, SUM(status = 'confirmed' AND preferred_date >= CURDATE()) AS c,
                  SUM(preferred_date = CURDATE() AND status IN ('pending','confirmed')) AS t FROM appointments")->fetch();
page_header('Upcoming Appointments', 'other');
$head = '<thead><tr><th>Date</th><th>Patient (age)</th><th>Barangay</th><th>Contact</th><th>Category</th><th>Animal / exposure</th><th>Status</th><th></th></tr></thead>';
?>
<div class="cards">
  <div class="stat"><span>Pending requests</span><b class="amber"><?= (int)$n['p'] ?></b></div>
  <div class="stat"><span>Confirmed (upcoming)</span><b><?= (int)$n['c'] ?></b></div>
  <div class="stat"><span>Today</span><b><?= (int)$n['t'] ?></b></div>
</div>
<section class="card">
  <h1>Upcoming Appointments</h1>
  <div class="scroll"><table class="table"><?= $head ?><tbody><?php appt_rows($upcoming, true); ?></tbody></table></div>
</section>
<section class="card">
  <h2>Past and closed (latest 20)</h2>
  <div class="scroll"><table class="table"><?= $head ?><tbody><?php appt_rows($recent, false); ?></tbody></table></div>
</section>
<?php page_footer(); ?>
