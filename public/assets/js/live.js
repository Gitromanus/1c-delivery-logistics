/**
 * Автообновление только для внешних изменений (заявки из 1С).
 * После DnD / локальных действий страница НЕ перезагружается.
 */
(function () {
  var dateInput = document.querySelector('input[name="date"]');
  var date = dateInput ? dateInput.value : new Date().toISOString().slice(0, 10);
  var lastVersion = null;
  var intervalMs = 8000;
  var timer = null;
  /** до этого времени reload запрещён (после своего DnD/сохранения) */
  var quietUntil = 0;
  var wasDragging = false;

  function isDragging() {
    return document.body.classList.contains('dd-dragging');
  }

  /**
   * Вызывать после успешного локального действия (перенос заявки/машины),
   * чтобы poll не делал location.reload() из‑за той же смены version.
   */
  window.deskAckLocalChange = function () {
    quietUntil = Date.now() + 30000; // 30 сек только внешние обновления
    fetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.version) {
          lastVersion = data.version;
        }
      })
      .catch(function () {});
  };

  function poll() {
    if (document.hidden) return;

    // только что закончили drag — пометить тишину
    if (wasDragging && !isDragging()) {
      wasDragging = false;
      if (window.deskAckLocalChange) window.deskAckLocalChange();
    }
    if (isDragging()) {
      wasDragging = true;
      return;
    }

    fetch('api/desk_poll.php?date=' + encodeURIComponent(date), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.version) return;

        if (lastVersion === null) {
          lastVersion = data.version;
          return;
        }

        if (data.version === lastVersion) return;

        // Свои правки DnD уже на экране — только подтянуть version, без F5
        if (Date.now() < quietUntil) {
          lastVersion = data.version;
          return;
        }

        // Реально новые данные снаружи (1С и т.п.)
        lastVersion = data.version;
        document.title = '● Логистика — новые заявки';
        location.reload();
      })
      .catch(function () {});
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
