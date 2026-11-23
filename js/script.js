// Client-side helpers. The SERVER re-checks everything - this file is only for a friendlier form.
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // ---------- slide transition when opening Log-in or Register ----------
  var main = $('.container');
  var slideFlag = false;
  try { slideFlag = sessionStorage.getItem('slide') === '1'; sessionStorage.removeItem('slide'); } catch (e) { /* storage blocked: no animation */ }
  if (main && slideFlag) main.classList.add('slide-in');                          // new page slides in from the right
  window.addEventListener('pageshow', function () { if (main) main.classList.remove('slide-out'); });   // back button safety
  $$('a[href="login.php"], a[href="register.php"]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.shiftKey || a.target === '_blank' || !main) return;
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      ev.preventDefault();
      try { sessionStorage.setItem('slide', '1'); } catch (e) { /* ignore */ }
      main.classList.add('slide-out');                                            // current page slides out to the left
      setTimeout(function () { window.location.href = a.getAttribute('href'); }, 300);
    });
  });

  // ---------- scroll reveal + card spotlight (decorative only) ----------
  document.documentElement.classList.add('js-reveal');
  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: 0.15 }) : null;
  $$('[data-reveal]').forEach(function (el) { if (io) io.observe(el); else el.classList.add('in'); });
  $$('.spot').forEach(function (el) {
    el.addEventListener('mousemove', function (ev) {
      var r = el.getBoundingClientRect();
      el.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
      el.style.setProperty('--my', (ev.clientY - r.top) + 'px');
    });
  });

  function setError(name, msg) {
    var box = $('[data-error-for="' + name + '"]');
    if (box) box.textContent = msg || '';
  }

  // ---------- validators (same rules/messages as php/validation.php) ----------
  var V = {
    name: function (v, el) {
      var label = el.dataset.label, max = parseInt(el.dataset.max || '50', 10);
      v = v.trim();
      if (v === '') return el.dataset.req ? label + ' is required.' : '';
      if (/\d/.test(v)) return 'Numbers are not allowed in ' + label + '.';
      if (/[^A-Za-z ]/.test(v)) return 'Special characters are not allowed in ' + label + '.';
      if (v.indexOf('  ') !== -1) return 'Double space is not allowed.';
      if (v.length < 2) return label + ' must be at least 2 characters.';
      if (v.length > max) return label + ' must not be longer than ' + max + ' characters.';
      if (v === v.toUpperCase()) return 'Do not use all capital letters.';
      if (/(.)\1\1/i.test(v)) return 'Three consecutive identical letters are not allowed.';
      var w = v.split(' ');
      for (var i = 0; i < w.length; i++) {
        if (!/^[A-Z][a-z]*$/.test(w[i])) return /^[A-Z]/.test(w[i]) ? 'Only the first letter of each word may be capital.' : 'First letter of your name must be capital.';
      }
      return '';
    },
    id: function (v) { return v === '' ? 'ID Number is required.' : (/^\d{4}-\d{4}$/.test(v) ? '' : 'ID Number must follow the format xxxx-xxxx.'); },
    email: function (v) { return v === '' ? 'Email is required.' : (v.length <= 100 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Please enter a valid email address.'); },
    zip: function (v) { return v === '' ? 'ZIP Code is required.' : (/^\d{4}$/.test(v) ? '' : 'ZIP Code must be exactly 4 digits.'); },
    contact: function (v) { return v === '' ? 'Contact Number is required.' : (/^09\d{9}$/.test(v) ? '' : 'Contact Number must be 11 digits and start with 09.'); },
    allergies: function (v) { return (v === '' || (v.length <= 100 && /^[A-Za-z, ]+$/.test(v))) ? '' : 'Allergies may contain letters, commas and spaces only (maximum 100 characters).'; },
    purok: function (v) {
      if (v === '') return 'Purok / Street is required.';
      if (v.indexOf('  ') !== -1) return 'Double space is not allowed.';
      if (!/^[A-Za-z0-9 ]+$/.test(v)) return 'Purok / Street may contain letters, digits and spaces only.';
      return (v.length < 3 || v.length > 50) ? 'Purok / Street must be 3 to 50 characters.' : '';
    },
    place: function (v, el) {
      var l = el.dataset.label;
      if (v === '') return l + ' is required.';
      if (v.indexOf('  ') !== -1) return 'Double space is not allowed.';
      if (!/^[A-Za-z ]+$/.test(v)) return l + ' may contain letters and spaces only.';
      var w = v.split(' ');
      for (var i = 0; i < w.length; i++) if (!/^[A-Z][a-z]*$/.test(w[i])) return l + ' must be in Title Case (for example: Cagayan De Oro).';
      return '';
    },
    username: function (v, el) {
      var min = parseInt(el.dataset.min, 10), max = parseInt(el.dataset.max, 10);
      if (v === '') return 'Username is required.';
      if (!/^[A-Za-z][A-Za-z0-9]*$/.test(v)) return 'Username must start with a letter and contain letters and digits only.';
      if (v.length < min) return 'Username must be at least ' + min + ' characters.';
      if (v.length > max) return 'Username must be at most ' + max + ' characters.';
      return '';
    },
    answer: function (v) {
      if (v === '') return 'Answer is required.';
      if (v.indexOf('  ') !== -1) return 'Double space is not allowed.';
      if (!/^[A-Za-z ]+$/.test(v)) return 'Answer may contain letters and spaces only.';
      return (v.length < 2 || v.length > 50) ? 'Answer must be 2 to 50 characters.' : '';
    },
    details: function (v) { return (v === '' || (v.length <= 100 && /^[A-Za-z0-9,. ]+$/.test(v))) ? '' : 'Exposure details may contain letters, numbers, commas, periods and spaces only (maximum 100 characters).'; },
    select: function (v, el) { return v === '' ? el.dataset.msg : ''; },
    birthdate: function (v) {
      if (v === '') return 'Birthdate is required.';
      var d = new Date(v + 'T00:00:00'), now = new Date(); now.setHours(0, 0, 0, 0);
      if (isNaN(d.getTime())) return 'Please enter a valid birthdate.';
      if (d > now) return 'Birthdate cannot be in the future.';
      return calcAge(d, now) < 18 ? 'You must be at least 18 years old to register.' : '';
    }
  };
  function calcAge(d, now) {
    var a = now.getFullYear() - d.getFullYear(), m = now.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < d.getDate())) a--;
    return a;
  }
  function checkField(el) {
    var fn = V[el.dataset.v];
    if (!fn) return '';
    var msg = fn(el.value, el);
    setError(el.name, msg);
    return msg;
  }
  function checkPasswords() {                       // used by the Next button and the final submit
    var p = $('#password'), p2 = $('#password2'), bad = false;
    if (!p) return false;
    if (strength(p.value) === 'Weak') { setError('password', 'Password is too weak. Use at least 3 of: lowercase, uppercase, digits, symbols.'); bad = true; }
    if (p2 && p.value !== p2.value) { setError('password2', 'Passwords do not match.'); bad = true; }
    return bad;
  }
  $$('[data-v]').forEach(function (el) { el.addEventListener('blur', function () { checkField(el); }); });

  var regForm = $('#register-form');
  if (regForm) {
    regForm.addEventListener('submit', function (ev) {
      var bad = false;
      $$('[data-v]', regForm).forEach(function (el) { if (checkField(el)) bad = true; });
      if (checkPasswords()) bad = true;
      if (!$('[name="privacy"]').checked) { setError('privacy', 'You must agree to the Privacy Notice to register.'); bad = true; }
      if (bad) ev.preventDefault();
    });
    var bd = $('#birthdate'), age = $('#age');                       // read-only Age, calculated from Birthdate
    var showAge = function () {
      var d = new Date(bd.value + 'T00:00:00'), now = new Date(); now.setHours(0, 0, 0, 0);
      age.value = (bd.value && !isNaN(d.getTime()) && d <= now) ? calcAge(d, now) : '';
    };
    bd.addEventListener('input', showAge); showAge();
  }

  // ---------- step-by-step registration (without JavaScript every step is simply shown) ----------
  if (regForm && $$('.step', regForm).length) {
    var steps = $$('.step', regForm), dots = $$('#stepper li'), cur = 0;
    regForm.classList.add('wizard');
    var hasError = function (st) { return $$('.error', st).some(function (e) { return e.textContent.trim() !== ''; }); };
    var show = function (i, scroll) {
      cur = i;
      steps.forEach(function (st, n) { st.classList.toggle('active', n === i); });
      dots.forEach(function (d, n) { d.classList.toggle('current', n === i); d.classList.toggle('done', n < i); });
      if (scroll) $('#stepper').scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    var firstError = function () { for (var n = 0; n < steps.length; n++) if (hasError(steps[n])) return n; return -1; };
    var stepOk = function (st) {
      var bad = false;
      $$('[data-v]', st).forEach(function (el) { if (checkField(el)) bad = true; });
      if ($('#password') && st.contains($('#password')) && checkPasswords()) bad = true;
      return !bad;
    };
    $$('.btn-next', regForm).forEach(function (b) { b.addEventListener('click', function () { if (stepOk(steps[cur])) show(cur + 1, true); }); });
    $$('.btn-back', regForm).forEach(function (b) { b.addEventListener('click', function () { show(cur - 1, true); }); });
    regForm.addEventListener('keydown', function (ev) {      // Enter = Next, not submit, until the last step
      if (ev.key === 'Enter' && cur < steps.length - 1 && ev.target.tagName !== 'BUTTON') { ev.preventDefault(); $$('.btn-next', steps[cur])[0].click(); }
    });
    regForm.addEventListener('submit', function (ev) { if (ev.defaultPrevented) { var n = firstError(); if (n >= 0) show(n, true); } });
    var start = firstError();
    show(start < 0 ? 0 : start, false);
  }

  // ---------- security questions: the 3 pickers must be different, and show an example answer ----------
  var qSel = $$('[data-q-select]');
  if (qSel.length) {
    var refreshQ = function () {
      var chosen = qSel.map(function (s) { return s.value; });
      qSel.forEach(function (s, i) {
        $$('option', s).forEach(function (o) { o.disabled = o.value !== '' && o.value !== s.value && chosen.indexOf(o.value) !== -1; });
        var box = $('#answer_' + (i + 1)), opt = s.options[s.selectedIndex];
        if (box) box.placeholder = (opt && opt.dataset.hint) ? opt.dataset.hint : 'Your answer';
      });
    };
    qSel.forEach(function (s) { s.addEventListener('change', refreshQ); });
    refreshQ();
  }

  // ---------- name extension "Other" ----------
  var extSel = $('[data-ext-select]'), extWrap = $('#ext-other-wrap');
  if (extSel && extWrap) {
    var toggleExt = function () { extWrap.classList.toggle('hidden', extSel.value !== 'Other'); };
    extSel.addEventListener('change', toggleExt); toggleExt();
  }

  // ---------- show password ----------
  $$('.show-pw').forEach(function (cb) {
    cb.addEventListener('change', function () { $('#' + cb.dataset.target).type = cb.checked ? 'text' : 'password'; });
  });

  // ---------- strength meter (rules in the Design Decisions doc) ----------
  function strength(p) {
    var m = $('[data-strength]');
    var minLen = m ? parseInt(m.dataset.min || '8', 10) : 8;
    var c = (/[a-z]/.test(p) ? 1 : 0) + (/[A-Z]/.test(p) ? 1 : 0) + (/\d/.test(p) ? 1 : 0) + (/[^A-Za-z0-9]/.test(p) ? 1 : 0);
    if (p.length < minLen || c < 3) return 'Weak';
    if (c === 4 && p.length >= minLen + 4) return 'Strong';
    return 'Medium';
  }
  $$('[data-strength]').forEach(function (el) {
    var bar = $('#meter-bar-' + el.name), txt = $('#meter-text-' + el.name);
    el.addEventListener('input', function () {
      if (el.value === '') { bar.className = 'meter-bar'; txt.textContent = ''; return; }
      var s = strength(el.value);
      bar.className = 'meter-bar ' + s.toLowerCase();
      txt.textContent = 'Strength: ' + s;
    });
  });

  // ---------- live username availability ----------
  var un = $('#username'), status = $('#username-status');
  if (un && status && regForm) {
    var timer;
    un.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (un.value === '') { status.textContent = ''; return; }
        fetch('api.php?action=check_username&u=' + encodeURIComponent(un.value), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { status.textContent = j.message; status.className = 'hint ' + (j.available ? 'ok' : 'bad'); })
          .catch(function () { status.textContent = ''; });
      }, 350);
    });
  }

  // ---------- CAPTCHA refresh ----------
  var rf = $('#captcha-refresh'), img = $('#captcha-img');
  if (rf && img) rf.addEventListener('click', function () { img.src = 'captcha.php?t=' + Date.now(); var c = $('#captcha'); if (c) { c.value = ''; c.focus(); } });

  // ---------- visible countdowns (the SERVER enforces the real lockout / cooldown) ----------
  $$('[data-countdown]').forEach(function (el) {
    var left = parseInt(el.dataset.countdown, 10), prefix = el.dataset.prefix || '', btn = $('#resend-btn');
    var draw = function () { el.textContent = prefix + left + (prefix ? ' s' : ''); };
    draw();
    var iv = setInterval(function () {
      left--;
      if (left <= 0) {
        clearInterval(iv);
        if (el.dataset.reload) { window.location.href = window.location.pathname; return; }
        el.textContent = '';
        if (btn) btn.disabled = false;
        return;
      }
      draw();
    }, 1000);
  });

  // ---------- idle timeout: warn 1 minute before, then log out ----------
  var idle = parseInt(document.body.dataset.idle || '0', 10);
  if (idle > 0) {
    var started = Date.now(), warned = false;
    ['click', 'keydown', 'mousemove'].forEach(function (evn) { document.addEventListener(evn, function () { /* server tracks real idle on each request */ }); });
    setInterval(function () {
      var left = idle - Math.floor((Date.now() - started) / 1000);
      if (left <= 60 && !warned) {
        warned = true;
        var b = document.createElement('div');
        b.className = 'alert alert-error idle-warning';
        b.textContent = 'You will be logged out in 1 minute because of inactivity. Click or reload to stay signed in.';
        b.addEventListener('click', function () { window.location.reload(); });
        document.body.insertBefore(b, document.body.firstChild);
      }
      if (left <= 0) window.location.href = 'logout.php';
    }, 1000);
  }
})();
