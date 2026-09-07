/**
 * Компактные подписи + кнопка «Навигатор» у рейса.
 * Маршрут — бесплатный deeplink, без Router API.
 */
(function () {
  var IC_SCALE = '<span class="ic ic-scale" aria-hidden="true"></span>';
  var IC_TRUCK = '<span class="ic ic-truck" aria-hidden="true"></span>';
  var IC_BOX = '<span class="ic ic-box" aria-hidden="true"></span>';
  var busy = false;
  var timer = null;

  function compactMeta(el) {
    if (!el || el.getAttribute('data-compact') === '1') return;
    var t = (el.textContent || '').trim();
    var m = t.match(/(\d+)\s*заявок?/i) || t.match(/^(\d+)$/);
    if (!m) return;
    el.classList.add('meta-orders');
    el.title = 'Заявок';
    el.innerHTML = IC_BOX + m[1];
    el.setAttribute('data-compact', '1');
  }

  function compactCap(el) {
    if (!el || el.getAttribute('data-compact') === '1') return;
    if (el.querySelector('.cap-load')) {
      el.setAttribute('data-compact', '1');
      return;
    }
    var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
    var load = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    var veh = t.match(/машин[аы]?\s*:\s*(\d+)/i);
    if (!load) return;
    var a = load[1].replace(/\s/g, '\u00a0').trim();
    var b = load[2].replace(/\s/g, '\u00a0').trim().replace(/\s*кг$/i, '');
    var html = '<span class="cap-load" title="Загрузка, кг">' + IC_SCALE + a + ' / ' + b + '</span>';
    if (veh) html += '<span class="cap-veh" title="Машин">' + IC_TRUCK + veh[1] + '</span>';
    el.innerHTML = html;
    el.setAttribute('data-compact', '1');
  }

  function compactTripWeight(el) {
    if (!el) return;
    var trip = el.closest('.trip');
    var n = trip ? trip.querySelectorAll('.orders-list > .drag-order').length : 0;
    if (el.getAttribute('data-compact') === '1' && el.getAttribute('data-compact-n') === String(n)) return;
    var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
    var m = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    if (!m) return;
    var a = m[1].trim();
    var b = m[2].replace(/\s*кг$/i, '').trim();
    el.title = 'Загрузка, кг';
    el.innerHTML =
      '<span class="trip-load">' + IC_SCALE + a + ' / ' + b + '</span>' +
      '<span class="trip-orders meta-orders" title="Заявок">' + IC_BOX + n + '</span>';
    el.setAttribute('data-compact', '1');
    el.setAttribute('data-compact-n', String(n));
  }

  function run() {
    if (busy) return;
    busy = true;
    try {
      document.querySelectorAll('.zone-card .meta').forEach(compactMeta);
      document.querySelectorAll('.zone-cap').forEach(compactCap);
      document.querySelectorAll('.trip-weight').forEach(compactTripWeight);
      addNaviButtons();
    } finally {
      busy = false;
    }
  }

  function pointsForTrip(tripEl) {
    var pts = [];
    if (typeof mapPoints === 'undefined' || !mapPoints) return pts;
    tripEl.querySelectorAll('.orders-list > .drag-order').forEach(function (ord) {
      var oid = ord.getAttribute('data-order-id');
      for (var i = 0; i < mapPoints.length; i++) {
        if (String(mapPoints[i].id) === String(oid)) {
          var p = mapPoints[i];
          if (p.lat != null && p.lon != null && p.lat !== '' && p.lon !== '') {
            var lat = Number(p.lat);
            var lon = Number(p.lon);
            if (!isNaN(lat) && !isNaN(lon)) pts.push({ lat: lat, lon: lon });
          }
          break;
        }
      }
    });
    return pts;
  }

  function naviUrl(pts) {
    if (!pts.length) return null;
    var last = pts[pts.length - 1];
    var q = 'lat_to=' + last.lat + '&lon_to=' + last.lon;
    for (var i = 0; i < pts.length - 1; i++) {
      q += '&lat_via_' + i + '=' + pts[i].lat + '&lon_via_' + i + '=' + pts[i].lon;
    }
    return 'yandexnavi://build_route_on_map?' + q;
  }

  function mapsUrl(pts) {
    if (!pts.length) return null;
    var parts = [''];
    for (var i = 0; i < pts.length; i++) {
      parts.push(pts[i].lat + ',' + pts[i].lon);
    }
    return 'https://yandex.ru/maps/?rtext=' + parts.join('~') + '&rtt=auto';
  }

  function addNaviButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      if (trip.querySelector('.btn-navi')) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-sm btn-navi';
      btn.textContent = 'Навигатор';
      btn.title = 'Открыть точки рейса в Яндекс Навигаторе';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pts = pointsForTrip(trip);
        if (!pts.length) {
          alert('У заявок в рейсе нет координат.\nСначала нажмите «Геокод с карты» на рабочем столе.');
          return;
        }
        var navi = naviUrl(pts);
        var maps = mapsUrl(pts);
        // На телефоне откроет Навигатор; если нет — через ~0.7с Карты
        window.location.href = navi;
        setTimeout(function () {
          if (maps) window.open(maps, '_blank');
        }, 700);
      });
      var title = trip.querySelector('.title');
      if (title) title.appendChild(btn);
      else trip.appendChild(btn);
    });
  }

  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(run, 80);
  }

  function start() {
    run();
    document.addEventListener('mouseup', schedule);
    document.addEventListener('touchend', schedule);
    setInterval(run, 2000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
