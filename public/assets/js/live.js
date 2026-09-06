/**
 * Автообновление рабочего стола при новых заявках из 1С.
 * Не перезагружает страницу во время drag-and-drop.
 */
(function () {
  var dateInput = document.querySelector('input[name="date"]');
  var date = dateInput ? dateInput.value : new Date().toISOString().slice(0, 10);
  var lastVersion = null;
  var intervalMs = 10000; // 10 сек
  var timer = null;

  function isDragging() {
    return document.body.classList.contains('dd-dragging');
  }

  function poll() {
    if (document.hidden || isDragging()) return;
    fetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.version) return;
        if (lastVersion === null) {
          lastVersion = data.version;
          return;
        }
        if (data.version !== lastVersion) {
          lastVersion = data.version;
          // короткая пометка в title
          document.title = '● Логистика — новые данные';
          location.reload();
        }
      })
      .catch(function () { /* сеть */ });
  }

  function start() {
    if (timer) clearInterval(timer);
    poll();
    timer = setInterval(poll, intervalMs);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });

  start();
})();
