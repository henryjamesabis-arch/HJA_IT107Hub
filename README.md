# Barangay Anti-Rabies & Animal Bite Center System (IT 107 Final Project)

PHP + MySQL + vanilla JS. Implements the handout: registration, email OTP, CAPTCHA, log-in with seeded lockout,
alert emails, Forgot/Change Password, 13 protections, read-only monitoring dashboard, and `scripts/monitor.php`.

## Setup (XAMPP, Windows)
1. Copy this folder to `C:\xampp\htdocs\HJA_IT107FINAL`.
2. Start Apache + MySQL. Open phpMyAdmin, import `sql/schema.sql` (it drops and recreates `HJA_IT107`, so re-import after updates).
3. Create the folder `C:\xampp\antirabies_private` (OUTSIDE htdocs). Copy `config.sample.php` into it, rename it
   `config.php`, and fill in: your name, SMTP App Password,
   and a random key: `php -r "echo bin2hex(random_bytes(32));"`
4. Install PHPMailer: in the project folder run `composer require phpmailer/phpmailer`
   (Mailpit alternative: set `'mail_transport' => 'mailpit'` in config).
5. Enable `extension=gd` in php.ini (usually already on) and restart Apache.
6. Create the admin: `php scripts/create_admin.php admin01 you@gmail.com "Str0ng!Passw0rd"`
7. Open http://localhost/HJA_IT107FINAL/ and register a test user.
8. Run the monitor: `php scripts/monitor.php`

## Class settings
Everyone uses the same values (instructor's updated handout, section 2.3). They live in the `$SEED` array in `php/db.php`.
At registration the user picks 3 different questions from the bank; Forgot Password shows only those 3.

## Where each protection lives (for your Threat Model + demo)
| # | Protection | File |
|---|-----------|------|
| 1 | SQL injection - prepared statements, `EMULATE_PREPARES=false` | `php/db.php`, every query |
| 2 | XSS - `e()` / `textContent` | `php/bootstrap.php`, `js/dashboard.js` |
| 3 | CSRF | `csrf_field()` / `csrf_verify()` in `php/bootstrap.php` |
| 4 | No username enumeration | `login.php`, `forgot.php` (dummy hash, same messages) |
| 5 | IP blocking | `maybe_block_ip()` in `php/bootstrap.php` |
| 6 | Session hardening, idle timeout | `php/bootstrap.php`, `otp.php` |
| 7 | Password history | `change_password.php` |
| 8 | Honeypot | `register.php` (field `website`) |
| 9 | Activity log (insert-only) | `log_event()` |
| 10 | Error handling | `php/db.php`, exception handler in bootstrap |
| 11 | Secrets outside web root + .gitignore | `php/db.php`, `.gitignore` |
| 12 | Security headers | `php/bootstrap.php` |
| 13 | AES-256-GCM encryption (Health Tracking PIN) | `encrypt_data()` in `php/db.php` |

## Design choices already made (write these up in your Design Decisions doc)
- Password hashing: bcrypt cost 12. OTPs: HMAC-SHA256 with the secret key. Security answers: bcrypt on the lowercase answer.
- Strength: Weak = under min length or fewer than 3 of {lower, upper, digit, symbol}; Strong = all 4 and min+4 length; else Medium.
- A failed CAPTCHA does NOT count as a failed log-in (otherwise anyone could lock out a victim's account by submitting wrong CAPTCHAs).
- Lockout is keyed by the *typed* username in table `lockouts`, so real and fake usernames behave identically.
- Forgot Password: `otp.php` sets `$_SESSION['reset_ok']` (10 min); `change_password.php` consumes it. Direct URL = redirect to login.
- The back button: every page sends `Cache-Control: no-store` AND every protected page checks the session on the server.
- JS files: `.htaccess` blocks address-bar access by Referer, but a script tag and a typed URL are the same request,
  so this only stops casual browsing. Real protection = server-side validation + no secrets in JS.
- Log table: use the optional least-privilege DB user in `sql/schema.sql` (INSERT/SELECT only on `activity_log`).
- Schedule the monitor: Task Scheduler -> action `php.exe C:\xampp\htdocs\HJA_IT107FINAL\scripts\monitor.php`, trigger every 15 min (cron: `*/15 * * * *`).

## Still yours to do
Proposal sign-off (Checkpoint 1), Threat Model, Design Decisions, Error Message Mapping, Privacy note, Development
Journal, SQL dump with 5+ test records (register a few users, then export from phpMyAdmin), test accounts, and the
**30+ commits over 4 weeks** - commit as you go (do not push this whole folder in one commit).
Then run the Section 18 self-test checklist, and re-read every file so you can explain it at the demo.

## Appointments
Residents register (profile fields only), then log in and use **Book an Appointment**. The admin opens **Upcoming Appointments** from Home to confirm/complete/cancel.
System fields at registration: Contact Number (formatted), Blood Type (dropdown, AES-256 encrypted), Previous Anti-Rabies Vaccination (dropdown), Known Allergies (free text, optional).

## Email (OTP goes to the Gmail the resident registered with)
1. Google Account -> Security -> turn on **2-Step Verification**.
2. Security -> **App passwords** -> create one (name: HJA_IT107) -> copy the 16 characters.
3. In `C:\xampp\antirabies_private\config.php`: `'mail_transport' => 'smtp'`, `'smtp_user' => 'you@gmail.com'`, `'smtp_pass' => 'abcd efgh ijkl mnop'` (the App Password, NOT your Gmail password).
4. Test it: `php scripts\test_mail.php you@gmail.com` - it prints SUCCESS or the exact error.

## If Composer will not install PHPMailer (manual way)
1. Download the zip from github.com/PHPMailer/PHPMailer (Code -> Download ZIP).
2. Open it and copy its **src** folder to `HJA_IT107FINAL\phpmailer\src` (so `phpmailer\src\PHPMailer.php` exists).
3. Run `php scripts\test_mail.php you@gmail.com` again.

## After you replace the project files
The `vendor` folder (PHPMailer) is not part of the zip. If emails stop sending ("PHPMailer is not installed" in error.log),
open PowerShell in the project folder and run:  `composer install`
Tip: copy new files INTO the existing folder instead of deleting the folder first, and `vendor` stays.
