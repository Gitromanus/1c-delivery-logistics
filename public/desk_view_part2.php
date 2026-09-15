<!-- Окно результатов геокодирования (текст можно скопировать) -->
<div id="geoLog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;color:#111;max-width:760px;width:100%;max-height:82vh;display:flex;flex-direction:column;border-radius:8px;padding:16px;box-sizing:border-box;box-shadow:0 10px 40px rgba(0,0,0,.4);">
    <div style="margin-bottom:8px;font-weight:bold;font-size:14px;">Результат геокодирования</div>
    <textarea id="geoLogText" readonly style="flex:1;width:100%;min-height:200px;font-family:monospace;font-size:12px;resize:vertical;box-sizing:border-box;"></textarea>
    <div style="margin-top:10px;text-align:right;">
      <button type="button" class="btn btn-primary" id="geoLogCopy">Скопировать</button>
      <button type="button" class="btn btn-ghost" id="geoLogClose">Закрыть и обновить</button>
    </div>
  </div>
</div>

<script>
const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
const needGeo = <?= json_encode($needGeo, JSON_UNESCAPED_UNICODE) ?>;
const zonePolys = <?= json_encode(array_map(function ($p) {
    return [
        'zone_id' => (int) $p['zone_id'],
        'zone_name' => $p['zone_name'],
        'color' => (string) ($p['color'] ?? ''),
        'points' => json_decode((string) $p['polygon'], true) ?: [],
    ];
}, $zonePolys), JSON_UNESCAPED_UNICODE) ?>;

// Окно результатов геокодирования (можно скопировать)
function showGeoLog(lines) {
  const ta = document.getElementById('geoLogText');
  if (ta) ta.value = lines.join('\n');
  const box = document.getElementById('geoLog');
  if (box) box.style.display = 'flex';
}
const logClose = document.getElementById('geoLogClose');
const logCopy = document.getElementById('geoLogCopy');
if (logClose) logClose.addEventListener('click', () => location.reload());
if (logCopy) logCopy.addEventListener('click', () => {
  const ta = document.getElementById('geoLogText');
  if (!ta) return;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(ta.value).then(() => { logCopy.textContent = 'Скопировано'; });
  } else {
    ta.focus(); ta.select();
    try { document.execCommand('copy'); logCopy.textContent = 'Скопировано'; } catch (e) {}
  }
});

document.getElementById('rebuildBtn').addEventListener('click', async () => {
  const btn = document.getElementById('rebuildBtn');
  btn.disabled = true;
  btn.textContent = 'Сборка…';
  try {
    const r = await fetch('api/rebuild.php?date=<?= urlencode($date) ?>', { method: 'POST' });
    const data = await r.json();
    if (data.warnings && data.warnings.length) alert('Готово.\n\n' + data.warnings.join('\n'));
    location.reload();
  } catch (e) {
    alert(e.message);
    btn.disabled = false;
    btn.textContent = 'Пересобрать рейсы';
  }
});

