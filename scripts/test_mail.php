<?php
// Sends ONE test email so you can check your Gmail settings (Checkpoint 1 asks for a test email).
// Usage:  php scripts\test_mail.php youremail@gmail.com
if (php_sapi_name() !== 'cli') { http_response_code(404); exit('Page Not Found'); }
define('APP_RUNNING', true);
require __DIR__ . '/../php/db.php';
require __DIR__ . '/../php/mailer.php';

$to = $argv[1] ?? '';
$MAIL_DEBUG = in_array('--debug', $argv, true);     // php scripts\test_mail.php you@gmail.com --debug
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) exit("Usage: php scripts\\test_mail.php youremail@gmail.com\n");
$len = strlen(str_replace(' ', '', (string)$CFG['smtp_pass']));
echo "smtp_pass in config.php has $len characters (a Google App Password has exactly 16 letters).\n";
echo "Sending a test email to $to using {$CFG['mail_transport']} ({$CFG['smtp_host']}:{$CFG['smtp_port']}) as {$CFG['smtp_user']} ...\n";
if (send_mail($to, $CFG['system_name'] . ' - test email', "If you can read this, your email settings work.\nOTP codes will be sent the same way.\n")) {
    echo "SUCCESS - check the inbox (and the spam folder) of $to.\n";
} else {
    echo "FAILED: $MAIL_ERROR\n";
}
