<?php
// Creates the admin account directly in the database (never through the registration form).
// Usage:  php scripts/create_admin.php <username> <email> "<password>"
if (php_sapi_name() !== 'cli') { http_response_code(404); exit('Page Not Found'); }
define('APP_RUNNING', true);
require __DIR__ . '/../php/db.php';
require __DIR__ . '/../php/validation.php';

[$self, $username, $email, $password] = array_pad($argv, 4, '');
if ($username === '' || $email === '' || $password === '') exit("Usage: php scripts/create_admin.php <username> <email> \"<password>\"\n");
foreach ([v_username($username), v_email(strtolower($email)), v_password($password)] as $err) if ($err) exit("Error: $err\n");

$st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id_number = ?');
$id = '0000-0001';
$st->execute([$id]);
if ($st->fetchColumn()) exit("Admin ID $id already exists.\n");

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("INSERT INTO users (id_number, first_name, family_name, birthdate, age, purok_street, barangay, municipality, country, zip_code,
    email, username, password_hash, role, is_verified, contact_number, blood_type, prior_vaccination, privacy_consent_at)
    VALUES (?, 'System', 'Admin', '1990-01-01', 36, 'Admin Office', 'Poblacion', 'Sample City', 'Philippines', '9000',
    ?, ?, ?, 'admin', 1, '09000000000', ?, 'Never vaccinated', NOW())")
    ->execute([$id, strtolower($email), $username, $hash, encrypt_data('O+')]);
$pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$id, $hash]);
echo "Admin '$username' created. Log in through login.php (the OTP goes to $email).\n";
