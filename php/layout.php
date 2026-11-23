<?php
// Shared layout (handout section 3): prospect name ABOVE the header, header links upper-right, copyright footer.
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

/** $page: 'home' (visitor home), 'login', 'register' or 'other'. Logged-in users always get Home | Log-out. */
function page_header(string $title, string $page = 'other', array $scripts = []): void {
    global $CFG, $SEED;
    $logged = is_logged_in();
    $idle = $logged ? ' data-idle="' . ($SEED['idle_min'] * 60) . '"' : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' - ' . e($CFG['system_name']) . '</title>'
       . '<link rel="stylesheet" href="css/style.css"></head><body' . $idle . '>';
    echo '<div class="aura-layer aura-layer-1" aria-hidden="true"></div><div class="aura-layer aura-layer-2" aria-hidden="true"></div><div class="aura-layer aura-layer-3" aria-hidden="true"></div>'
       . '<div class="paws" aria-hidden="true"><span>&#128062;</span><span>&#128062;</span><span>&#128062;</span><span>&#128062;</span><span>&#128062;</span><span>&#128062;</span></div>'
       . '<div class="page">';
    echo '<div class="prospect">' . e($CFG['system_name']) . '</div>';
    echo '<header class="site-header"><span class="brand">' . e($title) . '</span><nav>';
    if ($logged) {
        echo '<a href="home.php">Home</a> | <a href="logout.php">Log-out</a>';
    } elseif ($page === 'login') {
        echo '<a href="index.php">Home</a> | <a href="register.php">Register</a>';
    } elseif ($page === 'register') {
        echo '<a href="index.php">Home</a> | <a href="login.php">Log-in</a>';
    } else {
        echo '<a href="index.php">Home</a> | <a href="register.php">Register</a> | <a href="login.php">Log-in</a>';
    }
    echo '</nav></header><main class="container">';
    if ($f = take_flash()) echo '<div class="alert alert-' . e($f[1]) . '">' . e($f[0]) . '</div>';
    foreach ($scripts as $s) $GLOBALS['__scripts'][] = $s;
}

function page_footer(array $scripts = []): void {
    global $CFG;
    echo '</main><footer class="site-footer">&copy; ' . date('Y') . ' ' . e($CFG['owner_name']) . '. All rights reserved.</footer></div>';
    echo '<script src="js/script.js"></script>';
    foreach (array_merge($GLOBALS['__scripts'] ?? [], $scripts) as $s) echo '<script src="js/' . e($s) . '"></script>';
    echo '</body></html>';
}

function label_html(string $for, string $text, $req): string {
    $mark = $req === 'optional' ? ' <span class="opt">optional</span>' : ($req ? '<span class="req">*</span>' : '');
    return '<label for="' . e($for) . '">' . e($text) . $mark . '</label>';
}

/** $extra = raw attribute string written by the developer (never user input). */
function field(string $name, string $label, string $type, string $val, array $errs, $req = true, string $extra = ''): string {
    return '<div class="field">' . label_html($name, $label, $req)
         . '<input type="' . e($type) . '" id="' . e($name) . '" name="' . e($name) . '" value="' . e($val) . '" ' . $extra . '>'
         . '<div class="error" data-error-for="' . e($name) . '">' . e($errs[$name] ?? '') . '</div></div>';
}

function select_field(string $name, string $label, array $options, string $val, array $errs, $req = true, string $extra = ''): string {
    $h = '<div class="field">' . label_html($name, $label, $req) . '<select id="' . e($name) . '" name="' . e($name) . '" ' . $extra . '><option value="">-- Select --</option>';
    foreach ($options as $o) $h .= '<option value="' . e($o) . '"' . ($o === $val ? ' selected' : '') . '>' . e($o) . '</option>';
    return $h . '</select><div class="error" data-error-for="' . e($name) . '">' . e($errs[$name] ?? '') . '</div></div>';
}

function captcha_field(array $errs): string {
    return '<div class="field">' . label_html('captcha', 'CAPTCHA', true)
         . '<div class="captcha-row"><img src="captcha.php?t=' . time() . '" alt="CAPTCHA image" id="captcha-img" width="160" height="50">'
         . '<button type="button" class="btn btn-small" id="captcha-refresh">Refresh</button></div>'
         . '<input type="text" id="captcha" name="captcha" autocomplete="off" maxlength="6">'
         . '<div class="error" data-error-for="captcha">' . e($errs['captcha'] ?? '') . '</div></div>';
}

function password_field(string $name, string $label, array $errs, bool $meter = false): string {
    $h = '<div class="field">' . label_html($name, $label, true)
       . '<input type="password" id="' . e($name) . '" name="' . e($name) . '" autocomplete="off"' . ($meter ? ' data-strength="1" data-min="' . (int)$GLOBALS['SEED']['pass_min'] . '"' : '') . '>'
       . '<label class="inline pw-toggle"><input type="checkbox" class="show-pw" data-target="' . e($name) . '"><span>Show Password</span></label>';
    if ($meter) $h .= '<div class="meter"><div class="meter-bar" id="meter-bar-' . e($name) . '"></div></div><div class="meter-text" id="meter-text-' . e($name) . '"></div>';
    return $h . '<div class="error" data-error-for="' . e($name) . '">' . e($errs[$name] ?? '') . '</div></div>';
}

/** Friendly card heading: round icon + title + one line of help. $icon is a trusted HTML entity written by us. */
function auth_head(string $icon, string $title, string $sub): void {
    echo '<div class="auth-head"><span class="auth-icon">' . $icon . '</span><h1>' . e($title) . '</h1><p class="muted">' . e($sub) . '</p></div>';
}
