<?php
// Monitoring script - run from the command line:  php scripts/monitor.php
// Opening it in a browser shows "Page Not Found" (CLI check here + scripts/.htaccess blocks web access).
if (php_sapi_name() !== 'cli') { http_response_code(404); exit('Page Not Found'); }
define('APP_RUNNING', true);
require __DIR__ . '/../php/db.php';

$today = date('Y-m-d');
$new = 0; $already = 0; $lines = [];

function add_flag(string $type, string $sev, string $target, string $desc, string $label): void {
    global $pdo, $today, $new, $already, $lines;
    // UNIQUE (flag_type, target_identifier, flagged_date) + INSERT IGNORE = no duplicate for the same pattern/target/day
    $st = $pdo->prepare('INSERT IGNORE INTO security_flags (flag_type, severity, target_identifier, description, flagged_date) VALUES (?,?,?,?,?)');
    $st->execute([$type, $sev, mb_substr($target, 0, 100), $desc, $today]);
    if ($st->rowCount() === 1) $new++; else $already++;
    $lines[] = sprintf('[%-4s] %-18s %s %s', $sev, $label, $target, $desc);
}

$scanned = (int)$pdo->query('SELECT COUNT(*) FROM activity_log WHERE created_at >= (NOW() - INTERVAL 24 HOUR)')->fetchColumn();

// 1. IP over threshold (seeded)
$st = $pdo->prepare("SELECT ip_address, COUNT(*) AS c, COUNT(DISTINCT username_typed) AS u FROM activity_log
                     WHERE event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 24 HOUR)
                     GROUP BY ip_address HAVING COUNT(*) >= ?");
$st->execute([$SEED['ip_thresh']]);
foreach ($st->fetchAll() as $r) add_flag('IP_OVER_THRESHOLD', 'HIGH', $r['ip_address'], "{$r['c']} failures, {$r['u']} usernames", 'IP over threshold');

// 2. Account targeted: one username, failures from 3+ different IPs
$st = $pdo->query("SELECT username_typed, COUNT(DISTINCT ip_address) AS ips FROM activity_log
                   WHERE event_type = 'LOGIN_FAILED' AND username_typed <> '' AND created_at >= (NOW() - INTERVAL 24 HOUR)
                   GROUP BY username_typed HAVING COUNT(DISTINCT ip_address) >= 3");
foreach ($st->fetchAll() as $r) add_flag('ACCOUNT_TARGETED', 'MED', $r['username_typed'], "failures from {$r['ips']} IPs", 'Account targeted');

// 3. OTP guessing: 5+ OTP_FAILED for one account within 10 minutes (sliding window)
$st = $pdo->query("SELECT user_id, username_typed, UNIX_TIMESTAMP(created_at) AS t FROM activity_log
                   WHERE event_type = 'OTP_FAILED' AND user_id IS NOT NULL AND created_at >= (NOW() - INTERVAL 24 HOUR)
                   ORDER BY user_id, created_at");
$byUser = [];
foreach ($st->fetchAll() as $r) { $byUser[$r['user_id']]['name'] = $r['username_typed']; $byUser[$r['user_id']]['t'][] = (int)$r['t']; }
foreach ($byUser as $info) {
    $t = $info['t']; $best = 0; $j = 0;
    for ($i = 0; $i < count($t); $i++) {
        while ($t[$i] - $t[$j] > 600) $j++;
        $best = max($best, $i - $j + 1);
    }
    if ($best >= 5) add_flag('OTP_GUESSING', 'MED', $info['name'], "$best wrong OTPs in 10 min", 'OTP guessing');
}

// 4. Off-hours log-in: LOGIN_SUCCESS between 12:00 AM and 5:00 AM Philippine time
$st = $pdo->query("SELECT username_typed, DATE_FORMAT(MIN(created_at), '%h:%i %p') AS at FROM activity_log
                   WHERE event_type = 'LOGIN_SUCCESS' AND created_at >= (NOW() - INTERVAL 24 HOUR) AND HOUR(created_at) < 5
                   GROUP BY username_typed");
foreach ($st->fetchAll() as $r) add_flag('OFF_HOURS_LOGIN', 'LOW', $r['username_typed'], "log-in at {$r['at']} PHT", 'Off-hours log-in');

echo 'IT 107 Monitoring Report - ' . date('Y-m-d H:i') . " PHT\n";
echo 'Scanned: ' . number_format($scanned) . " events (last 24 hours)\n\n";
echo $lines ? implode("\n", $lines) . "\n\n" : "No suspicious patterns found.\n\n";
echo "New flags written: $new   Already flagged today: $already\n";
