<?php
/*
 * SAMPLE CONFIG (fake values) - this file IS committed to Git.
 * Copy it to:  C:\xampp\antirabies_private\config.php   (OUTSIDE htdocs)
 * and fill in the real values. The real config.php must NEVER be committed.
 *
 * Generate the encryption key (64 hex chars) with:
 *   php -r "echo bin2hex(random_bytes(32));"
 */
return [
    'system_name' => 'Barangay Anti-Rabies & Animal Bite Center System',  // your approved prospect name
    'owner_name'  => 'Juan Dela Cruz',        // for the footer copyright
    'base_url'    => 'http://localhost/HJA_IT107FINAL',

    'db_host' => 'localhost',
    'db_name' => 'HJA_IT107',
    'db_user' => 'root',
    'db_pass' => '',

    // 'smtp' = Gmail/other SMTP with App Password, 'mailpit' = local inbox (localhost:1025)
    'mail_transport' => 'smtp',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'your_email@gmail.com',
    'smtp_pass' => 'your_gmail_app_password',

    'enc_key'   => '0000000000000000000000000000000000000000000000000000000000000000',

    // TESTING ONLY: true shows the real email error on the OTP page. Set back to false afterwards.
    'show_mail_errors' => false,

    'error_log' => __DIR__ . '/error.log',
];
