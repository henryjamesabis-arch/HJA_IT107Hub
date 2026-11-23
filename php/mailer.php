<?php
// Email sending (PHPMailer) and the plain-text templates. Install PHPMailer with: composer require phpmailer/phpmailer
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

function send_mail(string $to, string $subject, string $body): bool {
    global $CFG, $MAIL_ERROR;
    $MAIL_ERROR = '';
    // PHPMailer is loaded from Composer's vendor/ folder, or from phpmailer/src/ if you downloaded it by hand
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    $manual = dirname(__DIR__) . '/phpmailer/src/PHPMailer.php';
    if (is_file($autoload)) {
        require_once $autoload;
    } elseif (is_file($manual)) {
        require_once $manual;
        require_once dirname(__DIR__) . '/phpmailer/src/SMTP.php';
        require_once dirname(__DIR__) . '/phpmailer/src/Exception.php';
    } else {
        $MAIL_ERROR = 'PHPMailer is not installed (run: composer require phpmailer/phpmailer)';
        error_log($MAIL_ERROR);
        return false;
    }
    try {
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host = $CFG['smtp_host'];
        $m->Port = (int)$CFG['smtp_port'];
        if (($CFG['mail_transport'] ?? 'smtp') === 'smtp') {
            $m->SMTPAuth = true;
            $m->Username = $CFG['smtp_user'];
            $m->Password = str_replace(' ', '', $CFG['smtp_pass']);     // Google shows the App Password in groups of 4; spaces are not part of it
            $m->SMTPSecure = ((int)$CFG['smtp_port'] === 465) ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;   // 587 = STARTTLS, 465 = SSL
        } else {                                   // Mailpit / MailHog on localhost:1025
            $m->SMTPAuth = false;
            $m->SMTPAutoTLS = false;
        }
        $m->Timeout = 15;
        if (!empty($GLOBALS['MAIL_DEBUG'])) { $m->SMTPDebug = 2; $m->Debugoutput = 'echo'; }   // test_mail.php --debug
        $m->CharSet = 'UTF-8';
        $m->setFrom($CFG['smtp_user'] ?: 'noreply@localhost', $CFG['system_name']);
        $m->addAddress($to);
        $m->Subject = $subject;
        $m->Body = $body;                          // plain text: nothing the attacker typed can run as HTML
        $m->send();
        return true;
    } catch (\Throwable $e) {
        $MAIL_ERROR = $e->getMessage();
        error_log('Mail error: ' . $MAIL_ERROR);
        return false;
    }
}

function send_otp_email(array $user, string $purpose, string $code, string $expiresAt): bool {
    global $CFG, $SEED;
    $titles = ['login' => 'Your log-in code', 'register' => 'Verify your account', 'reset' => 'Password reset code'];
    $body = $CFG['system_name'] . "\n" . $titles[$purpose] . "\n\n"
          . "Your 6-digit code: $code\n"
          . "It expires at " . date('h:i A', strtotime($expiresAt)) . " Philippine time ({$SEED['otp_min']} minutes from now).\n\n"
          . "If you did not request this code, ignore this email and consider changing your password.\n";
    return send_mail($user['email'], $CFG['system_name'] . ' - ' . $titles[$purpose], $body);
}

function send_lockout_alert(array $user, int $failures, int $tier): void {
    global $CFG;
    $body = $CFG['system_name'] . "\n\nFailed sign-in attempts on your account\n\n"
          . "Failed attempts: $failures (lockout tier $tier)\n"
          . 'When: ' . date('F j, Y h:i:s A') . " (Philippine time)\n"
          . 'IP address: ' . client_ip() . "\n"
          . 'Browser/device: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\n"
          . "If this was not you, reset your password now:\n" . $CFG['base_url'] . "/forgot.php\n";
    $ok = send_mail($user['email'], 'Failed sign-in attempts on your account', $body);
    if ($ok) log_event('ALERT_EMAIL_SENT', $user['username'], $user['id_number'], "Lockout tier $tier alert");
}

function send_password_changed_alert(array $user): void {
    global $CFG;
    $body = $CFG['system_name'] . "\n\nYour password was changed\n\n"
          . 'When: ' . date('F j, Y h:i:s A') . " (Philippine time)\n"
          . 'IP address: ' . client_ip() . "\n"
          . 'Browser/device: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\n"
          . "If this was not you, reset your password now:\n" . $CFG['base_url'] . "/forgot.php\n";
    $ok = send_mail($user['email'], 'Your password was changed', $body);
    if ($ok) log_event('ALERT_EMAIL_SENT', $user['username'], $user['id_number'], 'Password changed alert');
}
