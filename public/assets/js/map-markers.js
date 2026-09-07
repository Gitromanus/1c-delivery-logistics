/**
 * Метки v4: номер в рейсе. Стиль задаётся в админке (localStorage).
 */
(function () {
  var STYLE_KEY = 'logistics-marker-style';
  var STYLES = {
    circle: {
      assigned: 'islands#blueCircleIcon',
      neu: 'islands#orangeCircleIcon',
      done: 'islands#greenCircleIcon'
    },
    pin: {
      assigned: 'islands#blueIcon',
      neu: 'islands#orangeIcon',
      done: 'islands#greenIcon'
    },
    stretchy: {
      assigned: 'islands#blueStretchyIcon',
      neu: 'islands#orangeStretchyIcon',
      done: 'islands#greenStretchyIcon'
    },
    dot: {
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

  function patchAddMarks() {
    if (window.__addMarksPatched) return;
    try {
      if (typeof addMarks === 'function') {
        window.__origAddMarks = addMarks;
        addMarks = function (map) {
          window.__logisticsMap = map;
          rebuildMarks();
          return window.__orderCollection;
        };
        window.__addMarksPatched = true;
      }
    } catch (e) {}
  }

  // Убрать старый селект стиля с рабочего стола, если остался в DOM
  function removeDeskSwitcher() {
    var el = document.getElementById('markerStyle');
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function boot() {
    removeDeskSwitcher();
    patchAddMarks();

    var tries = 0;
    var t = setInterval(function () {
      tries++;
      patchAddMarks();
      removeDeskSwitcher();
      if (window.__logisticsMap) {
        rebuildMarks();
        clearInterval(t);
      } else if (tries > 80) {
        clearInterval(t);
      }
    }, 200);

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
