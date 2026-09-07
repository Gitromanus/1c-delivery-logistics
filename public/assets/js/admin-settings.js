/**
 * Настройки в админке: стиль меток на карте рабочего стола.
 * Пишет в localStorage (logistics-marker-style) — тот же ключ, что читает map-markers.js
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

  function mount() {
    if (document.getElementById('adminMarkerStyle')) return;

    // Блок настроек на странице админки
    var host =
      document.querySelector('.panel') ||
      document.querySelector('.app') ||
      document.body;

    var box = document.createElement('div');
    box.className = 'panel';
    box.style.marginTop = '16px';
    box.innerHTML =
      '<h2 style="margin-bottom:10px">Карта рабочего стола</h2>' +
      '<label class="muted" for="adminMarkerStyle">Стиль меток заявок</label><br>' +
      '<select id="adminMarkerStyle" style="margin-top:8px;min-width:220px"></select>' +
      '<p class="muted" style="margin-top:10px;font-size:0.8rem">Настройка сохраняется в этом браузере и применяется на рабочем столе.</p>';

    // Вставить после первого panel или в конец app
    var firstPanel = document.querySelector('.panel');
    if (firstPanel && firstPanel.parentNode) {
      firstPanel.parentNode.insertBefore(box, firstPanel.nextSibling);
    } else {
      host.appendChild(box);
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
      var note = document.createElement('div');
      note.className = 'flash flash-ok';
      note.textContent = 'Стиль меток сохранён. Обновите рабочий стол, если он уже открыт.';
      box.appendChild(note);
      setTimeout(function () {
        if (note.parentNode) note.parentNode.removeChild(note);
      }, 3500);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
