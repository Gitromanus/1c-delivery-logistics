/**
 * Админка v3:
 * - метки в шапке
 * - зоны | машины | привязки в один ряд без наложений
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
    css.textContent = [
      '#adminMarkerStyleBlock{display:none!important;}',
      '.admin-marker-wrap{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}',
      '.admin-marker-wrap label{font-size:0.8rem;color:var(--muted);white-space:nowrap;}',
      '.admin-marker-wrap select{max-width:180px;padding:8px 10px;font-size:0.85rem;}',
      /* Ряд из трёх карточек */',
      '.admin-grid-top{',
      '  display:grid!important;',
      '  grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr)!important;',
      '  gap:16px!important;',
      '  width:100%!important;',
      '  max-width:100%!important;',
      '  margin:0 0 16px!important;',
      '  padding:0!important;',
      '  align-items:stretch!important;',
      '  position:relative!important;',
      '  clear:both!important;',
      '  float:none!important;',
      '}',
      '.admin-grid-top > .panel{',
      '  position:relative!important;',
      '  float:none!important;',
      '  left:auto!important;',
      '  right:auto!important;',
      '  top:auto!important;',
      '  width:auto!important;',
      '  max-width:100%!important;',
      '  min-width:0!important;',
      '  margin:0!important;',
      '  box-sizing:border-box!important;',
      '  overflow:auto!important;',
      '  grid-column:auto!important;',
      '  grid-row:auto!important;',
      '}',
      '.admin-grid-top table{width:100%!important;table-layout:fixed;}',
      '.admin-grid-top th,.admin-grid-top td{',
      '  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;',
      '}',
      '@media(max-width:1100px){',
      '  .admin-grid-top{grid-template-columns:1fr!important;}',
      '}'
    ].join('\n');
    document.head.appendChild(css);
  }

  function findPanelByTitle(re) {
    var found = null;
    document.querySelectorAll('.panel').forEach(function (p) {
      if (found) return;
      if (p.closest('.admin-grid-top')) return;
      var head = p.querySelector('.panel-head, h2, h3');
      var t = head ? head.textContent : p.textContent.slice(0, 80);
      if (re.test(t || '')) found = p;
    });
    return found;
  }

  function wrapTopPanels() {
    var zones = findPanelByTitle(/^\s*Зон/i);
    var vehicles = findPanelByTitle(/^\s*Машин/i);
    var binds = findPanelByTitle(/Привяз/i);
    if (!zones || !vehicles || !binds) return;

    // Уже обёрнуты
    if (
      zones.parentNode &&
      zones.parentNode === vehicles.parentNode &&
      vehicles.parentNode === binds.parentNode &&
      zones.parentNode.classList.contains('admin-grid-top')
    ) {
      return;
    }

    var wrap = document.createElement('div');
    wrap.className = 'admin-grid-top';

    var parent = zones.parentNode;
    parent.insertBefore(wrap, zones);

    // Порядок: зоны → машины → привязки
    wrap.appendChild(zones);
    wrap.appendChild(vehicles);
    wrap.appendChild(binds);

    // Сброс инлайнов, если были
    [zones, vehicles, binds].forEach(function (p) {
      p.style.position = '';
      p.style.left = '';
      p.style.top = '';
      p.style.width = '';
      p.style.float = '';
      p.style.gridColumn = '';
      p.style.margin = '';
    });
  }

  function mountMarkerInHeader() {
    if (document.getElementById('adminMarkerStyle')) return;

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

    if (toolbar) toolbar.insertBefore(wrap, toolbar.firstChild);
    else document.body.insertBefore(wrap, document.body.firstChild);

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
