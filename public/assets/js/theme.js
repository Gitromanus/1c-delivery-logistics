/**
 * Переключатель светлая / тёмная тема.
 * Сохраняется в localStorage (ключ logistics-theme).
 */
(function () {
  var KEY = 'logistics-theme';

  function preferred() {
    try {
      var saved = localStorage.getItem(KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch (e) {}
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
      return 'light';
    }
    return 'dark';
  }

  function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(KEY, theme); } catch (e) {}
    var btn = document.getElementById('themeToggle');
    if (btn) {
      btn.textContent = theme === 'light' ? '🌙' : '☀️';
      btn.title = theme === 'light' ? 'Тёмная тема' : 'Светлая тема';
      btn.setAttribute('aria-label', btn.title);
    }
  }

  // Сразу до отрисовки, чтобы не мигало
  apply(preferred());

  function ensureButton() {
    if (document.getElementById('themeToggle')) return;
    var toolbar = document.querySelector('.toolbar');
    if (!toolbar) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'themeToggle';
    btn.className = 'btn btn-ghost btn-theme';
    var theme = document.documentElement.getAttribute('data-theme') || 'dark';
    btn.textContent = theme === 'light' ? '🌙' : '☀️';
    btn.title = theme === 'light' ? 'Тёмная тема' : 'Светлая тема';
    btn.addEventListener('click', function () {
      var cur = document.documentElement.getAttribute('data-theme') || 'dark';
      apply(cur === 'light' ? 'dark' : 'light');
    });
    // В конец тулбара
    toolbar.appendChild(btn);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureButton);
  } else {
    ensureButton();
  }
})();
