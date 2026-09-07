/** Компактные подписи v2 — без зависания */
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
    // Без MutationObserver — только после DnD через события
    document.addEventListener('mouseup', schedule);
    document.addEventListener('touchend', schedule);
    setInterval(run, 2000); // лёгкий подхват после API-обновлений
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
