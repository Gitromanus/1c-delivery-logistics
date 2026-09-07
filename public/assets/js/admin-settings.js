/**
 * Админка v7 — стиль меток внутри блока «Полигоны зон»
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
    var old = document.getElementById('admin-layout-css');
    if (old) old.remove();
    var css = document.createElement('style');
    css.id = 'admin-layout-css';
    css.textContent = [
      '.admin-grid-top{',
      '  display:grid!important;',
      '  grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr)!important;',
      '  gap:16px!important;width:100%!important;margin:0 0 16px!important;',
      '  grid-column:1/-1!important;',
      '}',
      '.admin-grid-top > .panel,.admin-grid-top > section.panel{',
      '  position:relative!important;float:none!important;',
      '  width:auto!important;min-width:0!important;margin:0!important;',
      '  overflow:auto!important;box-sizing:border-box!important;',
      '}',
      '@media(max-width:1100px){.admin-grid-top{grid-template-columns:1fr!important;}}',
      '#adminMarkerStyleBlock{display:none!important;}',
      '.admin-marker-in-poly{',
      '  display:flex;align-items:center;gap:10px;flex-wrap:wrap;',
      '  margin:0 0 12px;padding:10px 12px;',
      '  background:var(--panel-2,#f0f2f5);border-radius:8px;',
      '  border:1px solid var(--border,#dde1e8);',
      '}',
      '.admin-marker-in-poly label{font-size:0.85rem;color:var(--muted);white-space:nowrap;}',
      '.admin-marker-in-poly select{min-width:200px;padding:8px 12px;}'
    ].join('\n');
    document.head.appendChild(css);
  }

  function findPanelByTitle(re) {
    var found = null;
    document.querySelectorAll('section.panel, .panel').forEach(function (p) {
      if (found) return;
      var head = p.querySelector('.panel-head h2, h2, h3');
      var t = head ? (head.textContent || '').trim() : '';
      if (re.test(t)) found = p;
    });
    return found;
  }

  function wrapTopPanels() {
    var zones = findPanelByTitle(/^Зон/i);
    var vehicles = findPanelByTitle(/^Машин/i);
    var binds = findPanelByTitle(/Привяз/i);
    if (!zones || !vehicles || !binds) return;
    if (zones.parentNode && zones.parentNode.classList && zones.parentNode.classList.contains('admin-grid-top')) {
      return;
    }
    var wrap = document.createElement('div');
    wrap.className = 'admin-grid-top';
    zones.parentNode.insertBefore(wrap, zones);
    wrap.appendChild(zones);
    wrap.appendChild(vehicles);
    wrap.appendChild(binds);
  }

  function mountMarkerInPolygons() {
    if (document.getElementById('adminMarkerStyle')) return;

    var poly = findPanelByTitle(/Полигон/i);
    if (!poly) {
      // запасной вариант — в .app
      poly = document.querySelector('.app');
      if (!poly) return;
    }

    var row = document.createElement('div');
    row.className = 'admin-marker-in-poly';
    row.innerHTML =
      '<label for="adminMarkerStyle"><strong>Метки на карте</strong> — стиль</label>' +
      '<select id="adminMarkerStyle"></select>' +
      '<span class="muted" style="font-size:0.8rem">для рабочего стола</span>';

    // после заголовка h2
    var h2 = poly.querySelector('h2');
    if (h2 && h2.nextSibling) {
      poly.insertBefore(row, h2.nextSibling);
    } else if (h2) {
      h2.parentNode.insertBefore(row, h2.nextSibling);
    } else {
      poly.insertBefore(row, poly.firstChild);
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
    mountMarkerInPolygons();
    setTimeout(function () {
      wrapTopPanels();
      if (!document.getElementById('adminMarkerStyle')) mountMarkerInPolygons();
    }, 400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
