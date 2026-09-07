/**
 * Админка v6 — метки на всю ширину ПОД рядом зоны/машины/привязки
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
    if (document.getElementById('admin-layout-css')) {
      document.getElementById('admin-layout-css').remove();
    }
    var css = document.createElement('style');
    css.id = 'admin-layout-css';
    css.textContent = [
      /* Ряд из 3 карточек — на всю ширину родителя-сетки */',
      '.admin-grid-top{',
      '  display:grid!important;',
      '  grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr)!important;',
      '  gap:16px!important;',
      '  width:100%!important;',
      '  max-width:100%!important;',
      '  margin:0 0 16px!important;',
      '  grid-column:1/-1!important;',
      '  flex:0 0 100%!important;',
      '}',
      '.admin-grid-top > .panel, .admin-grid-top > section.panel{',
      '  position:relative!important;float:none!important;',
      '  width:auto!important;min-width:0!important;margin:0!important;',
      '  box-sizing:border-box!important;overflow:auto!important;',
      '}',
      '@media(max-width:1100px){.admin-grid-top{grid-template-columns:1fr!important;}}',
      /* Метки — отдельная полоса на всю ширину, не 4-я колонка */',
      '#adminMarkerStyleBlock{',
      '  display:block!important;',
      '  width:100%!important;',
      '  max-width:100%!important;',
      '  box-sizing:border-box!important;',
      '  margin:0 0 16px!important;',
      '  grid-column:1/-1!important;',
      '  flex:0 0 100%!important;',
      '  float:none!important;',
      '}',
      '#adminMarkerStyleBlock .marker-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}',
      '#adminMarkerStyleBlock label{font-size:0.85rem;color:var(--muted);}',
      '#adminMarkerStyleBlock select{min-width:200px;padding:8px 12px;}',
      /* Полигоны тоже на всю ширину */',
      'section.panel h2{font-size:0.95rem;}'
    ].join('\n');
    document.head.appendChild(css);
  }

  function findPanelByTitle(re) {
    var found = null;
    document.querySelectorAll('section.panel, .panel').forEach(function (p) {
      if (found) return;
      if (p.id === 'adminMarkerStyleBlock') return;
      if (p.closest && p.closest('.admin-grid-top') && !re.test('')) {
        /* still allow finding inside grid by title */
      }
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
    if (!zones || !vehicles || !binds) return null;

    if (zones.parentNode && zones.parentNode.classList && zones.parentNode.classList.contains('admin-grid-top')) {
      return zones.parentNode;
    }

    var wrap = document.createElement('div');
    wrap.className = 'admin-grid-top';
    zones.parentNode.insertBefore(wrap, zones);
    wrap.appendChild(zones);
    wrap.appendChild(vehicles);
    wrap.appendChild(binds);
    return wrap;
  }

  function mountMarkerBlock(grid) {
    var poly = findPanelByTitle(/Полигон/i);
    var box = document.getElementById('adminMarkerStyleBlock');

    if (!box) {
      box = document.createElement('section');
      box.className = 'panel';
      box.id = 'adminMarkerStyleBlock';
      box.innerHTML =
        '<h2 style="margin:0 0 10px;font-size:0.95rem;color:var(--muted);font-weight:600">Метки на карте рабочего стола</h2>' +
        '<div class="marker-row">' +
        '<label for="adminMarkerStyle">Стиль</label> ' +
        '<select id="adminMarkerStyle"></select> ' +
        '<span class="muted" style="font-size:0.8rem">Сохраняется в браузере, применяется на рабочем столе</span>' +
        '</div>';

      var sel = box.querySelector('#adminMarkerStyle');
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

    // Вставить: после grid, перед полигонами
    if (grid && grid.parentNode) {
      if (poly && poly.parentNode === grid.parentNode) {
        poly.parentNode.insertBefore(box, poly);
      } else if (grid.nextSibling) {
        grid.parentNode.insertBefore(box, grid.nextSibling);
      } else {
        grid.parentNode.appendChild(box);
      }
    } else if (poly && poly.parentNode) {
      poly.parentNode.insertBefore(box, poly);
    }
  }

  function boot() {
    injectLayoutCss();
    var grid = wrapTopPanels();
    mountMarkerBlock(grid);
    setTimeout(function () {
      injectLayoutCss();
      var g = document.querySelector('.admin-grid-top') || wrapTopPanels();
      mountMarkerBlock(g);
    }, 300);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
