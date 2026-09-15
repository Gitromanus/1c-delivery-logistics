/**
 * Компактные подписи (только mobile) + Навигатор.
 */
(function () {
  var IC_SCALE = '<span class="ic ic-scale" aria-hidden="true"></span>';
  var IC_TRUCK = '<span class="ic ic-truck" aria-hidden="true"></span>';
  var IC_BOX = '<span class="ic ic-box" aria-hidden="true"></span>';
  var NAVI_ICON = '<span class="navi-ico" aria-hidden="true">📍</span>';
  var busy = false;
  var timer = null;

  function isNarrow() {
    try { return window.matchMedia('(max-width: 900px)').matches; } catch (e) { return window.innerWidth <= 900; }
  }

  function compactZoneCard(card) {
    if (!card || card.getAttribute('data-zone-compact') === '1') return;
    var cap = card.querySelector('.zone-cap');
    if (!cap) return;
    var meta = card.querySelector('.meta');
    var t = (cap.textContent || '').replace(/\s+/g, ' ').trim();
    var load = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    var veh = t.match(/машин[аы]?\s*:?\s*(\d+)/i);
    var orders = null;
    if (meta) {
      var mt = (meta.textContent || '').replace(/\s+/g, ' ').trim();
      var om = mt.match(/(\d+)\s*заявок/);
      if (om) orders = om[1];
    }
    if (!load) return;
    var a = load[1].replace(/\s/g, '\u00a0').trim();
    var b = load[2].replace(/\s/g, '\u00a0').trim().replace(/\s*кг$/i, '');
    var html = '<span class="cap-load" title="Загрузка, кг">' + IC_SCALE + a + ' / ' + b + '</span>';
    if (veh) {
      html += '<span class="cap-veh" title="Машин">' + IC_TRUCK + veh[1] + '</span>';
    }
    if (orders != null) {
      html += '<span class="cap-orders meta-orders" title="Заявок">' + IC_BOX + orders + '</span>';
    }
    cap.innerHTML = html;
    card.setAttribute('data-zone-compact', '1');
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

  function layoutTripTitle(trip) {
    var title = trip.querySelector('.title');
    if (!title) return;
    var navi = title.querySelector('.btn-navi, .btn-icon-navi');
    var toggle = title.querySelector('.trip-toggle');
    if (navi && toggle && navi.nextSibling !== toggle) {
      title.appendChild(navi);
      title.appendChild(toggle);
    }
  }

  function addNaviButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      var title = trip.querySelector('.title');
      if (!title) return;
      if (!trip.querySelector('.btn-navi, .btn-icon-navi')) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary btn-navi btn-icon-navi';
        btn.innerHTML = NAVI_ICON;
        btn.title = 'Маршрут всех точек рейса в Яндекс Навигаторе';
        btn.setAttribute('aria-label', 'Навигатор');
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var pts = pointsForTrip(trip);
          if (!pts.length) {
            alert('У заявок в рейсе нет координат.\nСначала «Геокод с карты».');
            return;
          }
          window.location.href = naviUrl(pts);
          var maps = mapsUrl(pts);
          setTimeout(function () {
            if (maps) window.open(maps, '_blank');
          }, 700);
        });
        title.appendChild(btn);
      }
      layoutTripTitle(trip);
    });
  }

  function run() {
    if (busy) return;
    busy = true;
    try {
      if (!isNarrow()) {
        addNaviButtons();
        return;
      }
      document.querySelectorAll('.zone-card').forEach(function (card) {
        var cap = card.querySelector('.zone-cap');
        if (cap && !cap.querySelector('.cap-load') && card.getAttribute('data-zone-compact') === '1') {
          card.removeAttribute('data-zone-compact');
        }
        compactZoneCard(card);
      });
      document.querySelectorAll('.trip-weight').forEach(compactTripWeight);
      addNaviButtons();
    } finally {
      busy = false;
    }
  }

  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(run, 80);
  }

  function start() {
    run();
    document.addEventListener('mouseup', schedule);
    document.addEventListener('touchend', schedule);
    window.addEventListener('resize', schedule);
    setInterval(run, 4000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
