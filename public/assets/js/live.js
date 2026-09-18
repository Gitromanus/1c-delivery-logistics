/** live.js v13 — restore zone stats; fmtKg without spaces */
(function () {
  function loadScript(id, src) {
    if (document.getElementById(id)) return;
    var s = document.createElement('script');
    s.id = id;
    s.src = src;
    document.head.appendChild(s);
  }
  loadScript('desk-compact-v2', 'assets/js/desk-compact-v2.js?v=11');
  loadScript('map-markers', 'assets/js/map-markers.js?v=5');

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
      btn.textContent = theme === 'light' ? '\u{1F319}' : '\u{2600}\u{FE0F}';
      btn.title = theme === 'light' ? '\u0422\u0451\u043C\u043D\u0430\u044F \u0442\u0435\u043C\u0430' : '\u0421\u0432\u0435\u0442\u043B\u0430\u044F \u0442\u0435\u043C\u0430';
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
    btn.textContent = theme === 'light' ? '\u{1F319}' : '\u{2600}\u{FE0F}';
    btn.title = theme === 'light' ? '\u0422\u0451\u043C\u043D\u0430\u044F \u0442\u0435\u043C\u0430' : '\u0421\u0432\u0435\u0442\u043B\u0430\u044F \u0442\u0435\u043C\u0430';
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

  function fmtKg(n) {
    return String(Math.round(Number(n) || 0));
  }

  function updateZoneStats() {
    nativeFetch('api/desk_zone_stats.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.stats) return;
        var map = {};
        data.stats.forEach(function (s) { map[String(s.zone_id)] = s; });

        document.querySelectorAll('.zone-card[data-zone-drop]').forEach(function (card) {
          var zid = card.getAttribute('data-zone-drop');
          var s = map[zid] || { cnt: 0, weight: 0 };
          var w = Number(s.weight) || 0;
          var cnt = Number(s.cnt) || 0;
          card.setAttribute('data-order-w', String(w));

          var totCap = 0;
          card.querySelectorAll('.veh-chip[data-cap]').forEach(function (ch) {
            totCap += parseFloat(ch.getAttribute('data-cap')) || 0;
          });
          var nVeh = card.querySelectorAll('.veh-chip[data-vehicle-id]').length;
          var noVeh = nVeh === 0 && w > 0.01;

          var bar = card.querySelector('.bar');
          if (bar) {
            bar.classList.remove('over', 'no-vehicle');
            var pct = 0;
            if (noVeh) {
              pct = 100;
              bar.classList.add('no-vehicle');
            } else if (totCap > 0) {
              pct = Math.min(100, Math.round((w / totCap) * 100));
              if (w > totCap + 0.01) bar.classList.add('over');
            }
            var i = bar.querySelector('i');
            if (i) i.style.width = pct + '%';
          }

          var cap = card.querySelector('.zone-cap');
          var meta = card.querySelector('.meta');
          if (cap && cap.querySelector('.cap-load')) {
            var loadEl = cap.querySelector('.cap-load');
            if (loadEl) {
              loadEl.innerHTML = '<span class="ic ic-scale" aria-hidden="true"></span>' +
                fmtKg(w) + ' / ' + fmtKg(totCap);
            }
            var vehEl = cap.querySelector('.cap-veh');
            if (vehEl) {
              vehEl.innerHTML = '<span class="ic ic-truck" aria-hidden="true"></span>' + nVeh;
            } else if (nVeh >= 0) {
              var s = document.createElement('span');
              s.className = 'cap-veh';
              s.title = '\u041C\u0430\u0448\u0438\u043D';
              s.innerHTML = '<span class="ic ic-truck" aria-hidden="true"></span>' + nVeh;
              cap.appendChild(s);
            }
            var ordEl = cap.querySelector('.cap-orders');
            if (ordEl) {
              ordEl.innerHTML = '<span class="ic ic-box" aria-hidden="true"></span>' + cnt;
            } else {
              var o = document.createElement('span');
              o.className = 'cap-orders meta-orders';
              o.title = '\u0417\u0430\u044F\u0432\u043E\u043A';
              o.innerHTML = '<span class="ic ic-box" aria-hidden="true"></span>' + cnt;
              cap.appendChild(o);
            }
            if (meta) meta.style.display = 'none';
          } else {
            if (meta) {
              meta.textContent = cnt + ' \u0437\u0430\u044F\u0432\u043E\u043A \u00B7 ' + fmtKg(w) + ' \u043A\u0433';
              meta.style.display = '';
              meta.removeAttribute('data-compact');
            }
            if (cap) {
              card.removeAttribute('data-zone-compact');
              cap.removeAttribute('data-compact');
              cap.textContent =
                '\u0417\u0430\u0433\u0440\u0443\u0436\u0435\u043D\u043E: ' + fmtKg(w) + ' / ' + fmtKg(totCap) + ' \u043A\u0433 \u00B7 \u043C\u0430\u0448\u0438\u043D: ' + nVeh;
            }
          }

          var badge = card.querySelector('.badge-corner');
          if (badge) {
            if (cnt === 0) {
              // Заявок нет, но район закреплён за рейсом (в т.ч. объединённым)
              var covered = card.querySelectorAll('.veh-chip[data-vehicle-id]').length > 0;
              badge.textContent = covered ? '\u0412\u0435\u0437\u0451\u043C' : '\u041F\u0443\u0441\u0442\u043E';
              badge.className = 'badge badge-ok badge-corner';
            } else if (noVeh) {
              badge.textContent = '\u041D\u0435\u0442 \u043C\u0430\u0448\u0438\u043D';
              badge.className = 'badge badge-warn badge-corner';
            } else if (totCap > 0 && w > totCap + 0.01) {
              badge.textContent = '\u041F\u0435\u0440\u0435\u0433\u0440\u0443\u0437';
              badge.className = 'badge badge-warn badge-corner';
            } else {
              badge.textContent = '\u0412 \u0440\u0430\u0431\u043E\u0442\u0435';
              badge.className = 'badge badge-ok badge-corner';
            }
          }
        });
      })
      .catch(function () {});
  }

  function placeVehiclesByDate() {
    nativeFetch('api/desk_vehicles.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.vehicles) return;
        var chips = {};
        document.querySelectorAll('.veh-chip[data-vehicle-id]').forEach(function (ch) {
          chips[ch.getAttribute('data-vehicle-id')] = ch;
        });
        document.querySelectorAll('.zone-vehicles').forEach(function (box) {
          box.innerHTML = '';
        });
        data.vehicles.forEach(function (v) {
          var zid = String(v.zone_id);
          var vid = String(v.vehicle_id);
          var box = document.querySelector('.zone-card[data-zone-drop="' + zid + '"] .zone-vehicles');
          if (!box) return;
          var ch = chips[vid];
          if (ch) {
            // Машина может везти несколько зон (объединённый рейс) —
            // клонируем чип вместо перемещения одного и того же элемента.
            ch = ch.cloneNode(true);
          } else {
            ch = document.createElement('div');
            ch.className = 'veh-chip';
            ch.setAttribute('data-vehicle-id', vid);
            ch.setAttribute('data-cap', v.capacity_kg);
            ch.title = '\u041F\u0435\u0440\u0435\u0442\u0430\u0449\u0438\u0442\u0435 \u0432 \u0434\u0440\u0443\u0433\u0443\u044E \u0437\u043E\u043D\u0443';
            ch.innerHTML = '<span class="veh-name"></span><span class="veh-cap"></span>';
            ch.querySelector('.veh-name').textContent = v.name;
            ch.querySelector('.veh-cap').textContent =
              Math.round(v.capacity_kg) + ' \u043A\u0433';
          }
          ch.setAttribute('data-zone-id', zid);
          box.appendChild(ch);
        });
        document.querySelectorAll('.zone-vehicles').forEach(function (box) {
          if (!box.children.length) {
            var empty = document.createElement('span');
            empty.className = 'muted zone-empty';
            empty.textContent = '\u043D\u0435\u0442 \u043C\u0430\u0448\u0438\u043D';
            box.appendChild(empty);
          }
        });
        updateZoneStats();
      })
      .catch(function () { updateZoneStats(); });
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
            return res.clone().json().then(function (data) {
              if (data && data.ok) {
                quietUntil = Date.now() + 15000;
                setTimeout(function () { location.reload(); }, 50);
              }
              return res;
            }).catch(function () { return res; });
          });
        }
      } catch (e) {}
    }
    return nativeFetch(input, init);
  };

  function ensureEmptyTrips() {
    if (ensureDone) return;
    ensureDone = true;
    nativeFetch('api/ensure_trips.php?date=' + encodeURIComponent(date), { method: 'POST', cache: 'no-store' })
      .then(function (r) { return r.json(); })
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
    if (isDragging()) { wasDragging = true; return; }
    nativeFetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.version) return;
        if (lastVersion === null) { lastVersion = data.version; return; }
        if (data.version === lastVersion) return;
        if (Date.now() < quietUntil) { lastVersion = data.version; return; }
        // Прилетела новая заявка (вырос max_id) — после перезагрузки
        // наводим карту на её точку.
        var pv = String(lastVersion).split('-');
        var nv = String(data.version).split('-');
        if (data.last_order_id > 0 && pv.length > 1 && nv.length > 1 && parseInt(nv[1], 10) > parseInt(pv[1], 10)) {
          try { sessionStorage.setItem('logistics_flash_order', String(data.last_order_id)); } catch (e) {}
        }
        lastVersion = data.version;
        document.title = '\u25CF \u041B\u043E\u0433\u0438\u0441\u0442\u0438\u043A\u0430 \u2014 \u043D\u043E\u0432\u044B\u0435 \u0437\u0430\u044F\u0432\u043A\u0438';
        location.reload();
      })
      .catch(function () {});
  }

  // После перезагрузки страницы навести карту на новую заявку.
  function flashNewOrder() {
    var id = 0;
    try { id = parseInt(sessionStorage.getItem('logistics_flash_order'), 10) || 0; } catch (e) {}
    if (!id) return;
    try { sessionStorage.removeItem('logistics_flash_order'); } catch (e) {}
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      var mark = window.orderMarks && (window.orderMarks[id] || window.orderMarks[String(id)]);
      if (mark || tries > 75) {
        clearInterval(t);
        // Только наведение карты: страницу не прокручиваем к карточке.
        if (typeof window.highlightOrder === 'function') window.highlightOrder(id, { scroll: false });
      }
    }, 200);
  }

  function start() {
    placeVehiclesByDate();
    ensureEmptyTrips();
    flashNewOrder();
    if (timer) clearInterval(timer);
    poll();
    timer = setInterval(poll, intervalMs);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });

  start();
})();
