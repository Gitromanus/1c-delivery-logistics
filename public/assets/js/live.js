/** live.js v10 — poll, theme, vehicles by date */
(function () {
  function loadScript(id, src) {
    if (document.getElementById(id)) return;
    var s = document.createElement('script');
    s.id = id;
    s.src = src;
    document.head.appendChild(s);
  }
  loadScript('desk-compact-v2', 'assets/js/desk-compact-v2.js?v=9');
  loadScript('map-markers', 'assets/js/map-markers.js?v=4');

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
    var toolbar = document.querySelector('.toolbar');
    if (toolbar) toolbar.appendChild(btn);
    else {
      btn.style.cssText = 'position:fixed;top:12px;right:12px;z-index:50';
      document.body.appendChild(btn);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ensureThemeButton);
  else ensureThemeButton();

  var dateInput = document.querySelector('input[name="date"]');
  var date = dateInput ? dateInput.value : new Date().toISOString().slice(0, 10);
  var lastVersion = null;
  var intervalMs = 8000;
  var timer = null;
  var quietUntil = 0;
  var wasDragging = false;
  var ensureDone = false;
  var nativeFetch = window.fetch.bind(window);

  function isDragging() {
    return document.body.classList.contains('dd-dragging');
  }

  /** Расставить чипы машин по зонам рейсов выбранной даты */
  function placeVehiclesByDate() {
    nativeFetch('api/desk_vehicles.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.vehicles) return;

        // Собрать существующие чипы
        var chips = {};
        document.querySelectorAll('.veh-chip[data-vehicle-id]').forEach(function (ch) {
          chips[ch.getAttribute('data-vehicle-id')] = ch;
        });

        // Очистить контейнеры
        document.querySelectorAll('.zone-vehicles').forEach(function (box) {
          box.innerHTML = '';
        });

        data.vehicles.forEach(function (v) {
          var zid = String(v.zone_id);
          var vid = String(v.vehicle_id);
          var box = document.querySelector('.zone-card[data-zone-drop="' + zid + '"] .zone-vehicles');
          if (!box) return;
          var ch = chips[vid];
          if (!ch) {
            ch = document.createElement('div');
            ch.className = 'veh-chip';
            ch.setAttribute('data-vehicle-id', vid);
            ch.setAttribute('data-cap', v.capacity_kg);
            ch.title = 'Перетащите в другую зону';
            ch.innerHTML =
              '<span class="veh-name"></span><span class="veh-cap"></span>';
            ch.querySelector('.veh-name').textContent = v.name;
            ch.querySelector('.veh-cap').textContent =
              Math.round(v.capacity_kg).toLocaleString('ru-RU') + ' кг';
          }
          ch.setAttribute('data-zone-id', zid);
          box.appendChild(ch);
        });

        document.querySelectorAll('.zone-vehicles').forEach(function (box) {
          if (!box.children.length) {
            var empty = document.createElement('span');
            empty.className = 'muted zone-empty';
            empty.textContent = 'нет машин';
            box.appendChild(empty);
          }
        });
      })
      .catch(function () {});
  }

  window.deskAckLocalChange = function () {
    quietUntil = Date.now() + 30000;
    nativeFetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.version) lastVersion = data.version;
      })
      .catch(function () {});
  };

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    if (url.indexOf('vehicle_zone.php') !== -1 && init && init.body && typeof init.body === 'string') {
      try {
        var body = JSON.parse(init.body);
        if (body && body.action === 'move') {
          body.date = date;
          init = Object.assign({}, init, { body: JSON.stringify(body) });
          return nativeFetch(input, init).then(function (res) {
            return res
              .clone()
              .json()
              .then(function (data) {
                if (data && data.ok) {
                  quietUntil = Date.now() + 15000;
                  setTimeout(function () {
                    location.reload();
                  }, 50);
                }
                return res;
              })
              .catch(function () {
                return res;
              });
          });
        }
      } catch (e) {}
    }
    return nativeFetch(input, init);
  };

  function ensureEmptyTrips() {
    if (ensureDone) return;
    ensureDone = true;
    nativeFetch('api/ensure_trips.php?date=' + encodeURIComponent(date), {
      method: 'POST',
      cache: 'no-store'
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (data && data.ok && data.created > 0) {
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
    nativeFetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) {
        return r.json();
      })
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
    placeVehiclesByDate();
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
