<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
if (is_logged_in()) redirect('home.php');

const DUMMY_HASH = '$2y$12$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';   // same bcrypt work for unknown usernames
$errs = [];
$general = '';
$typed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $typed = mb_substr(trim(post_str('username')), 0, 100);
    $_SESSION['last_user'] = $typed;
    $pw = post_str('password');

    if (ip_blocked_seconds() > 0) {
        $general = 'Too many failed attempts from your network. Please try again later.';
    } else {
        if ($typed === '') $errs['username'] = 'Username is required.';
        if ($pw === '')    $errs['password'] = 'Password is required.';
        if (!captcha_ok(post_str('captcha'))) {
            $errs['captcha'] = 'Incorrect CAPTCHA. Please try again.';
            log_event('CAPTCHA_FAILED', $typed, null, 'login');     // decision: a failed CAPTCHA does NOT count as a failed log-in
        }
        if (!$errs) {
            if (lock_remaining($typed) > 0) {
                $general = 'Too many failed attempts. Please wait for the countdown to finish.';
            } else {
                $s = $pdo->prepare('SELECT * FROM users WHERE username = ?');
                $s->execute([$typed]);
                $user = $s->fetch() ?: null;
                $formatOk = v_username($typed) === null && strlen($pw) <= 64;
                $pwOk = password_verify($pw, $user['password_hash'] ?? DUMMY_HASH);
                if ($user && $formatOk && $pwOk) {
                    if ((int)$user['is_verified'] !== 1) {                // correct password but account not verified yet
                        $r = otp_issue($user, 'register');
                        $_SESSION['pending'] = ['uid' => $user['id_number'], 'purpose' => 'register'];
                        flash('Please verify your account first. ' . $r[1], $r[0] ? 'success' : 'error');
                        redirect('otp.php');
                    }
                    [$ok, $msg] = otp_issue($user, 'login');              // no session yet - only the OTP step
                    $_SESSION['pending'] = ['uid' => $user['id_number'], 'purpose' => 'login'];
                    flash($msg, $ok ? 'success' : 'error');
                    redirect('otp.php');
                }
                record_failure($typed, $user);
                $general = 'Invalid username or password.';              // identical for wrong user and wrong password
            }
        }
    }
}

$lockLeft = ($typed !== '' || !empty($_SESSION['last_user'])) ? lock_remaining($typed !== '' ? $typed : $_SESSION['last_user']) : 0;
$lastKey = $typed !== '' ? $typed : ($_SESSION['last_user'] ?? '');
$showForgot = $lastKey !== '' && failure_count($lastKey) >= $SEED['forgot_after'];
$locked = $lockLeft > 0;

page_header('Log-in', 'login');
?>
<section class="card narrow">
<?php auth_head('&#128075;', 'Welcome back', 'Log in to book and track your appointments.'); ?>
<?php if (isset($_GET['out'])): ?><div class="alert alert-info">You have been logged out.</div><?php endif; ?>
<?php if ($general !== ''): ?><div class="alert alert-error"><?= e($general) ?></div><?php endif; ?>
<?php if ($locked): ?>
  <div class="alert alert-error">Locked. Try again in <span class="countdown" data-countdown="<?= (int)$lockLeft ?>" data-reload="1"><?= (int)$lockLeft ?></span> seconds.</div>
<?php endif; ?>
<form method="post" action="login.php" novalidate>
  <?= csrf_field() ?>
  <?= field('username', 'Username', 'text', $typed, $errs, true, 'autocomplete="username"') ?>
  <?= password_field('password', 'Password', $errs) ?>
  <?= captcha_field($errs) ?>
  <button class="btn" type="submit"<?= $locked ? ' disabled' : '' ?>>Log-in</button>
</form>
<?php if ($showForgot): ?><p><a href="forgot.php">Forgot Password? Reset Here</a></p><?php endif; ?>
<p>No account yet?
  <?php if ($locked): ?><span class="disabled-link" aria-disabled="true">Please register here</span>
  <?php else: ?><a href="register.php">Please register here</a><?php endif; ?></p>
</section>
<?php page_footer(); ?>
