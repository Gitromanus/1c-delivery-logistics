// Генератор UPDATE-запросов для заполнения контрагента (partner) по external_id.
// Источник: C:/Users/User/Desktop/реализацииУТ11.txt (колонки: Документ|Номер|Дата|Способ|Адрес|Кол-во|Контрагент)
const fs = require('fs');
const path = require('path');

const SRC = 'C:/Users/User/Desktop/реализацииУТ11.txt';
const OUT = path.join(__dirname, 'sql', 'partner_update.sql');

const raw = fs.readFileSync(SRC, 'utf8');
const lines = raw.split(/\r?\n/);

const esc = s => String(s).replace(/'/g, "''").trim();

const updates = [];
let skipped = 0;
let total = 0;

for (let i = 0; i < lines.length; i++) {
  const t = lines[i].trim();
  if (t === '') continue;
  if (i === 0) continue; // заголовок

  const parts = t.split('\t');
  if (parts.length < 7) { skipped++; continue; }

  const number = parts[1].trim();   // Номер = external_id
  const partner = parts[6].trim();  // Контрагент

  if (!number) { skipped++; continue; }

  total++;
  const p = partner !== '' ? `'${esc(partner)}'` : 'NULL';
  updates.push(`UPDATE orders SET partner = ${p} WHERE external_id = '${esc(number)}';`);
}

let sql = 'SET NAMES utf8mb4;\n';
sql += '-- Обновление контрагента (partner) по external_id из реализацииУТ11.txt\n';
sql += '-- Записей: ' + total + '\n\n';
sql += updates.join('\n');
sql += '\n';

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, sql, 'utf8');
console.log('UPDATE запросов:', total);
console.log('Пропущено строк:', skipped);
console.log('Файл:', OUT);