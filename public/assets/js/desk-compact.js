/**
 * Компактные подписи зон и рейсов. Без бесконечного цикла MutationObserver.
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
    if (veh) {
      html += '<span class="cap-veh" title="Машин">' + IC_TRUCK + veh[1] + '</span>';
    }
    el.innerHTML = html;
    el.setAttribute('data-compact', '1');
  }

  function compactTripWeight(el) {
    if (!el) return;
    var trip = el.closest('.trip');
    var n = trip ? trip.querySelectorAll('.orders-list > .drag-order').length : 0;
    var prev = el.getAttribute('data-compact-n');
    if (el.getAttribute('data-compact') === '1' && prev === String(n)) return;

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
    } finally {
      busy = false;
    }
  }

  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(run, 50);
  }

  function start() {
    run();
    var root = document.querySelector('.app') || document.body;
    var obs = new MutationObserver(function (mutations) {
      if (busy) return;
      // игнор наших же правок
      for (var i = 0; i < mutations.length; i++) {
        var t = mutations[i].target;
        if (t && t.nodeType === 1 && t.getAttribute && t.getAttribute('data-compact') === '1') {
          continue;
        }
        // DnD или refreshZone сбросил текст — нужно снова сжать
        if (mutations[i].type === 'childList' || mutations[i].type === 'characterData') {
          var el = mutations[i].target;
          if (el && el.nodeType === 1) {
            if (el.classList && (el.classList.contains('zone-cap') || el.classList.contains('trip-weight') || el.classList.contains('meta'))) {
              el.removeAttribute('data-compact');
              el.removeAttribute('data-compact-n');
            }
            if (el.classList && el.classList.contains('orders-list')) {
              var tw = el.closest('.trip') && el.closest('.trip').querySelector('.trip-weight');
              if (tw) {
                tw.removeAttribute('data-compact');
                tw.removeAttribute('data-compact-n');
              }
            }
          }
          schedule();
          return;
        }
      }
    });
    obs.observe(root, { childList: true, subtree: true, characterData: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
