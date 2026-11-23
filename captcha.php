<?php
// Own image CAPTCHA (PHP GD). The answer is stored ONLY in the session and checked on the server.
// If the GD extension is not enabled, an SVG image is served instead so the form still works.
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';

$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';        // no 0/O/1/I confusion
$code = '';
for ($i = 0; $i < 5; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
$_SESSION['captcha'] = $code;
session_write_close();                               // save the answer NOW, before the image is sent
while (ob_get_level() > 0) ob_end_clean();           // nothing but the image may be sent

if (!function_exists('imagecreatetruecolor')) {      // GD missing -> SVG fallback
    error_log('CAPTCHA: GD extension is not enabled (php.ini: extension=gd). Serving SVG fallback.');
    header('Content-Type: image/svg+xml');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="50"><rect width="160" height="50" fill="#f0f4f8"/>';
    for ($i = 0; $i < 5; $i++) {
        $svg .= '<line x1="' . random_int(0, 160) . '" y1="' . random_int(0, 50) . '" x2="' . random_int(0, 160) . '" y2="' . random_int(0, 50) . '" stroke="#a9b7c6" stroke-width="2"/>';
    }
    for ($i = 0; $i < 5; $i++) {
        $x = 18 + $i * 28; $y = random_int(30, 38); $r = random_int(-25, 25);
        $svg .= '<text x="' . $x . '" y="' . $y . '" font-size="28" font-family="Arial" font-weight="bold" fill="#1c2733" transform="rotate(' . $r . ' ' . $x . ' ' . $y . ')">' . $code[$i] . '</text>';
    }
    echo $svg . '</svg>';
    exit;
}

$small = imagecreatetruecolor(100, 25);
imagefilledrectangle($small, 0, 0, 100, 25, imagecolorallocate($small, 240, 244, 248));
for ($i = 0; $i < 6; $i++) {                         // noise lines
    imageline($small, random_int(0, 100), random_int(0, 25), random_int(0, 100), random_int(0, 25),
        imagecolorallocate($small, random_int(120, 200), random_int(120, 200), random_int(120, 200)));
}
for ($i = 0; $i < 5; $i++) {
    imagestring($small, 5, 8 + $i * 18, random_int(2, 7), $code[$i],
        imagecolorallocate($small, random_int(10, 90), random_int(10, 90), random_int(10, 90)));
}
$img = imagecreatetruecolor(160, 50);                // enlarge + wave distortion
imagefill($img, 0, 0, imagecolorallocate($img, 240, 244, 248));
$phase = random_int(0, 628) / 100;
for ($x = 0; $x < 160; $x++) {
    $dy = (int)round(sin($x / 9 + $phase) * 3);
    imagecopyresampled($img, $small, $x, $dy, (int)($x * 100 / 160), 0, 1, 50, 1, 25);
}
header('Content-Type: image/png');
imagepng($img);
