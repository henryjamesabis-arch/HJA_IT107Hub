<?php
// Reachable ONLY after the reset OTP was accepted (session flag set by otp.php, consumed here).
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';

$ro = $_SESSION['reset_ok'] ?? null;
if (!$ro || $ro['until'] < time()) { unset($_SESSION['reset_ok']); redirect('login.php'); }
$s = $pdo->prepare('SELECT * FROM users WHERE id_number = ?');
$s->execute([$ro['uid']]);
$user = $s->fetch();
if (!$user) { unset($_SESSION['reset_ok']); redirect('login.php'); }
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $p1 = post_str('password');
    $p2 = post_str('password2');
    if ($m = v_password($p1)) {
        $errs['password'] = $m;
    } elseif ($p1 !== $p2) {
        $errs['password2'] = 'Mismatch Password.';
    } else {
        $h = $pdo->prepare('SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT 3');   // protection 7
        $h->execute([$user['id_number']]);
        $reused = password_verify($p1, $user['password_hash']);
        foreach ($h->fetchAll(PDO::FETCH_COLUMN) as $old) if (password_verify($p1, $old)) $reused = true;
        if ($reused) $errs['password'] = 'You cannot reuse a recent password.';
    }
    if (!$errs) {
        $new = hash_password_value($p1);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_number = ?')->execute([$new, $user['id_number']]);
        $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?,?)')->execute([$user['id_number'], $new]);
        $pdo->commit();
        unset($_SESSION['reset_ok']);                       // the reset is used up: reload / back cannot replay it
        clear_failures($user['username']);
        log_event('PASSWORD_CHANGED', $user['username'], $user['id_number']);
        send_password_changed_alert($user);
        flash('Successfully Change Password', 'success');
        redirect('login.php');
    }
}
page_header('Change Password', 'other');
?>
<section class="card narrow">
<?php auth_head('&#128737;', 'Change Password', 'Choose a strong password you have not used recently.'); ?>
<p class="muted">Minimum <?= (int)$SEED['pass_min'] ?> characters. Weak passwords are not accepted.</p>
<form method="post" action="change_password.php" novalidate>
  <?= csrf_field() ?>
  <?= password_field('password', 'Enter Password', $errs, true) ?>
  <?= password_field('password2', 'Re-enter Password', $errs) ?>
  <button class="btn" type="submit">Change Password</button>
</form>
</section>
<?php page_footer(); ?>
