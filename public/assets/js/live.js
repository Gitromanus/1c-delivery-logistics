/**
 * Тема + пустые рейсы + автообновление.
 */
(function () {
  // —— тема (дублирует theme.js, если он уже загружен — кнопка одна) ——
  var THEME_KEY = 'logistics-theme';
  function preferredTheme() {
    try {
      var saved = localStorage.getItem(THEME_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch (e) {}
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
    return 'dark';
  }
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    var btn = document.getElementById('themeToggle');
    if (btn) {
      btn.textContent = theme === 'light' ? '🌙' : '☀️';
      btn.title = theme === 'light' ? 'Тёмная тема' : 'Светлая тема';
    }
  }
  applyTheme(preferredTheme());
  function ensureThemeButton() {
    if (document.getElementById('themeToggle')) return;
    var toolbar = document.querySelector('.toolbar');
    if (!toolbar) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'themeToggle';
    btn.className = 'btn btn-ghost btn-theme';
    var theme = document.documentElement.getAttribute('data-theme') || 'dark';
    btn.textContent = theme === 'light' ? '🌙' : '☀️';
    btn.title = theme === 'light' ? 'Тёмная тема' : 'Светлая тема';
    btn.addEventListener('click', function () {
      var cur = document.documentElement.getAttribute('data-theme') || 'dark';
      applyTheme(cur === 'light' ? 'dark' : 'light');
    });
    toolbar.appendChild(btn);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureThemeButton);
  } else {
    ensureThemeButton();
  }

  var dateInput = document.querySelector('input[name="date"]');
  var date = dateInput ? dateInput.value : new Date().toISOString().slice(0, 10);
  var lastVersion = null;
  var intervalMs = 8000;
  var timer = null;
  var quietUntil = 0;
  var wasDragging = false;
  var ensureDone = false;

  function isDragging() {
    return document.body.classList.contains('dd-dragging');
  }

  window.deskAckLocalChange = function () {
    quietUntil = Date.now() + 30000;
    fetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.version) lastVersion = data.version;
      })
      .catch(function () {});
  };

  /** Пустые рейсы для всех машин — ручная сборка без «Пересобрать» */
  function ensureEmptyTrips() {
    if (ensureDone) return;
    ensureDone = true;
    fetch('api/ensure_trips.php?date=' + encodeURIComponent(date), { method: 'POST', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.created > 0) {
          // появились новые пустые рейсы — обновить список
          quietUntil = Date.now() + 5000;
          location.reload();
        }
      })
      .catch(function () {});
  }

  function poll() {
    if (document.hidden) return;
    if (wasDragging && !isDragging()) {
      wasDragging = false;
      if (window.deskAckLocalChange) window.deskAckLocalChange();
    }
    if (isDragging()) {
      wasDragging = true;
      return;
    }
    fetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.version) return;
        if (lastVersion === null) {
          lastVersion = data.version;
          return;
        }
        if (data.version === lastVersion) return;
        if (Date.now() < quietUntil) {
          lastVersion = data.version;
          return;
        }
        lastVersion = data.version;
        document.title = '● Логистика — новые заявки';
        location.reload();
      })
      .catch(function () {});
  }

  function start() {
    ensureEmptyTrips();
    if (timer) clearInterval(timer);
    poll();
    timer = setInterval(poll, intervalMs);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });

  start();
})();
