<?php
// Config + PDO + seed + crypto. Shared by the web pages and the CLI scripts.
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

// Protection 11: secrets live OUTSIDE the web root (C:\xampp\antirabies_private\config.php)
$cfgFile = getenv('AR_CONFIG') ?: dirname(__DIR__, 3) . '/antirabies_private/config.php';
if (!is_file($cfgFile)) {
    error_log('config.php not found at ' . $cfgFile);
    http_response_code(500);
    exit('A system error occurred. Please try again later.');
}
$CFG = require $cfgFile;
if (!is_string($CFG['enc_key'] ?? null) || !preg_match('/^[0-9a-fA-F]{64}$/', $CFG['enc_key'])) {
    error_log('config.php: enc_key must be exactly 64 hex characters (0-9, a-f). Make one with: php -r "echo bin2hex(random_bytes(32));"');
    http_response_code(500);
    exit('A system error occurred. Please try again later.' . (PHP_SAPI === 'cli' ? "\nconfig.php: enc_key must be exactly 64 hex characters (0-9, a-f). Make one with: php -r \"echo bin2hex(random_bytes(32));\"\n" : ''));
}
$CFG['enc_key_bin'] = hex2bin($CFG['enc_key']);

date_default_timezone_set('Asia/Manila');
// Protection 10: never show PHP/SQL errors to users; log them outside the web root
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $CFG['error_log']);

// Command-line scripts (create_admin.php, monitor.php, test_mail.php) print the real error on screen.
if (PHP_SAPI === 'cli') {
    set_exception_handler(function (Throwable $e) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
        exit(1);
    });
}

try {
    $pdo = new PDO(
        "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
        $CFG['db_user'], $CFG['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements (protection 1)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('A system error occurred. Please try again later.' . (PHP_SAPI === 'cli' ? "\nERROR: " . $e->getMessage() . "\n" : ''));
}

// ---------- Class settings (same for everyone, from the instructor's updated handout, section 2.3) ----------
$SEED = [
    'user_min'     => 6,
    'user_max'     => 20,
    'pass_min'     => 8,
    'name_max'     => 50,
    'forgot_after' => 2,                         // failed log-ins before "Forgot Password?" appears
    'lock'         => [1 => 15, 2 => 30, 3 => 60],   // lockout seconds at 3 / 6 / 9 failures
    'otp_min'      => 5,
    'idle_min'     => 15,
    'ip_thresh'    => 10,                        // failures in 10 minutes, any account
];
$QUESTION_BANK = [
    'Q1' => 'What was the name of your first pet?',
    'Q2' => 'Who was your best friend in elementary school?',
    'Q3' => 'Who was your favorite teacher in high school?',
    'Q4' => "What is your mother's maiden first name?",
    'Q5' => 'What street did you live on in Grade 1?',
    'Q6' => 'What was the first song you learned by heart?',
    'Q7' => 'What was the name of your elementary school?',
    'Q8' => 'What dish did you cook for the first time?',
];

$QUESTION_HINTS = [   // example answers shown as placeholders to make the questions easier
    'Q1' => 'e.g. Bantay', 'Q2' => 'e.g. Pedro Santos', 'Q3' => 'e.g. Maria Reyes', 'Q4' => 'e.g. Teresa',
    'Q5' => 'e.g. Rizal Street', 'Q6' => 'e.g. Twinkle Twinkle', 'Q7' => 'e.g. San Roque Elementary', 'Q8' => 'e.g. Fried Egg',
];

// ---------- Protection 13: AES-256-GCM encryption at rest, random IV each time ----------
function encrypt_data(string $plain): string {
    global $CFG;
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $CFG['enc_key_bin'], OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}
function decrypt_data(string $stored): ?string {
    global $CFG;
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 28) return null;
    $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $CFG['enc_key_bin'], OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $out === false ? null : $out;
}
