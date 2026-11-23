<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
require_once __DIR__ . '/php/dashboard_data.php';

$u = current_user();
if (!$u) redirect('login.php');
if ($u['role'] !== 'admin') {
    log_event('ADMIN_ACCESS_DENIED', $u['username'], $u['id_number'], 'dashboard.php');
    http_response_code(403);
    page_header('Access denied', 'other');
    echo '<section class="card"><h1>Access denied</h1><p>You do not have permission to view this page.</p></section>';
    page_footer();
    exit;
}
log_event('ADMIN_VIEWED_DASHBOARD', $u['username'], $u['id_number']);          // watching the logs is itself logged
$filters = ['date_from' => $_GET['date_from'] ?? '', 'date_to' => $_GET['date_to'] ?? '', 'type' => $_GET['type'] ?? '',
            'username' => $_GET['username'] ?? '', 'ip' => $_GET['ip'] ?? ''];
$filters = array_map(fn($v) => is_string($v) ? $v : '', $filters);
$data = dashboard_data($filters);
page_header('Security Monitoring Dashboard', 'other', ['dashboard.js']);
?>
<div class="dash" id="dash" data-initial="<?= e(json_encode($data)) ?>">
  <div class="dash-top">
    <h1>Security Monitoring Dashboard</h1>
    <span class="badge">READ-ONLY &middot; logs cannot be edited or deleted &middot; auto-refresh 60 s</span>
  </div>
  <noscript><div class="alert alert-error">JavaScript is required to display the dashboard.</div></noscript>

  <div class="cards">
    <div class="stat"><span>Successful log-ins</span><b id="s-success">0</b></div>
    <div class="stat"><span>Failed log-ins</span><b id="s-failed" class="red">0</b></div>
    <div class="stat"><span>Accounts locked now</span><b id="s-locked_now" class="amber">0</b></div>
    <div class="stat"><span>IPs blocked now</span><b id="s-blocked_now" class="amber">0</b></div>
    <div class="stat"><span>OTPs sent / failed</span><b id="s-otp">0 / 0</b></div>
    <div class="stat"><span>Alert emails sent</span><b id="s-alerts">0</b></div>
  </div>

  <div class="grid">
    <section class="card"><h2>Failed log-ins per hour - last 24 hours</h2><canvas id="hourly-chart" width="640" height="220"></canvas></section>
    <section class="card"><h2>Top IP addresses (24 h)</h2>
      <div class="scroll"><table class="table"><thead><tr><th>IP address</th><th>Failures</th><th>Status</th></tr></thead><tbody id="top-ips"></tbody></table></div></section>
    <section class="card"><h2>Flags from the monitoring script</h2><ul id="flags" class="flags"></ul></section>
  </div>

  <section class="card">
    <h2>Recent events (latest 50)</h2>
    <form method="get" action="dashboard.php" class="filters" id="filters">
      <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" aria-label="From date">
      <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" aria-label="To date">
      <select name="type" aria-label="Event type"><option value="">Event type: All</option>
        <?php foreach (EVENT_TYPES as $t): ?><option value="<?= e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="username" placeholder="Username" value="<?= e($filters['username']) ?>">
      <input type="text" name="ip" placeholder="IP" value="<?= e($filters['ip']) ?>">
      <button class="btn btn-small" type="submit">Apply filter</button>
    </form>
    <div class="scroll"><table class="table"><thead><tr><th>Time (PHT)</th><th>Event</th><th>Username</th><th>IP address</th><th>Browser</th></tr></thead>
      <tbody id="events"></tbody></table></div>
    <p class="muted" id="updated"></p>
  </section>
</div>
<?php page_footer(); ?>
