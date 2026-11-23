<?php
// One OTP page for all three flows (register / login / reset). Cannot be reached without a pending step.
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';

$pending = $_SESSION['pending'] ?? null;
if (!$pending || is_logged_in()) redirect('login.php');       // typing the URL directly sends the user back
$purpose = $pending['purpose'];
$titles = ['login' => 'Enter your log-in code', 'register' => 'Verify your account', 'reset' => 'Password reset code'];

$s = $pdo->prepare('SELECT * FROM users WHERE id_number = ?');
$s->execute([$pending['uid']]);
$user = $s->fetch();
if (!$user) { unset($_SESSION['pending']); redirect('login.php'); }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['resend'])) {
        [$ok, $msg] = otp_issue($user, $purpose);
        flash($msg, $ok ? 'success' : 'error');
        redirect('otp.php');
    }
    [$ok, $msg] = otp_verify($user, $purpose, trim(post_str('otp')));
    if ($ok) {
        unset($_SESSION['pending']);
        if ($purpose === 'register') {
            $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id_number = ?')->execute([$user['id_number']]);
            log_event('ACCOUNT_VERIFIED', $user['username'], $user['id_number']);
            flash('Your account is verified. Your ID Number is ' . $user['id_number'] . '. You can now log in.', 'success');
            redirect('login.php');
        }
        if ($purpose === 'login') {
            session_regenerate_id(true);                         // protection 6: new session ID after log-in
            $_SESSION['uid'] = $user['id_number'];
            $_SESSION['last'] = time();
            clear_failures($user['username']);                   // a successful log-in resets the counter
            log_event('LOGIN_SUCCESS', $user['username'], $user['id_number']);
            redirect('home.php');
        }
        // reset: the server remembers that steps 1-5 were completed (consumed by change_password.php)
        $_SESSION['reset_ok'] = ['uid' => $user['id_number'], 'until' => time() + 600];
        redirect('change_password.php');
    }
    $error = $msg;
}

$cooldown = otp_cooldown($user['id_number'], $purpose);
page_header($titles[$purpose], 'other');
?>
<section class="card narrow">
<?php auth_head('&#9993;', $titles[$purpose], 'Check your email inbox for the code.'); ?>
<p>We sent a 6-digit code to <strong><?= e(preg_replace('/(?<=.).(?=[^@]*@)/', '*', $user['email'])) ?></strong>. It expires in <?= (int)$SEED['otp_min'] ?> minutes.</p>
<form method="post" action="otp.php" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <?= label_html('otp', '6-digit code', true) ?>
    <input type="text" id="otp" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus>
    <div class="error" data-error-for="otp"><?= e($error) ?></div>
  </div>
  <button class="btn" type="submit">Verify</button>
  <button class="btn btn-secondary" type="submit" name="resend" value="1" formnovalidate id="resend-btn"<?= $cooldown > 0 ? ' disabled data-enable-after="' . (int)$cooldown . '"' : '' ?>>Resend OTP</button>
  <span class="countdown" id="resend-countdown"<?= $cooldown > 0 ? ' data-countdown="' . (int)$cooldown . '" data-prefix="Resend available in "' : '' ?>></span>
</form>
</section>
<?php page_footer(); ?>
