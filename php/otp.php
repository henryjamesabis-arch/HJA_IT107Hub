<?php
// One-time passwords: 6 digits, random_int, stored hashed, expiry, 3 tries, single use, resend limits.
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

function otp_hash(string $code): string {
    global $CFG;
    return hash_hmac('sha256', $code, $CFG['enc_key_bin']);     // keyed hash; the DB alone cannot reveal the code
}

/** Seconds the user must still wait before requesting a new OTP (60s cooldown). */
function otp_cooldown(string $uid, string $purpose): int {
    global $pdo;
    $s = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM otps WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$uid, $purpose]);
    $age = $s->fetchColumn();
    return ($age === false) ? 0 : max(0, 60 - (int)$age);
}

/** Create + email a new OTP. Returns [ok(bool), message]. Cancels the previous one. */
function otp_issue(array $user, string $purpose): array {
    global $pdo, $SEED, $CFG, $MAIL_ERROR;
    $uid = $user['id_number'];
    if (otp_cooldown($uid, $purpose) > 0) return [false, 'Please wait for the countdown before requesting a new code.'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM otps WHERE user_id = ? AND purpose = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
    $c->execute([$uid, $purpose]);
    if ((int)$c->fetchColumn() >= 4) return [false, 'Too many code requests. Please try again in 15 minutes.'];   // first send + 3 resends

    $pdo->prepare('UPDATE otps SET is_used = 1 WHERE user_id = ? AND purpose = ? AND is_used = 0')->execute([$uid, $purpose]);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);       // never rand()
    $exp = date('Y-m-d H:i:s', time() + $SEED['otp_min'] * 60);
    $pdo->prepare('INSERT INTO otps (user_id, otp_hash, purpose, expires_at) VALUES (?,?,?,?)')->execute([$uid, otp_hash($code), $purpose, $exp]);
    $otpId = (int)$pdo->lastInsertId();

    if (send_otp_email($user, $purpose, $code, $exp)) {
        log_event('OTP_SENT', $user['username'], $uid, $purpose);
        return [true, 'A 6-digit code was sent to your email.'];
    }
    $pdo->prepare('DELETE FROM otps WHERE id = ?')->execute([$otpId]);      // an email that never left must not count toward the resend limit
    log_event('OTP_SEND_ERROR', $user['username'], $uid, $purpose);
    $why = !empty($CFG['show_mail_errors']) ? ' [' . $MAIL_ERROR . ']' : '';      // testing switch in config.php (keep it false normally)
    return [false, 'We could not send the email. Please try "Resend OTP" in a moment.' . $why];
}

/** Check an entered code. Returns [ok(bool), message]. */
function otp_verify(array $user, string $purpose, string $input): array {
    global $pdo;
    $uid = $user['id_number'];
    $s = $pdo->prepare('SELECT * FROM otps WHERE user_id = ? AND purpose = ? AND is_used = 0 ORDER BY id DESC LIMIT 1');
    $s->execute([$uid, $purpose]);
    $row = $s->fetch();
    if (!$row) return [false, 'This code is no longer valid. Please request a new one.'];
    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare('UPDATE otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
        log_event('OTP_FAILED', $user['username'], $uid, 'expired');
        return [false, 'This code has expired. Please request a new one.'];
    }
    if (!preg_match('/^\d{6}$/', $input)) return [false, 'Enter the 6-digit code.'];

    if (hash_equals($row['otp_hash'], otp_hash($input))) {
        $pdo->prepare('UPDATE otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);   // single use
        return [true, ''];
    }
    $attempts = (int)$row['attempts'] + 1;
    $pdo->prepare('UPDATE otps SET attempts = ?, is_used = ? WHERE id = ?')->execute([$attempts, $attempts >= 3 ? 1 : 0, $row['id']]);
    log_event('OTP_FAILED', $user['username'], $uid, "wrong code ($purpose)");
    if ($attempts >= 3) return [false, 'Too many wrong codes. This code is cancelled - please request a new one.'];
    return [false, 'Incorrect code. You have ' . (3 - $attempts) . ' ' . ((3 - $attempts) === 1 ? 'try' : 'tries') . ' left.'];
}
