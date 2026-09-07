/**
 * Админка v4:
 * - зоны | машины | привязки в один ряд
 * - стиль меток — панель над «Полигоны зон»
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
      '.admin-marker-wrap-header{display:none!important;}',
      '.admin-grid-top{',
      '  display:grid!important;',
      '  grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr)!important;',
      '  gap:16px!important;',
      '  width:100%!important;',
      '  margin:0 0 16px!important;',
      '  align-items:stretch!important;',
      '}',
      '.admin-grid-top > .panel{',
      '  position:relative!important;',
      '  float:none!important;',
      '  width:auto!important;',
      '  min-width:0!important;',
      '  margin:0!important;',
      '  box-sizing:border-box!important;',
      '  overflow:auto!important;',
      '}',
      '.admin-grid-top table{width:100%!important;table-layout:fixed;}',
      '.admin-grid-top th,.admin-grid-top td{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}',
      '@media(max-width:1100px){.admin-grid-top{grid-template-columns:1fr!important;}}',
      /* Панель меток над полигонами */',
      '#adminMarkerStyleBlock{',
      '  display:block!important;',
      '  margin:0 0 16px!important;',
      '}',
      '#adminMarkerStyleBlock .marker-row{',
      '  display:flex;align-items:center;gap:12px;flex-wrap:wrap;',
      '}',
      '#adminMarkerStyleBlock label{font-size:0.85rem;color:var(--muted);}',
      '#adminMarkerStyleBlock select{min-width:200px;padding:8px 12px;}'
    ].join('\n');
    document.head.appendChild(css);
  }

  function findPanelByTitle(re) {
    var found = null;
    document.querySelectorAll('.panel').forEach(function (p) {
      if (found) return;
      var head = p.querySelector('.panel-head, h2, h3');
      var t = head ? head.textContent : '';
      if (re.test(t || '')) found = p;
    });
    return found;
  }

  function wrapTopPanels() {
    var zones = findPanelByTitle(/^\s*Зон/i);
    var vehicles = findPanelByTitle(/^\s*Машин/i);
    var binds = findPanelByTitle(/Привяз/i);
    if (!zones || !vehicles || !binds) return;

    if (
      zones.parentNode &&
      zones.parentNode.classList &&
      zones.parentNode.classList.contains('admin-grid-top')
    ) {
      return;
    }

    var wrap = document.createElement('div');
    wrap.className = 'admin-grid-top';
    zones.parentNode.insertBefore(wrap, zones);
    wrap.appendChild(zones);
    wrap.appendChild(vehicles);
    wrap.appendChild(binds);
  }

  function mountMarkerAbovePolygons() {
    if (document.getElementById('adminMarkerStyleBlock')) return;

    // убрать из шапки, если вдруг остался
    var hdr = document.querySelector('.admin-marker-wrap');
    if (hdr && hdr.parentNode) hdr.parentNode.removeChild(hdr);

    var poly = findPanelByTitle(/Полигон/i);
    var box = document.createElement('div');
    box.className = 'panel';
    box.id = 'adminMarkerStyleBlock';
    box.innerHTML =
      '<div class="panel-head" style="margin-bottom:10px">' +
      '<h2 style="margin:0;font-size:0.95rem;color:var(--muted)">Метки на карте рабочего стола</h2>' +
      '</div>' +
      '<div class="marker-row">' +
      '<label for="adminMarkerStyle">Стиль</label>' +
      '<select id="adminMarkerStyle"></select>' +
      '<span class="muted" style="font-size:0.8rem">Сохраняется в браузере, применяется на рабочем столе</span>' +
      '</div>';

    if (poly && poly.parentNode) {
      poly.parentNode.insertBefore(box, poly);
    } else {
      var app = document.querySelector('.app');
      if (app) app.appendChild(box);
      else document.body.appendChild(box);
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
    mountMarkerAbovePolygons();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
