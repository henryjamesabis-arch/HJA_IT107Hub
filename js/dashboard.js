// Dashboard rendering. Every value is inserted with textContent (never innerHTML) so attacker-typed
// usernames like <script>alert(1)</script> are shown as text and never run.
(function () {
  'use strict';
  var root = document.getElementById('dash');
  if (!root) return;

  function el(tag, text, cls) {
    var n = document.createElement(tag);
    if (text !== undefined) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function set(id, v) { document.getElementById(id).textContent = v; }

  function drawChart(rows) {
    var c = document.getElementById('hourly-chart'), g = c.getContext('2d');
    var w = c.width, h = c.height, pad = 28, max = 1;
    rows.forEach(function (r) { if (r.count > max) max = r.count; });
    g.clearRect(0, 0, w, h);
    g.font = '10px sans-serif'; g.fillStyle = '#9aa5b5'; g.strokeStyle = 'rgba(255,255,255,0.25)';
    g.beginPath(); g.moveTo(pad, 8); g.lineTo(pad, h - pad); g.lineTo(w - 6, h - pad); g.stroke();
    g.fillText(String(max), 4, 16); g.fillText('0', 14, h - pad);
    var slot = (w - pad - 6) / rows.length, bw = slot * 0.7;
    rows.forEach(function (r, i) {
      var bh = (h - pad - 14) * (r.count / max), x = pad + i * slot + (slot - bw) / 2;
      g.fillStyle = r.count === max && r.count > 0 ? '#ff6b61' : '#6d8dff';
      g.fillRect(x, h - pad - bh, bw, bh);
      if (i % 4 === 0) { g.fillStyle = '#9aa5b5'; g.fillText(r.label, x - 4, h - 10); }
    });
  }

  function render(d) {
    set('s-success', d.summary.success);
    set('s-failed', d.summary.failed);
    set('s-locked_now', d.summary.locked_now);
    set('s-blocked_now', d.summary.blocked_now);
    set('s-otp', d.summary.otp_sent + ' / ' + d.summary.otp_failed);
    set('s-alerts', d.summary.alerts);
    drawChart(d.hourly);

    var tb = document.getElementById('top-ips'); clear(tb);
    if (!d.top_ips.length) { var tr0 = el('tr'); var td0 = el('td', 'No failures in the last 24 hours.'); td0.colSpan = 3; tr0.appendChild(td0); tb.appendChild(tr0); }
    d.top_ips.forEach(function (r) {
      var tr = el('tr'); tr.appendChild(el('td', r.ip_address)); tr.appendChild(el('td', r.failures));
      var td = el('td'); if (parseInt(r.blocked, 10) > 0) td.appendChild(el('span', 'BLOCKED', 'tag tag-red')); else td.textContent = '-';
      tr.appendChild(td); tb.appendChild(tr);
    });

    var fl = document.getElementById('flags'); clear(fl);
    if (!d.flags.length) fl.appendChild(el('li', 'No flags yet. Run: php scripts/monitor.php'));
    d.flags.forEach(function (f) {
      var li = el('li'); li.appendChild(el('strong', '[' + f.severity + '] ' + f.flag_type.replace(/_/g, ' ') + ' - ' + f.target_identifier));
      li.appendChild(el('div', f.description + ' (' + f.flagged_date + ')', 'muted')); fl.appendChild(li);
    });

    var ev = document.getElementById('events'); clear(ev);
    d.events.forEach(function (r) {
      var tr = el('tr');
      tr.appendChild(el('td', r.created_at)); tr.appendChild(el('td', r.event_type));
      tr.appendChild(el('td', r.username_typed)); tr.appendChild(el('td', r.ip_address)); tr.appendChild(el('td', r.user_agent));
      ev.appendChild(tr);
    });
    set('updated', 'Last updated ' + d.updated);
  }

  try { render(JSON.parse(root.dataset.initial)); } catch (e) { /* ignore */ }

  setInterval(function () {                              // auto-refresh every 60 s; endpoint re-checks the admin session
    fetch('api.php?action=dashboard&' + window.location.search.replace(/^\?/, ''), { credentials: 'same-origin' })
      .then(function (r) { if (r.status === 403) { window.location.href = 'login.php'; throw new Error('denied'); } return r.json(); })
      .then(render).catch(function () {});
  }, 60000);
})();
