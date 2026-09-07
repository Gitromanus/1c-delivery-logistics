/**
 * Компактные подписи + Навигатор + порядок кнопок рейса.
 * Зона: в одном ряду загрузка · машины · заявки
 */
(function () {
  var IC_SCALE = '<span class="ic ic-scale" aria-hidden="true"></span>';
  var IC_TRUCK = '<span class="ic ic-truck" aria-hidden="true"></span>';
  var IC_BOX = '<span class="ic ic-box" aria-hidden="true"></span>';
  var NAVI_ICON =
    '<svg class="navi-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">' +
    '<path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>' +
    '</svg>';
  var busy = false;
  var timer = null;

  function compactZoneCard(card) {
    if (!card || card.getAttribute('data-zone-compact') === '1') return;

    var meta = card.querySelector('.meta');
    var cap = card.querySelector('.zone-cap');
    if (!cap) return;

    var orders = null;
    if (meta) {
      var tm = (meta.textContent || '').trim();
      var mo = tm.match(/(\d+)\s*заявок?/i) || tm.match(/^(\d+)$/);
      if (mo) orders = mo[1];
      // убрать отдельную строку «N заявок»
      meta.style.display = 'none';
    }
    // data-order-w на карточке — вес, не количество; число заявок только из meta

    var t = (cap.textContent || '').replace(/\s+/g, ' ').trim();
    var load = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    var veh = t.match(/машин[аы]?\s*:\s*(\d+)/i);
    // уже компактный HTML?
    if (cap.querySelector('.cap-load')) {
      if (orders != null && !cap.querySelector('.cap-orders')) {
        var span = document.createElement('span');
        span.className = 'cap-orders meta-orders';
        span.title = 'Заявок';
        span.innerHTML = IC_BOX + orders;
        cap.appendChild(span);
      }
      card.setAttribute('data-zone-compact', '1');
      return;
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

    var nameEl = title.querySelector('.trip-name');
    if (!nameEl) {
      var kids = Array.prototype.slice.call(title.childNodes);
      for (var i = 0; i < kids.length; i++) {
        var n = kids[i];
        if (n.nodeType === 1 && n.tagName === 'SPAN' && !n.classList.contains('trip-actions')) {
          n.classList.add('trip-name');
          nameEl = n;
          break;
        }
        if (n.nodeType === 3 && n.textContent.trim()) {
          var sp = document.createElement('span');
          sp.className = 'trip-name';
          sp.textContent = n.textContent;
          title.replaceChild(sp, n);
          nameEl = sp;
          break;
        }
      }
    }

    var actions = title.querySelector('.trip-actions');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'trip-actions';
      title.appendChild(actions);
    }

    var toggle = title.querySelector('.trip-toggle') || actions.querySelector('.trip-toggle');
    var navi = title.querySelector('.btn-navi') || actions.querySelector('.btn-navi');
    if (navi && navi.parentNode !== actions) actions.appendChild(navi);
    if (toggle && toggle.parentNode !== actions) actions.appendChild(toggle);
    if (navi) actions.appendChild(navi);
    if (toggle) actions.appendChild(toggle);
  }

  function addNaviButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      var title = trip.querySelector('.title');
      if (!title) return;

      if (!trip.querySelector('.btn-navi')) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary btn-navi btn-icon-navi';
        btn.innerHTML = NAVI_ICON;
        btn.title = 'Открыть в Яндекс Навигаторе';
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
      } else {
        var existing = trip.querySelector('.btn-navi');
        if (existing && !existing.querySelector('.navi-ico')) {
          existing.classList.add('btn-icon-navi');
          existing.innerHTML = NAVI_ICON;
          existing.title = 'Открыть в Яндекс Навигаторе';
          existing.setAttribute('aria-label', 'Навигатор');
        }
      }
      layoutTripTitle(trip);
    });
  }

  function run() {
    if (busy) return;
    busy = true;
    try {
      document.querySelectorAll('.zone-card').forEach(function (card) {
        // после DnD refreshZone сбрасывает HTML — снимаем флаг
        var cap = card.querySelector('.zone-cap');
        if (cap && !cap.querySelector('.cap-load') && card.getAttribute('data-zone-compact') === '1') {
          card.removeAttribute('data-zone-compact');
          var meta = card.querySelector('.meta');
          if (meta) meta.style.display = '';
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
    setInterval(run, 3000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
