/**
 * Компактные подписи зон и рейсов: иконки вместо длинных слов.
 * Работает с исходной вёрсткой и после DnD (MutationObserver).
 */
(function () {
  var IC_SCALE = '<span class="ic ic-scale" aria-hidden="true"></span>';
  var IC_TRUCK = '<span class="ic ic-truck" aria-hidden="true"></span>';
  var IC_BOX = '<span class="ic ic-box" aria-hidden="true"></span>';

  function compactMeta(el) {
    if (!el || el.querySelector('.ic-box')) return;
    var t = (el.textContent || '').trim();
    var m = t.match(/(\d+)\s*заявок?/i) || t.match(/^(\d+)$/);
    if (!m) return;
    el.classList.add('meta-orders');
    el.title = 'Заявок';
    el.innerHTML = IC_BOX + m[1];
  }

  function compactCap(el) {
    if (!el || el.querySelector('.cap-load')) return;
    var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
    // «Загружено: 0 / 1 900 кг · машин: 1» или уже «0 / 1900 …»
    var load = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    var veh = t.match(/машин[аы]?\s*:\s*(\d+)/i);
    if (!load) return;
    var a = load[1].replace(/\s/g, '\u00a0').trim();
    var b = load[2].replace(/\s/g, '\u00a0').trim();
    // убрать возможный «кг» в конце b
    b = b.replace(/\s*кг$/i, '');
    var html = '<span class="cap-load" title="Загрузка, кг">' + IC_SCALE + a + ' / ' + b + '</span>';
    if (veh) {
      html += '<span class="cap-veh" title="Машин">' + IC_TRUCK + veh[1] + '</span>';
    }
    el.innerHTML = html;
  }

  function compactTripWeight(el) {
    if (!el || el.querySelector('.ic-scale')) return;
    var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
    var m = t.match(/([\d\s]+)\s*\/\s*([\d\s]+)/);
    if (!m) return;
    var a = m[1].trim();
    var b = m[2].replace(/\s*кг$/i, '').trim();
    el.title = 'Загрузка, кг';
    el.innerHTML = IC_SCALE + a + ' / ' + b;
  }

  function run() {
    document.querySelectorAll('.zone-card .meta').forEach(compactMeta);
    document.querySelectorAll('.zone-cap').forEach(compactCap);
    document.querySelectorAll('.trip-weight').forEach(compactTripWeight);
  }

  function start() {
    run();
    var root = document.querySelector('.app') || document.body;
    var obs = new MutationObserver(function () {
      run();
    });
    obs.observe(root, { childList: true, subtree: true, characterData: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
