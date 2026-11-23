<?php
// JSON endpoints: username availability (public) and dashboard data (admin session required).
define('APP_RUNNING', true);
$action = $_GET['action'] ?? '';
if ($action === 'dashboard') define('NO_TOUCH', true);     // background refresh must not keep the session alive
require __DIR__ . '/php/bootstrap.php';
require_once __DIR__ . '/php/dashboard_data.php';

if ($action === 'check_username') {
    header('Content-Type: application/json');
    $name = is_string($_GET['u'] ?? null) ? $_GET['u'] : '';
    if ($err = v_username($name)) { echo json_encode(['available' => false, 'message' => $err]); exit; }
    $s = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    $s->execute([$name]);
    echo json_encode($s->fetchColumn() ? ['available' => false, 'message' => 'This username is already taken.'] : ['available' => true, 'message' => 'Username is available.']);
    exit;
}

if ($action === 'dashboard') {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') { http_response_code(403); exit; }   // logged out or not admin: returns nothing
    header('Content-Type: application/json');
    echo json_encode(dashboard_data($_GET));
    exit;
}
http_response_code(404);
exit('Page Not Found');
