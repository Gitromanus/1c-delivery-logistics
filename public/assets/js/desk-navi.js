/**
 * Кнопка «Навигатор» у рейса: открывает Яндекс.Навигатор с точками доставки.
 * Бесплатно через URL-схему, без API построения маршрутов.
 *
 * Точки берутся из mapPoints (координаты с сервера) в порядке sort в рейсе.
 * Старт — текущее местоположение водителя (lat_from не указываем).
 */
(function () {
  function byId(id) {
    if (typeof mapPoints === 'undefined' || !mapPoints) return null;
    for (var i = 0; i < mapPoints.length; i++) {
      if (String(mapPoints[i].id) === String(id)) return mapPoints[i];
    }
    return null;
  }

  function pointsForTrip(tripEl) {
    var orders = tripEl.querySelectorAll('.orders-list > .drag-order');
    var pts = [];
    for (var i = 0; i < orders.length; i++) {
      var oid = orders[i].getAttribute('data-order-id');
      var p = byId(oid);
      if (p && p.lat != null && p.lon != null && p.lat !== '' && p.lon !== '') {
        pts.push({ lat: Number(p.lat), lon: Number(p.lon) });
      }
    }
    return pts;
  }

  /** Яндекс Навигатор: конечная + промежуточные (via). Без lat_from = «откуда я». */
  function naviUrl(pts) {
    if (!pts.length) return null;
    var last = pts[pts.length - 1];
    var q = 'lat_to=' + last.lat + '&lon_to=' + last.lon;
    for (var i = 0; i < pts.length - 1; i++) {
      q += '&lat_via_' + i + '=' + pts[i].lat + '&lon_via_' + i + '=' + pts[i].lon;
    }
    return 'yandexnavi://build_route_on_map?' + q;
  }

  /** Запасной вариант: Яндекс Карты / приложение Карт (rtext). Старт = текущее место (~) */
  function mapsUrl(pts) {
    if (!pts.length) return null;
    var parts = ['']; // пустой from → «мое местоположение»
    for (var i = 0; i < pts.length; i++) {
      parts.push(pts[i].lat + ',' + pts[i].lon);
    }
    return 'https://yandex.ru/maps/?rtext=' + parts.join('~') + '&rtt=auto';
  }

  function addButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      if (trip.querySelector('.btn-navi')) return;
      var pts = pointsForTrip(trip);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-sm btn-navi';
      btn.textContent = 'Навигатор';
      btn.title = pts.length
        ? ('Точек с координатами: ' + pts.length)
        : 'Нет координат у заявок — сначала геокодирование';
      if (!pts.length) {
        btn.disabled = true;
        btn.className = 'btn btn-ghost btn-sm btn-navi';
      }
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var points = pointsForTrip(trip);
        if (!points.length) {
          alert('У заявок в рейсе нет координат. Сначала нажмите «Геокод с карты».');
          return;
        }
        var navi = naviUrl(points);
        var maps = mapsUrl(points);
        // Сначала пробуем Навигатор; если схема не открылась — Карты в браузере/приложении
        var opened = false;
        try {
          window.location.href = navi;
          opened = true;
        } catch (err) {}
        setTimeout(function () {
          // Запасной путь через Карты (часто подхватывает и Навигатор / Go)
          if (maps) {
            window.open(maps, '_blank');
          }
        }, opened ? 800 : 0);
      });
      var title = trip.querySelector('.title');
      if (title) title.appendChild(btn);
      else trip.insertBefore(btn, trip.firstChild);
    });
  }

  function boot() {
    compactAll();
    addNaviButtons();
  }

  function compactAll() {
    document.querySelectorAll('.zone-card .meta').forEach(compactMeta);
    document.querySelectorAll('.zone-cap').forEach(compactCap);
    document.querySelectorAll('.trip-weight').forEach(compactTripWeight);
  }

  function addNaviButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      if (trip.querySelector('.btn-navi')) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-sm btn-navi';
      btn.textContent = 'Навигатор';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pts = [];
        if (typeof mapPoints === 'undefined') {
          alert('Нет данных карты (mapPoints).');
          return;
        }
        trip.querySelectorAll('.orders-list > .drag-order').forEach(function (ord) {
          var oid = ord.getAttribute('data-order-id');
          for (var i = 0; i < mapPoints.length; i++) {
            if (String(mapPoints[i].id) === String(oid)) {
              var p = mapPoints[i];
              if (p.lat != null && p.lon != null && p.lat !== '' && p.lon !== '') {
                pts.push({ lat: Number(p.lat), lon: Number(p.lon) });
              }
              break;
            }
          }
        });
        if (!pts.length) {
          alert('У точек рейса нет координат. Сначала «Геокод с карты».');
          return;
        }
        var last = pts[pts.length - 1];
        var q = 'lat_to=' + last.lat + '&lon_to=' + last.lon;
        for (var i = 0; i < pts.length - 1; i++) {
          q += '&lat_via_' + i + '=' + pts[i].lat + '&lon_via_' + i + '=' + pts[i].lon;
        }
        var navi = 'yandexnavi://build_route_on_map?' + q;
        var parts = [''];
        for (var j = 0; j < pts.length; j++) {
          parts.push(pts[j].lat + ',' + pts[j].lon);
        }
        var maps = 'https://yandex.ru/maps/?rtext=' + parts.join('~') + '&rtt=auto';
        window.location.href = navi;
        setTimeout(function () {
          window.open(maps, '_blank');
        }, 700);
      });
      var title = trip.querySelector('.title');
      if (title) title.appendChild(btn);
      else trip.appendChild(btn);
    });
  }

  function boot() {
    run();
    addNaviButtons();
  }

  function addNaviButtons() {
    document.querySelectorAll('.trip').forEach(function (trip) {
      if (trip.querySelector('.btn-navi')) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-sm btn-navi';
      btn.textContent = 'Навигатор';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pts = [];
        if (typeof mapPoints === 'undefined' || !mapPoints) {
          alert('Нет координат (mapPoints). Сначала геокодирование.');
          return;
        }
        trip.querySelectorAll('.orders-list > .drag-order').forEach(function (ord) {
          var oid = ord.getAttribute('data-order-id');
          for (var i = 0; i < mapPoints.length; i++) {
            if (String(mapPoints[i].id) === String(oid)) {
              var p = mapPoints[i];
              if (p.lat != null && p.lon != null && p.lat !== '' && p.lon !== '') {
                pts.push({ lat: Number(p.lat), lon: Number(p.lon) });
              }
              break;
            }
          }
        });
        if (!pts.length) {
          alert('У точек рейса нет координат. Сначала «Геокод с карты».');
          return;
        }
        var last = pts[pts.length - 1];
        var q = 'lat_to=' + last.lat + '&lon_to=' + last.lon;
        for (var i = 0; i < pts.length - 1; i++) {
          q += '&lat_via_' + i + '=' + pts[i].lat + '&lon_via_' + i + '=' + pts[i].lon;
        }
        var navi = 'yandexnavi://build_route_on_map?' + q;
        var parts = [''];
        for (var j = 0; j < pts.length; j++) {
          parts.push(pts[j].lat + ',' + pts[j].lon);
        }
        var maps = 'https://yandex.ru/maps/?rtext=' + parts.join('~') + '&rtt=auto';
        window.location.href = navi;
        setTimeout(function () {
          window.open(maps, '_blank');
        }, 700);
      });
      var title = trip.querySelector('.title');
      if (title) title.appendChild(btn);
      else trip.appendChild(btn);
    });
  }

  function boot() {
    run();
    // кнопки навигатора — отдельная фича уже в этом файле
  }

  // Переопределяем: без observer, чтобы не вешать вкладку
  function bootSafe() {
    run();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
