/**
 * Админка:
 * - стиль меток в шапке (компактно)
 * - сетка: зоны | машины | привязки в один ряд
 */
(function () {
  var STYLE_KEY = 'logistics-marker-style';
  var STYLES = {
    circle: 'Круг с номером',
    pin: 'Метка с номером',
    stretchy: 'Широкая с номером',
    dot: 'Точка'
  };

  function getStyle() {
    try {
      var s = localStorage.getItem(STYLE_KEY);
      if (s && STYLES[s]) return s;
    } catch (e) {}
    return 'circle';
  }

  function setStyle(k) {
    try {
      localStorage.setItem(STYLE_KEY, k);
    } catch (e) {}
  }

  function injectLayoutCss() {
    if (document.getElementById('admin-layout-css')) return;
    var css = document.createElement('style');
    css.id = 'admin-layout-css';
    css.textContent =
      /* Три колонки: зоны, машины, привязки */
      '.admin-grid-top{' +
      'display:grid!important;' +
      'grid-template-columns:repeat(3,minmax(0,1fr))!important;' +
      'gap:16px!important;' +
      'align-items:start!important;' +
      'margin-bottom:16px!important;' +
      '}' +
      '@media(max-width:1100px){.admin-grid-top{grid-template-columns:1fr!important;}}' +
      /* Скрыть старый блок настроек меток, если всплыл посередине */
      '#adminMarkerStyleBlock{display:none!important;}' +
      /* Компактный селект в шапке */
      '.admin-marker-wrap{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}' +
      '.admin-marker-wrap label{font-size:0.8rem;color:var(--muted);white-space:nowrap;}' +
      '.admin-marker-wrap select{max-width:180px;padding:8px 10px;font-size:0.85rem;}';
    document.head.appendChild(css);
  }

  function wrapTopPanels() {
    var panels = Array.prototype.slice.call(document.querySelectorAll('.app > .panel, .app .panel'));
    // Ищем панели по заголовкам
    var zones = null;
    var vehicles = null;
    var binds = null;
    document.querySelectorAll('.panel').forEach(function (p) {
      var h = (p.querySelector('h2,h3,.panel-head') || p).textContent || '';
      if (/Зон/i.test(h) && !zones) zones = p;
      else if (/Машин/i.test(h) && !vehicles) vehicles = p;
      else if (/Привяз/i.test(h) && !binds) binds = p;
    });
    if (!zones || !vehicles || !binds) return;
    if (zones.parentNode && zones.parentNode.classList.contains('admin-grid-top')) return;

    var wrap = document.createElement('div');
    wrap.className = 'admin-grid-top';
    var parent = zones.parentNode;
    parent.insertBefore(wrap, zones);
    wrap.appendChild(zones);
    wrap.appendChild(vehicles);
    wrap.appendChild(binds);
  }

  function mountMarkerInHeader() {
    if (document.getElementById('adminMarkerStyle')) return;

    // Убрать старый блок, если был
    var old = document.getElementById('adminMarkerStyleBlock');
    if (old && old.parentNode) old.parentNode.removeChild(old);

    var toolbar =
      document.querySelector('.toolbar') ||
      document.querySelector('.header') ||
      document.querySelector('.admin-nav');

    var wrap = document.createElement('div');
    wrap.className = 'admin-marker-wrap';
    wrap.innerHTML =
      '<label for="adminMarkerStyle">Метки</label>' +
      '<select id="adminMarkerStyle" title="Стиль меток на рабочем столе"></select>';

    if (toolbar) {
      toolbar.insertBefore(wrap, toolbar.firstChild);
    } else {
      document.body.insertBefore(wrap, document.body.firstChild);
    }

    var sel = document.getElementById('adminMarkerStyle');
    var cur = getStyle();
    Object.keys(STYLES).forEach(function (k) {
      var opt = document.createElement('option');
      opt.value = k;
      opt.textContent = STYLES[k];
      if (k === cur) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.addEventListener('change', function () {
      setStyle(sel.value);
    });
  }

  function boot() {
    injectLayoutCss();
    wrapTopPanels();
    mountMarkerInHeader();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
