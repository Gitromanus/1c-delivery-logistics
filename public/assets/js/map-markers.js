/**
 * Метки на карте: номер в рейсе + выбор стиля.
 * Стили: circle | pin | stretchy | dot
 * localStorage: logistics-marker-style
 */
(function () {
  var STYLE_KEY = 'logistics-marker-style';
  var STYLES = {
    circle: {
      name: 'Круг с номером',
      assigned: 'islands#blueCircleIcon',
      neu: 'islands#orangeCircleIcon',
      done: 'islands#greenCircleIcon'
    },
    pin: {
      name: 'Булочка с номером',
      assigned: 'islands#blueIcon',
      neu: 'islands#orangeIcon',
      done: 'islands#greenIcon'
    },
    stretchy: {
      name: 'Широкая с номером',
      assigned: 'islands#blueStretchyIcon',
      neu: 'islands#orangeStretchyIcon',
      done: 'islands#greenStretchyIcon'
    },
    dot: {
      name: 'Точка (как раньше)',
      assigned: 'islands#blueDotIcon',
      neu: 'islands#orangeDotIcon',
      done: 'islands#greenDotIcon'
    }
  };

  function getStyleKey() {
    try {
      var s = localStorage.getItem(STYLE_KEY);
      if (s && STYLES[s]) return s;
    } catch (e) {}
    return 'circle';
  }

  function setStyleKey(k) {
    try { localStorage.setItem(STYLE_KEY, k); } catch (e) {}
  }

  /** order_id → номер в рейсе (1..n) */
  function buildSeqMap() {
    var seq = {};
    document.querySelectorAll('.trip').forEach(function (trip) {
      var n = 0;
      trip.querySelectorAll('.orders-list > .drag-order').forEach(function (ord) {
        n++;
        var id = ord.getAttribute('data-order-id');
        if (id) seq[String(id)] = n;
      });
    });
    return seq;
  }

  function presetFor(p, seq, style) {
    var st = STYLES[style] || STYLES.circle;
    if (p.status === 'new') return st.neu;
    if (p.status === 'done') return st.done;
    return st.assigned;
  }

  function rebuildMarks() {
    var map = window.__logisticsMap;
    if (!map || typeof ymaps === 'undefined') return;
    if (typeof mapPoints === 'undefined' || !mapPoints) return;

    // снять старые метки заказов (не полигоны зон)
    if (window.__orderCollection) {
      map.geoObjects.remove(window.__orderCollection);
    }
    if (typeof orderMarks === 'object') {
      for (var k in orderMarks) {
        if (Object.prototype.hasOwnProperty.call(orderMarks, k)) delete orderMarks[k];
      }
    } else {
      window.orderMarks = {};
    }

    var seq = buildSeqMap();
    var style = getStyleKey();
    var withCoords = mapPoints.filter(function (p) {
      return p.lat && p.lon;
    });
    if (!withCoords.length) return;

    var collection = new ymaps.GeoObjectCollection();
    withCoords.forEach(function (p) {
      var num = seq[String(p.id)];
      var title = (p.number || p.external_id || '') + ' · ' + (p.weight_kg || 0) + ' кг';
      if (num) title = '#' + num + ' · ' + title;
      var preset = presetFor(p, num, style);
      var props = {
        balloonContent:
          '<strong>' +
          title +
          '</strong><br>' +
          (p.partner || '') +
          '<br>' +
          (p.address || ''),
        iconCaption: num ? '' : p.number || p.external_id || ''
      };
      var opts = { preset: preset };
      // номер внутри иконки (circle / pin / stretchy)
      if (style !== 'dot' && num) {
        props.iconContent = String(num);
      } else if (style !== 'dot' && !num) {
        props.iconContent = '·';
      }
      var mark = new ymaps.Placemark(
        [parseFloat(p.lat), parseFloat(p.lon)],
        props,
        opts
      );
      mark.__origPreset = preset;
      mark.events.add('click', function () {
        if (typeof highlightOrder === 'function') highlightOrder(p.id);
      });
      window.orderMarks[p.id] = mark;
      collection.add(mark);
    });
    map.geoObjects.add(collection);
    window.__orderCollection = collection;
  }

  function ensureStyleSwitcher() {
    if (document.getElementById('markerStyle')) return;
    var toolbar = document.querySelector('.toolbar');
    if (!toolbar) return;
    var sel = document.createElement('select');
    sel.id = 'markerStyle';
    sel.title = 'Стиль меток на карте';
    sel.style.cssText = 'max-width:160px';
    var cur = getStyleKey();
    Object.keys(STYLES).forEach(function (k) {
      var opt = document.createElement('option');
      opt.value = k;
      opt.textContent = STYLES[k].name;
      if (k === cur) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.addEventListener('change', function () {
      setStyleKey(sel.value);
      rebuildMarks();
    });
    toolbar.appendChild(sel);
  }

  function boot() {
    ensureStyleSwitcher();
    // дождаться карты
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      if (window.__logisticsMap || tries > 40) {
        clearInterval(t);
        if (window.__logisticsMap) rebuildMarks();
      }
    }, 250);

    document.addEventListener('mouseup', function () {
      setTimeout(rebuildMarks, 100);
    });
    document.addEventListener('touchend', function () {
      setTimeout(rebuildMarks, 100);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.rebuildMapMarks = rebuildMarks;
})();
