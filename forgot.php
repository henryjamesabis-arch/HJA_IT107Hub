<?php
// Forgot Password: 1) username + CAPTCHA  2) answer a security question (twice)  3) reset OTP (otp.php)  4) change_password.php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
if (is_logged_in()) redirect('home.php');

const DUMMY_HASH2 = '$2y$12$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
/** The 3 questions the user registered. Unknown username: 3 look-alike questions that are always the same for that name. */
function questions_for(?string $uid, string $typed): array {
    global $pdo;
    if ($uid) {
        $q = $pdo->prepare('SELECT question_code FROM answers WHERE user_id = ? ORDER BY id');
        $q->execute([$uid]);
        $codes = $q->fetchAll(PDO::FETCH_COLUMN);
        if (count($codes) === 3) return $codes;
    }
    $start = crc32(strtolower($typed)) % 8;
    return ['Q' . ($start + 1), 'Q' . ((($start + 3) % 8) + 1), 'Q' . ((($start + 5) % 8) + 1)];
}
if (isset($_GET['restart'])) { unset($_SESSION['fp']); redirect('forgot.php'); }

$errs = [];
$general = '';
$step = isset($_SESSION['fp']) ? 2 : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (ip_blocked_seconds() > 0) {
        $general = 'Too many failed attempts from your network. Please try again later.';
    } elseif (post_str('step') === '1') {
        $typed = mb_substr(trim(post_str('username')), 0, 100);
        if ($typed === '') $errs['username'] = 'Username is required.';
        if (!captcha_ok(post_str('captcha'))) {
            $errs['captcha'] = 'Incorrect CAPTCHA. Please try again.';
            log_event('CAPTCHA_FAILED', $typed, null, 'forgot');
        }
        if (!$errs) {
            // Same screen whether or not the username exists (protection 4).
            $s = $pdo->prepare('SELECT id_number FROM users WHERE username = ? AND is_verified = 1');
            $s->execute([$typed]);
            $uid = $s->fetchColumn() ?: null;
            log_event('RESET_REQUESTED', $typed, $uid);
            $_SESSION['fp'] = ['user' => $typed, 'qs' => questions_for($uid, $typed)];
            $_SESSION['last_user'] = $typed;
            redirect('forgot.php');
        }
    } elseif (post_str('step') === '2' && isset($_SESSION['fp'])) {
        $step = 2;
        $typed = $_SESSION['fp']['user'];
        $code = post_str('question');
        $a1 = trim(post_str('answer'));
        $a2 = trim(post_str('answer2'));
        if (!in_array($code, $_SESSION['fp']['qs'] ?? [], true)) $errs['question'] = 'Please choose one of your questions.';
        if ($m = v_answer($a1)) $errs['answer'] = $m;
        if (!isset($errs['answer']) && $a1 !== $a2) $errs['answer2'] = 'Answers do not match.';   // must match before anything is checked
        if (!$errs) {
            if (lock_remaining($typed) > 0) {
                $general = 'Too many failed attempts. Please wait for the countdown to finish.';
            } else {
                $s = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_verified = 1');
                $s->execute([$typed]);
                $user = $s->fetch() ?: null;
                $hash = DUMMY_HASH2;
                if ($user) {
                    $h = $pdo->prepare('SELECT answer_hash FROM answers WHERE user_id = ? AND question_code = ?');
                    $h->execute([$user['id_number'], $code]);
                    $hash = $h->fetchColumn() ?: DUMMY_HASH2;
                }
                if ($user && password_verify(strtolower($a1), $hash)) {
                    [$ok, $msg] = otp_issue($user, 'reset');
                    $_SESSION['pending'] = ['uid' => $user['id_number'], 'purpose' => 'reset'];
                    unset($_SESSION['fp']);
                    flash($msg, $ok ? 'success' : 'error');
                    redirect('otp.php');
                }
                record_failure($typed, $user, 'forgot-password answer');   // counts toward the SAME lockout as log-in
                $general = 'Invalid username or answer.';
            }
        }
    }
}

$step = isset($_SESSION['fp']) ? 2 : 1;
$lockLeft = $step === 2 ? lock_remaining($_SESSION['fp']['user']) : 0;
page_header('Forgot Password', 'other');
?>
<section class="card narrow">
<?php auth_head('&#128273;', 'Forgot Password', 'Verify it is you, then choose a new password.'); ?>
<?php if ($general !== ''): ?><div class="alert alert-error"><?= e($general) ?></div><?php endif; ?>
<?php if ($lockLeft > 0): ?>
  <div class="alert alert-error">Locked. Try again in <span class="countdown" data-countdown="<?= (int)$lockLeft ?>" data-reload="1"><?= (int)$lockLeft ?></span> seconds.</div>
<?php endif; ?>

<?php if ($step === 1): ?>
<form method="post" action="forgot.php" novalidate>
  <?= csrf_field() ?><input type="hidden" name="step" value="1">
  <?= field('username', 'Username', 'text', '', $errs, true, 'autocomplete="username"') ?>
  <?= captcha_field($errs) ?>
  <button class="btn" type="submit">Continue</button>
</form>
<?php else: ?>
<form method="post" action="forgot.php" novalidate>
  <?= csrf_field() ?><input type="hidden" name="step" value="2">
  <div class="field">
    <strong>Choose the following question</strong>
    <?php foreach ($_SESSION['fp']['qs'] ?? [] as $q): ?>
      <label class="choice"><input type="radio" name="question" value="<?= e($q) ?>"<?= post_str('question') === $q ? ' checked' : '' ?>> <?= e($QUESTION_BANK[$q]) ?></label>
    <?php endforeach; ?>
    <div class="error" data-error-for="question"><?= e($errs['question'] ?? '') ?></div>
  </div>
  <?= password_field('answer', 'Your Answer', $errs) ?>
  <?= password_field('answer2', 'Re-enter Answer', $errs) ?>
  <button class="btn" type="submit"<?= $lockLeft > 0 ? ' disabled' : '' ?>>Continue</button>
  <a class="btn btn-secondary" href="forgot.php?restart=1">Start over</a>
</form>
<?php endif; ?>
</section>
<?php page_footer(); ?>
