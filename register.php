<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
if (is_logged_in()) redirect('home.php');

$keys = ['first_name','middle_name','family_name','name_ext','name_ext_other','birthdate','purok_street','barangay',
         'municipality','country','zip_code','email','username','contact_number','blood_type','prior_vaccination','known_allergies',
         'question_1','answer_1','question_2','answer_2','question_3','answer_3'];
function next_id_number(PDO $pdo): string {                 // YYYY-0001, YYYY-0002, ... (called inside a transaction)
    $prefix = date('Y') . '-';
    $q = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(id_number, 6) AS UNSIGNED)), 0) + 1 FROM users WHERE id_number LIKE ? FOR UPDATE');
    $q->execute([$prefix . '%']);
    $n = (int)$q->fetchColumn();
    if ($n > 9999) throw new RuntimeException('ID Number range exhausted');
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
$in = array_fill_keys($keys, '');
$in['country'] = 'Philippines';
$errs = [];
$age = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post_str('website') !== '') {                       // protection 8: honeypot filled = bot. Refuse silently.
        log_event('HONEYPOT_TRIGGERED', post_str('username'));
        redirect('index.php');
    }
    foreach (array_keys($in) as $k) $in[$k] = trim(post_str($k));
    $in['email'] = strtolower($in['email']);
    $pw = post_str('password');
    $pw2 = post_str('password2');

    // ---- core fields ----
    if ($m = v_name($in['first_name'], 'First Name', true, $SEED['name_max']))   $errs['first_name'] = $m;
    if ($m = v_name($in['middle_name'], 'Middle Name', false, $SEED['name_max'])) $errs['middle_name'] = $m;
    if ($m = v_name($in['family_name'], 'Family Name', true, $SEED['name_max']))  $errs['family_name'] = $m;
    if ($m = v_extension($in['name_ext'], $in['name_ext_other']))   $errs['name_ext'] = $m;
    [$m, $calcAge] = v_birthdate($in['birthdate']);                 // age is recalculated here, never trusted from the browser
    if ($m) $errs['birthdate'] = $m;
    $age = $calcAge === null ? '' : (string)$calcAge;
    if ($m = v_purok($in['purok_street']))                          $errs['purok_street'] = $m;
    if ($m = v_place($in['barangay'], 'Barangay'))                  $errs['barangay'] = $m;
    if ($m = v_place($in['municipality'], 'Municipality / City'))   $errs['municipality'] = $m;
    if ($m = v_place($in['country'], 'Country', false))             $errs['country'] = $m;
    if ($m = v_zip($in['zip_code']))                                $errs['zip_code'] = $m;
    if ($m = v_email($in['email']))                                 $errs['email'] = $m;
    if ($m = v_username($in['username']))                           $errs['username'] = $m;
    if ($m = v_password($pw))                                       $errs['password'] = $m;
    if (!isset($errs['password']) && $pw !== $pw2)                  $errs['password2'] = 'Passwords do not match.';
    if ($pw2 === '' && !isset($errs['password2']))                  $errs['password2'] = 'Re-enter Password is required.';

    // ---- authentication questions (seeded) ----
    $chosen = [];
    foreach ([1, 2, 3] as $n) {
        $code = $in["question_$n"];
        if (!isset($QUESTION_BANK[$code]))        $errs["question_$n"] = 'Please choose a question.';
        elseif (in_array($code, $chosen, true))   $errs["question_$n"] = 'Please choose 3 different questions.';
        else                                      $chosen[] = $code;
        if ($m = v_answer($in["answer_$n"]))      $errs["answer_$n"] = $m;
    }

    // ---- system fields (patient profile) ----
    if ($m = v_contact($in['contact_number']))                      $errs['contact_number'] = $m;
    if ($m = v_dropdown($in['blood_type'], BLOOD_TYPES, 'Please select a valid blood type.')) $errs['blood_type'] = $m;
    if ($m = v_dropdown($in['prior_vaccination'], VAX_HISTORY, 'Please select a valid vaccination history.')) $errs['prior_vaccination'] = $m;
    if ($m = v_allergies($in['known_allergies']))                   $errs['known_allergies'] = $m;

    // ---- privacy consent + CAPTCHA ----
    if (post_str('privacy') !== '1') $errs['privacy'] = 'You must agree to the Privacy Notice to register.';
    if (!captcha_ok(post_str('captcha'))) {
        $errs['captcha'] = 'Incorrect CAPTCHA. Please try again.';
        log_event('CAPTCHA_FAILED', $in['username'], null, 'register');
    }

    // ---- uniqueness (prepared statements) ----
    foreach ([['email', 'email', 'This email is already registered.'],
              ['username', 'username', 'This username is already taken.']] as [$f, $col, $msg]) {
        if (!isset($errs[$f])) {
            $s = $pdo->prepare("SELECT 1 FROM users WHERE $col = ?");     // $col is a fixed literal from the list above
            $s->execute([$in[$f]]);
            if ($s->fetchColumn()) $errs[$f] = $msg;
        }
    }

    if (!$errs) {
        $ext = $in['name_ext'] === 'Other' ? $in['name_ext_other'] : ($in['name_ext'] ?: null);
        $hash = hash_password_value($pw);
        $newId = '';
        $pdo->beginTransaction();
        try {
            $newId = next_id_number($pdo);                      // ID Number is generated by the server (xxxx-xxxx)
            $pdo->prepare('INSERT INTO users (id_number, first_name, middle_name, family_name, name_extension, birthdate, age, purok_street, barangay,
                    municipality, country, zip_code, email, username, password_hash, contact_number, blood_type, prior_vaccination,
                    known_allergies, privacy_consent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([$newId, $in['first_name'], $in['middle_name'] ?: null, $in['family_name'], $ext, $in['birthdate'], $calcAge,
                    $in['purok_street'], $in['barangay'], $in['municipality'], $in['country'], $in['zip_code'], $in['email'],
                    $in['username'], $hash, $in['contact_number'], encrypt_data($in['blood_type']), $in['prior_vaccination'],
                    $in['known_allergies'] ?: null]);
            $ins = $pdo->prepare('INSERT INTO answers (user_id, question_code, answer_hash) VALUES (?,?,?)');
            foreach ([1, 2, 3] as $n) $ins->execute([$newId, $in["question_$n"], hash_password_value(strtolower($in["answer_$n"]))]);
            $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?,?)')->execute([$newId, $hash]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        log_event('REGISTERED', $in['username'], $newId);
        $user = ['id_number' => $newId, 'email' => $in['email'], 'username' => $in['username']];
        [$ok, $msg] = otp_issue($user, 'register');
        $_SESSION['pending'] = ['uid' => $newId, 'purpose' => 'register'];
        flash($msg, $ok ? 'success' : 'error');
        redirect('otp.php');
    }
}

page_header('Register', 'register');
$stepNames = ['About you', 'Address', 'Account', 'Security questions', 'Health profile', 'Finish'];
?>
<section class="card wide">
<?php auth_head('&#129657;', 'Create your account', 'It takes about 2 minutes. Your ID Number is generated for you after you verify your email.'); ?>
<ol class="stepper" id="stepper">
  <?php foreach ($stepNames as $i => $n): ?><li><span><?= $i + 1 ?></span><em><?= e($n) ?></em></li><?php endforeach; ?>
</ol>
<form method="post" action="register.php" id="register-form" novalidate>
  <?= csrf_field() ?>
  <div class="hp" aria-hidden="true"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

  <div class="step">
    <h2>About you</h2>
    <div class="form-grid">
      <?= field('first_name', 'First Name', 'text', $in['first_name'], $errs, true, 'data-v="name" data-label="First Name" data-req="1" data-max="' . $SEED['name_max'] . '"') ?>
      <?= field('middle_name', 'Middle Name', 'text', $in['middle_name'], $errs, 'optional', 'data-v="name" data-label="Middle Name" data-max="' . $SEED['name_max'] . '"') ?>
      <?= field('family_name', 'Family Name', 'text', $in['family_name'], $errs, true, 'data-v="name" data-label="Family Name" data-req="1" data-max="' . $SEED['name_max'] . '"') ?>
      <?= select_field('name_ext', 'Name Extension', ['Jr.', 'Sr.', 'Other'], $in['name_ext'], $errs, 'optional', 'data-ext-select="1"') ?>
    </div>
    <div id="ext-other-wrap" class="field hidden">
      <?= field('name_ext_other', 'Other Extension (I, II, III, IV, IX, X)', 'text', $in['name_ext_other'], $errs, false, 'maxlength="4"') ?>
    </div>
    <div class="form-grid">
      <?= field('birthdate', 'Birthdate', 'date', $in['birthdate'], $errs, true, 'max="' . date('Y-m-d') . '" data-v="birthdate"') ?>
      <?= field('age', 'Age', 'text', $age, [], true, 'readonly tabindex="-1"') ?>
    </div>
    <div class="step-nav"><span></span><button type="button" class="btn btn-next">Next</button></div>
  </div>

  <div class="step">
    <h2>Address</h2>
    <?= field('purok_street', 'Purok / Street', 'text', $in['purok_street'], $errs, true, 'maxlength="50" data-v="purok"') ?>
    <div class="form-grid">
      <?= field('barangay', 'Barangay', 'text', $in['barangay'], $errs, true, 'maxlength="50" data-v="place" data-label="Barangay" data-req="1"') ?>
      <?= field('municipality', 'Municipality / City', 'text', $in['municipality'], $errs, true, 'maxlength="50" data-v="place" data-label="Municipality / City" data-req="1"') ?>
      <?= field('country', 'Country', 'text', $in['country'], $errs, true, 'maxlength="50" data-v="place" data-label="Country" data-req="1"') ?>
      <?= field('zip_code', 'ZIP Code', 'text', $in['zip_code'], $errs, true, 'maxlength="4" inputmode="numeric" data-v="zip"') ?>
    </div>
    <div class="step-nav"><button type="button" class="btn btn-secondary btn-back">Back</button><button type="button" class="btn btn-next">Next</button></div>
  </div>

  <div class="step">
    <h2>Account</h2>
    <?= field('email', 'Email', 'email', $in['email'], $errs, true, 'maxlength="100" data-v="email"') ?>
    <?= field('username', 'Username', 'text', $in['username'], $errs, true, 'data-v="username" data-min="' . $SEED['user_min'] . '" data-max="' . $SEED['user_max'] . '" autocomplete="off"') ?>
    <div class="hint" id="username-status"></div>
    <?= password_field('password', 'Password', $errs, true) ?>
    <?= password_field('password2', 'Re-enter Password', $errs) ?>
    <div class="step-nav"><button type="button" class="btn btn-secondary btn-back">Back</button><button type="button" class="btn btn-next">Next</button></div>
  </div>

  <div class="step">
    <h2>Security questions</h2>
    <p class="tip">Pick 3 different questions that are easy for you. These help you get back into your account if you forget your password. Capital letters do not matter. Use letters and spaces only (2 to 50 characters).</p>
    <?php foreach ([1, 2, 3] as $n): ?>
      <div class="qcard"><span class="qno"><?= $n ?></span>
        <div class="field">
          <?= label_html("question_$n", "Question $n", true) ?>
          <select id="question_<?= $n ?>" name="question_<?= $n ?>" data-q-select="1" data-v="select" data-msg="Please choose a question.">
            <option value="">-- Choose a question --</option>
            <?php foreach ($QUESTION_BANK as $code => $text): ?>
              <option value="<?= e($code) ?>" data-hint="<?= e($QUESTION_HINTS[$code]) ?>"<?= $in["question_$n"] === $code ? ' selected' : '' ?>><?= e($text) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="error" data-error-for="question_<?= $n ?>"><?= e($errs["question_$n"] ?? '') ?></div>
        </div>
        <?= field("answer_$n", 'Your Answer', 'text', $in["answer_$n"], $errs, true, 'maxlength="50" autocomplete="off" data-v="answer" placeholder="Your answer"') ?>
      </div>
    <?php endforeach; ?>
    <div class="step-nav"><button type="button" class="btn btn-secondary btn-back">Back</button><button type="button" class="btn btn-next">Next</button></div>
  </div>

  <div class="step">
    <h2>Health profile</h2>
    <?= field('contact_number', 'Contact Number', 'text', $in['contact_number'], $errs, true, 'placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" data-v="contact"') ?>
    <div class="form-grid">
      <?= select_field('blood_type', 'Blood Type', BLOOD_TYPES, $in['blood_type'], $errs, true, 'data-v="select" data-msg="Please select a valid blood type."') ?>
      <?= select_field('prior_vaccination', 'Previous Anti-Rabies Vaccination', VAX_HISTORY, $in['prior_vaccination'], $errs, true, 'data-v="select" data-msg="Please select a valid vaccination history."') ?>
    </div>
    <?= field('known_allergies', 'Known Allergies', 'text', $in['known_allergies'], $errs, 'optional', 'maxlength="100" data-v="allergies"') ?>
    <div class="step-nav"><button type="button" class="btn btn-secondary btn-back">Back</button><button type="button" class="btn btn-next">Next</button></div>
  </div>

  <div class="step">
    <h2>Finish</h2>
    <div class="field">
      <label class="inline consent"><input type="checkbox" name="privacy" value="1"> I have read the <a href="privacy.php" target="_blank" rel="noopener">Privacy Notice</a> and agree to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 (RA 10173).</label>
      <div class="error" data-error-for="privacy"><?= e($errs['privacy'] ?? '') ?></div>
    </div>
    <?= captcha_field($errs) ?>
    <div class="step-nav"><button type="button" class="btn btn-secondary btn-back">Back</button><button class="btn btn-glow" type="submit">Create account</button></div>
  </div>
</form>
</section>
<?php page_footer(); ?>
