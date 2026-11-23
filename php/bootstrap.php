<?php
// Loaded by every web page: headers, hardened session, and shared helpers.
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/layout.php';

// Protection 12: security headers on every page. no-store stops the back button showing old pages.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Protection 6: session hardening
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('ARSESSID');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
session_start();

// Protection 10: any uncaught error -> generic message, real error to the log file
set_exception_handler(function (Throwable $e) {
    error_log('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    exit('A system error occurred. Please try again later.');
});

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }   // protection 2
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function redirect(string $to): void { header('Location: ' . $to); exit; }
function flash(string $msg, string $type = 'info'): void { $_SESSION['flash'] = [$msg, $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ---------- Protection 9: append-only activity log (INSERT only, never UPDATE/DELETE) ----------
function log_event(string $type, string $typed = '', ?string $userId = null, ?string $details = null): void {
    global $pdo;
    $pdo->prepare('INSERT INTO activity_log (event_type, username_typed, user_id, ip_address, user_agent, details) VALUES (?,?,?,?,?,?)')
        ->execute([$type, mb_substr($typed, 0, 100), $userId, client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500), $details]);
}

// ---------- Protection 3: CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        log_event('CSRF_REJECTED', (string)($_POST['username'] ?? ''), null, $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(403);
        exit('Request rejected: invalid security token. Please go back, reload the page and try again.');
    }
}

// ---------- CAPTCHA (server-side, answer lives in the session) ----------
function captcha_ok($input): bool {
    $answer = $_SESSION['captcha'] ?? '';
    unset($_SESSION['captcha']);                    // single use: new image after every attempt
    return $answer !== '' && is_string($input) && hash_equals(strtolower($answer), strtolower(trim($input)));
}

// ---------- Sessions / roles ----------
function is_logged_in(): bool { return !empty($_SESSION['uid']); }
function logout_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function enforce_idle(): void {
    global $SEED;
    if (!is_logged_in()) return;
    if (time() - ($_SESSION['last'] ?? 0) > $SEED['idle_min'] * 60) {
        logout_session();
        session_start();
        flash('You were logged out because of inactivity.', 'info');
        redirect('login.php');
    }
    if (!defined('NO_TOUCH')) $_SESSION['last'] = time();   // background refresh must NOT extend the session
}
enforce_idle();

function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) return null;
    $s = $pdo->prepare('SELECT * FROM users WHERE id_number = ? AND is_verified = 1');
    $s->execute([$_SESSION['uid']]);
    return $s->fetch() ?: null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

// ---------- IP blocking (protection 5) ----------
function ip_blocked_seconds(): int {
    global $pdo;
    $s = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) FROM ip_blocks WHERE ip_address = ? AND blocked_until > NOW()');
    $s->execute([client_ip()]);
    $v = $s->fetchColumn();
    return $v === false ? 0 : (int)$v;
}
function maybe_block_ip(): void {
    global $pdo, $SEED;
    if (ip_blocked_seconds() > 0) return;
    $s = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE ip_address = ? AND event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
    $s->execute([client_ip()]);
    if ((int)$s->fetchColumn() >= $SEED['ip_thresh']) {
        $pdo->prepare('INSERT INTO ip_blocks (ip_address, blocked_until) VALUES (?, NOW() + INTERVAL 10 MINUTE) ON DUPLICATE KEY UPDATE blocked_until = NOW() + INTERVAL 10 MINUTE')
            ->execute([client_ip()]);
        log_event('IP_BLOCKED', '', null, 'IP reached ' . $SEED['ip_thresh'] . ' failures in 10 minutes');
    }
}

// ---------- Progressive lockout (server-enforced; shared by log-in and Forgot Password) ----------
function lock_key(string $typed): string { return mb_substr(strtolower(trim($typed)), 0, 100); }
function lock_remaining(string $typed): int {
    global $pdo;
    $s = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), locked_until) FROM lockouts WHERE username_key = ? AND locked_until > NOW()');
    $s->execute([lock_key($typed)]);
    $v = $s->fetchColumn();
    return $v === false ? 0 : max(1, (int)$v);
}
function failure_count(string $typed): int {
    global $pdo;
    $s = $pdo->prepare('SELECT failures FROM lockouts WHERE username_key = ?');
    $s->execute([lock_key($typed)]);
    return (int)$s->fetchColumn();
}
function clear_failures(string $typed): void {
    global $pdo;
    $pdo->prepare('DELETE FROM lockouts WHERE username_key = ?')->execute([lock_key($typed)]);
}
/** Count one failure (real OR non-existent username - identical behaviour). $user = DB row or null. */
function record_failure(string $typed, ?array $user, string $detail = 'login'): int {
    global $pdo, $SEED;
    $key = lock_key($typed);
    $pdo->prepare('INSERT INTO lockouts (username_key, failures) VALUES (?, 1) ON DUPLICATE KEY UPDATE failures = failures + 1')->execute([$key]);
    $s = $pdo->prepare('SELECT failures, tier_alerted FROM lockouts WHERE username_key = ?');
    $s->execute([$key]);
    $row = $s->fetch();
    $f = (int)$row['failures'];
    log_event('LOGIN_FAILED', $typed, $user['id_number'] ?? null, $detail);
    if ($f >= 3 && $f % 3 === 0) {
        $tier = min(3, intdiv($f, 3));
        $pdo->prepare('UPDATE lockouts SET locked_until = NOW() + INTERVAL ? SECOND WHERE username_key = ?')->execute([$SEED['lock'][$tier], $key]);
        log_event('LOCKOUT_TIER_' . $tier, $typed, $user['id_number'] ?? null, $SEED['lock'][$tier] . ' seconds');
        if ($user && $tier > (int)$row['tier_alerted']) {            // once per tier, never once per attempt
            send_lockout_alert($user, $f, $tier);
            $pdo->prepare('UPDATE lockouts SET tier_alerted = ? WHERE username_key = ?')->execute([$tier, $key]);
        }
    }
    maybe_block_ip();
    return $f;
}

function post_str(string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? $v : ''; }   // arrays/odd types become ''
function hash_password_value(string $p): string { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
