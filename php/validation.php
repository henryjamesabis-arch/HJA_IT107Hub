<?php
// Server-side validation. Every function returns an error message (string) or null when valid.
// The messages are mirrored in js/script.js (browser side) - the SERVER is the one that counts.
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

const APPOINTMENT_CATEGORIES = ['Initial Consultation', 'Vaccination (Day 0)', 'Follow-up Dose', 'Booster Dose', 'Wound Assessment'];
const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const VAX_HISTORY = ['Never vaccinated', 'Pre-exposure vaccinated', 'Completed a previous course'];
const ANIMAL_TYPES = ['Dog', 'Cat', 'Bat', 'Monkey', 'Other'];

function v_id_number(string $v): ?string {
    if ($v === '') return 'ID Number is required.';
    return preg_match('/^\d{4}-\d{4}$/', $v) ? null : 'ID Number must follow the format xxxx-xxxx.';
}

/** Name rules (handout 4.5). $max comes from the seed. */
function v_name(string $v, string $label, bool $required, int $max): ?string {
    $v = trim($v);
    if ($v === '') return $required ? "$label is required." : null;
    if (preg_match('/\d/', $v))              return "Numbers are not allowed in $label.";
    if (preg_match('/[^A-Za-z ]/', $v))      return "Special characters are not allowed in $label.";
    if (strpos($v, '  ') !== false)          return 'Double space is not allowed.';
    if (strlen($v) < 2)                      return "$label must be at least 2 characters.";
    if (strlen($v) > $max)                   return "$label must not be longer than $max characters.";
    if ($v === strtoupper($v))               return 'Do not use all capital letters.';
    if (preg_match('/(.)\1\1/i', $v))        return 'Three consecutive identical letters are not allowed.';
    foreach (explode(' ', $v) as $w) {
        if (!preg_match('/^[A-Z][a-z]*$/', $w)) {
            return ctype_upper($w[0]) ? 'Only the first letter of each word may be capital.' : 'First letter of your name must be capital.';
        }
    }
    return null;
}

function v_extension(string $choice, string $other): ?string {
    if ($choice === '') return null;
    if ($choice === 'Jr.' || $choice === 'Sr.') return null;
    if ($choice !== 'Other') return 'Please select a valid name extension.';
    if (!preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/', $other)) return 'Name Extension must be a Roman numeral such as I, II, III, IV, IX or X.';
    return null;
}

/** Returns [error|null, age|null] */
function v_birthdate(string $v): array {
    if ($v === '') return ['Birthdate is required.', null];
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    $errs = DateTime::getLastErrors();
    if (!$d || ($errs && ($errs['warning_count'] || $errs['error_count']))) return ['Please enter a valid birthdate.', null];
    $now = new DateTime('today');
    if ($d > $now) return ['Birthdate cannot be in the future.', null];
    $age = $d->diff($now)->y;                         // the SERVER calculates the age
    if ($age < 18) return ['You must be at least 18 years old to register.', $age];
    return [null, $age];
}

function v_purok(string $v): ?string {
    if ($v === '') return 'Purok / Street is required.';
    if (strpos($v, '  ') !== false) return 'Double space is not allowed.';
    if (!preg_match('/^[A-Za-z0-9 ]+$/', $v)) return 'Purok / Street may contain letters, digits and spaces only.';
    if (strlen($v) < 3 || strlen($v) > 50) return 'Purok / Street must be 3 to 50 characters.';
    return null;
}
function v_place(string $v, string $label, bool $limit = true): ?string {
    if ($v === '') return "$label is required.";
    if (strpos($v, '  ') !== false) return 'Double space is not allowed.';
    if (!preg_match('/^[A-Za-z ]+$/', $v)) return "$label may contain letters and spaces only.";
    if ($limit && (strlen($v) < 2 || strlen($v) > 50)) return "$label must be 2 to 50 characters.";
    foreach (explode(' ', $v) as $w) {
        if (!preg_match('/^[A-Z][a-z]*$/', $w)) return "$label must be in Title Case (for example: Cagayan De Oro).";
    }
    return null;
}
function v_zip(string $v): ?string {
    if ($v === '') return 'ZIP Code is required.';
    return preg_match('/^\d{4}$/', $v) ? null : 'ZIP Code must be exactly 4 digits.';
}

function v_email(string $v): ?string {
    if ($v === '') return 'Email is required.';
    if (strlen($v) > 100 || preg_match('/\s/', $v) || !filter_var($v, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    return null;
}

function v_username(string $v): ?string {
    global $SEED;
    if ($v === '') return 'Username is required.';
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $v)) return 'Username must start with a letter and contain letters and digits only.';
    if (strlen($v) < $SEED['user_min']) return "Username must be at least {$SEED['user_min']} characters.";
    if (strlen($v) > $SEED['user_max']) return "Username must be at most {$SEED['user_max']} characters.";
    return null;
}

/** Strength rules: Weak / Medium / Strong. Same logic is in js/script.js. */
function pw_strength(string $p): string {
    global $SEED;
    $classes = (preg_match('/[a-z]/', $p) ? 1 : 0) + (preg_match('/[A-Z]/', $p) ? 1 : 0)
             + (preg_match('/\d/', $p) ? 1 : 0) + (preg_match('/[^A-Za-z0-9]/', $p) ? 1 : 0);
    if (strlen($p) < $SEED['pass_min'] || $classes < 3) return 'Weak';
    if ($classes === 4 && strlen($p) >= $SEED['pass_min'] + 4) return 'Strong';
    return 'Medium';
}
function v_password(string $p): ?string {
    global $SEED;
    if ($p === '') return 'Password is required.';
    if (strlen($p) < $SEED['pass_min']) return "Password must be at least {$SEED['pass_min']} characters.";
    if (strlen($p) > 64) return 'Password must be at most 64 characters.';
    if (pw_strength($p) === 'Weak') return 'Password is too weak. Use at least 3 of: lowercase, uppercase, digits, symbols.';
    return null;
}

function v_answer(string $v): ?string {
    if ($v === '') return 'Answer is required.';
    if (strpos($v, '  ') !== false) return 'Double space is not allowed.';
    if (!preg_match('/^[A-Za-z ]+$/', $v)) return 'Answer may contain letters and spaces only.';
    if (strlen($v) < 2 || strlen($v) > 50) return 'Answer must be 2 to 50 characters.';
    return null;
}

// ----- system fields (Anti-Rabies & Animal Bite Center) -----
function v_contact(string $v): ?string {
    if ($v === '') return 'Contact Number is required.';
    return preg_match('/^09\d{9}$/', $v) ? null : 'Contact Number must be 11 digits and start with 09.';
}
function v_allergies(string $v): ?string {
    if ($v === '') return null;
    if (strlen($v) > 100 || !preg_match('/^[A-Za-z, ]+$/', $v)) return 'Allergies may contain letters, commas and spaces only (maximum 100 characters).';
    return null;
}
function v_preferred_date(string $v): ?string {
    if ($v === '') return 'Preferred Appointment Date is required.';
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    $errs = DateTime::getLastErrors();
    if (!$d || ($errs && ($errs['warning_count'] || $errs['error_count']))) return 'Please enter a valid appointment date.';
    if ($d < new DateTime('today')) return 'Preferred Appointment Date cannot be in the past.';
    if ($d > new DateTime('+60 days')) return 'Please choose a date within the next 60 days.';
    return null;
}
function v_dropdown(string $v, array $allowed, string $msg): ?string {
    return in_array($v, $allowed, true) ? null : $msg;      // strict: fake dropdown values are rejected
}
function v_exposure_date(string $v): ?string {
    if ($v === '') return 'Exposure Date is required.';
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    $errs = DateTime::getLastErrors();
    if (!$d || ($errs && ($errs['warning_count'] || $errs['error_count']))) return 'Please enter a valid exposure date.';
    if ($d > new DateTime('today')) return 'Exposure Date cannot be in the future.';
    if ($d < new DateTime('-1 year')) return 'Exposure Date cannot be more than 1 year ago.';
    return null;
}
function v_exposure_details(string $v): ?string {
    if ($v === '') return null;
    if (strlen($v) > 100 || !preg_match('/^[A-Za-z0-9,. ]+$/', $v)) return 'Exposure details may contain letters, numbers, commas, periods and spaces only (maximum 100 characters).';
    return null;
}
