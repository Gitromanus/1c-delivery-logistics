/**
 * Метки v3: номер в рейсе, смена стиля.
 * - снимает и точки, и GeoObjectCollection
 * - не перерисовывает карту на обычный клик (без мигания)
 * - подменяет addMarks, чтобы не было «старой» + «новой» метки
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
      name: 'Метка с номером',
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
      name: 'Точка',
      assigned: 'islands#blueDotIcon',
      neu: 'islands#orangeDotIcon',
      done: 'islands#greenDotIcon'
    }
  };

  var dragging = false;

  function getStyleKey() {
    try {
      var s = localStorage.getItem(STYLE_KEY);
      if (s && STYLES[s]) return s;
    } catch (e) {}
    return 'circle';
  }

  function setStyleKey(k) {
    try {
      localStorage.setItem(STYLE_KEY, k);
    } catch (e) {}
  }

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

  function presetFor(p, style) {
    var st = STYLES[style] || STYLES.circle;
    if (p.status === 'new') return st.neu;
    if (p.status === 'done') return st.done;
    return st.assigned;
  }

  /** Удалить всё, кроме полигонов зон */
  function clearOrderLayers(map) {
    var removeList = [];
    map.geoObjects.each(function (obj) {
      try {
        var t = obj.geometry && obj.geometry.getType && obj.geometry.getType();
        if (t === 'Polygon' || t === 'LineString') return;
        removeList.push(obj);
      } catch (e) {
        removeList.push(obj);
      }
    });
    removeList.forEach(function (obj) {
      try {
        map.geoObjects.remove(obj);
      } catch (e) {}
    });
    window.__orderCollection = null;
  }

  function marksDict() {
    try {
      if (typeof orderMarks !== 'undefined' && orderMarks) {
        Object.keys(orderMarks).forEach(function (k) {
          delete orderMarks[k];
        });
        return orderMarks;
      }
    } catch (e) {}
    window.orderMarks = {};
    return window.orderMarks;
  }

  function rebuildMarks() {
    var map = window.__logisticsMap;
    if (!map || typeof ymaps === 'undefined') return false;
    if (typeof mapPoints === 'undefined' || !mapPoints) return false;

    clearOrderLayers(map);
    var marks = marksDict();

    var seq = buildSeqMap();
    var style = getStyleKey();
    var withCoords = mapPoints.filter(function (p) {
      return p.lat && p.lon;
    });
    if (!withCoords.length) return true;

    var collection = new ymaps.GeoObjectCollection();
    withCoords.forEach(function (p) {
      var num = seq[String(p.id)];
      var title = (p.number || p.external_id || '') + ' · ' + (p.weight_kg || 0) + ' кг';
      if (num) title = '#' + num + ' · ' + title;
      var preset = presetFor(p, style);
      var props = {
        balloonContent:
          '<strong>' +
          title +
          '</strong><br>' +
          (p.partner || '') +
          '<br>' +
          (p.address || ''),
        iconCaption: ''
      };
      if (style !== 'dot') {
        props.iconContent = num ? String(num) : '·';
      } else {
        props.iconCaption = p.number || p.external_id || '';
      }
      var mark = new ymaps.Placemark(
        [parseFloat(p.lat), parseFloat(p.lon)],
        props,
        { preset: preset, zIndex: 700, zIndexHover: 800 }
      );
      mark.__origPreset = preset;
      mark.events.add('click', function () {
        if (typeof highlightOrder === 'function') highlightOrder(p.id);
      });
      marks[p.id] = mark;
      collection.add(mark);
    });
    map.geoObjects.add(collection);
    window.__orderCollection = collection;
    return true;
  }

  function ensureStyleSwitcher() {
    if (document.getElementById('markerStyle')) return;
    var toolbar = document.querySelector('.toolbar');
    if (!toolbar) return;
    var sel = document.createElement('select');
    sel.id = 'markerStyle';
    sel.title = 'Стиль меток на карте';
    sel.style.maxWidth = '170px';
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
      if (!rebuildMarks()) {
        alert('Карта ещё не готова — подождите и выберите стиль снова.');
      }
    });
    toolbar.appendChild(sel);
  }

  /** Не даём штатному addMarks рисовать вторые точки */
  function patchAddMarks() {
    if (typeof window.__addMarksPatched !== 'undefined') return;
    try {
      if (typeof addMarks === 'function') {
        window.__origAddMarks = addMarks;
        // eslint-disable-next-line no-global-assign
        addMarks = function (map) {
          window.__logisticsMap = map;
          rebuildMarks();
          return window.__orderCollection;
        };
        window.__addMarksPatched = true;
      }
    } catch (e) {}
  }

  function boot() {
    ensureStyleSwitcher();
    patchAddMarks();

    var tries = 0;
    var t = setInterval(function () {
      tries++;
      patchAddMarks();
      if (window.__logisticsMap) {
        rebuildMarks();
        clearInterval(t);
      } else if (tries > 80) {
        clearInterval(t);
      }
    }, 200);

    // Перерисовка только после DnD, не на каждый клик
    document.addEventListener(
      'mousedown',
      function () {
        if (document.body.classList.contains('dd-dragging')) dragging = true;
      },
      true
    );
    document.addEventListener(
      'mouseup',
      function () {
        if (dragging || document.body.classList.contains('dd-dragging')) {
          dragging = false;
          setTimeout(rebuildMarks, 200);
        }
      },
      true
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.rebuildMapMarks = rebuildMarks;
})();
