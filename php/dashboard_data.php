<?php
// Read-only queries for the monitoring dashboard. SELECT only - this file never changes the log (protection / S11).
if (!defined('APP_RUNNING')) { http_response_code(404); exit('Page Not Found'); }

const EVENT_TYPES = ['REGISTERED','ACCOUNT_VERIFIED','LOGIN_SUCCESS','LOGIN_FAILED','OTP_SENT','OTP_FAILED','LOCKOUT_TIER_1','LOCKOUT_TIER_2',
    'LOCKOUT_TIER_3','IP_BLOCKED','ALERT_EMAIL_SENT','RESET_REQUESTED','PASSWORD_CHANGED','CAPTCHA_FAILED','CSRF_REJECTED',
    'ADMIN_VIEWED_DASHBOARD','ADMIN_ACCESS_DENIED','HONEYPOT_TRIGGERED','OTP_SEND_ERROR','APPOINTMENT_BOOKED','APPOINTMENT_UPDATED'];

function dashboard_data(array $f): array {
    global $pdo;

    // 1. summary cards (today)
    $counts = [];
    foreach ($pdo->query('SELECT event_type, COUNT(*) AS c FROM activity_log WHERE created_at >= CURDATE() GROUP BY event_type')->fetchAll() as $r) {
        $counts[$r['event_type']] = (int)$r['c'];
    }
    $summary = [
        'success'      => $counts['LOGIN_SUCCESS'] ?? 0,
        'failed'       => $counts['LOGIN_FAILED'] ?? 0,
        'locked_now'   => (int)$pdo->query('SELECT COUNT(*) FROM lockouts WHERE locked_until > NOW()')->fetchColumn(),
        'blocked_now'  => (int)$pdo->query('SELECT COUNT(*) FROM ip_blocks WHERE blocked_until > NOW()')->fetchColumn(),
        'otp_sent'     => $counts['OTP_SENT'] ?? 0,
        'otp_failed'   => $counts['OTP_FAILED'] ?? 0,
        'alerts'       => $counts['ALERT_EMAIL_SENT'] ?? 0,
    ];

    // 2. failed log-ins per hour, last 24 hours
    $per = [];
    foreach ($pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H') AS h, COUNT(*) AS c FROM activity_log
                          WHERE event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 24 HOUR) GROUP BY h")->fetchAll() as $r) {
        $per[$r['h']] = (int)$r['c'];
    }
    $hourly = [];
    for ($i = 23; $i >= 0; $i--) {
        $t = strtotime("-$i hour");
        $hourly[] = ['label' => date('H:00', $t), 'count' => $per[date('Y-m-d H', $t)] ?? 0];
    }

    // 3. top 5 IPs by failures (24h) + blocked?
    $top = $pdo->query("SELECT a.ip_address, COUNT(*) AS failures,
                (SELECT COUNT(*) FROM ip_blocks b WHERE b.ip_address = a.ip_address AND b.blocked_until > NOW()) AS blocked
              FROM activity_log a WHERE a.event_type = 'LOGIN_FAILED' AND a.created_at >= (NOW() - INTERVAL 24 HOUR)
              GROUP BY a.ip_address ORDER BY failures DESC LIMIT 5")->fetchAll();

    // 4. flags from scripts/monitor.php
    $flags = $pdo->query('SELECT flag_type, severity, target_identifier, description, flagged_date FROM security_flags ORDER BY id DESC LIMIT 10')->fetchAll();

    // 5. recent events (latest 50) with filters - all values are bound parameters
    $where = []; $params = [];
    $from = (string)($f['date_from'] ?? ''); $to = (string)($f['date_to'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }
    $type = (string)($f['type'] ?? '');
    if (in_array($type, EVENT_TYPES, true)) { $where[] = 'event_type = ?'; $params[] = $type; }
    $un = mb_substr((string)($f['username'] ?? ''), 0, 100);
    if ($un !== '') { $where[] = 'username_typed LIKE ?'; $params[] = '%' . addcslashes($un, '%_\\') . '%'; }
    $ip = mb_substr((string)($f['ip'] ?? ''), 0, 45);
    if ($ip !== '') { $where[] = 'ip_address LIKE ?'; $params[] = addcslashes($ip, '%_\\') . '%'; }
    $sql = 'SELECT created_at, event_type, username_typed, ip_address, user_agent FROM activity_log'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 50';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return ['summary' => $summary, 'hourly' => $hourly, 'top_ips' => $top, 'flags' => $flags,
            'events' => $st->fetchAll(), 'updated' => date('h:i:s A')];
}
