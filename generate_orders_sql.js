// Генератор INSERT-запросов для таблицы orders
// Источник: C:/Users/User/Desktop/реализацииУТ11.txt
// Выход: sql/orders_seed.sql
const fs = require('fs');
const path = require('path');

const SRC = 'C:/Users/User/Desktop/реализацииУТ11.txt';
const OUT = path.join(__dirname, 'sql', 'orders_seed.sql');

const raw = fs.readFileSync(SRC, 'utf8');
const lines = raw.split(/\r?\n/);

const rows = [];
let headersSkipped = false;

for (const line of lines) {
  const t = line.trim();
  if (!t) continue;
  if (!headersSkipped) { headersSkipped = true; continue; } // заголовок

  const parts = t.split('\t');
  if (parts.length < 6) continue;

  const number = parts[1].trim();
  const dateStr = parts[2].trim();
  const address = parts[4].trim();
  const weightRaw = parts[5].trim();

  // Дата: ДД.ММ.ГГГГ -> ГГГГ-ММ-ДД (для колонки DATE)
  let docDate = 'NULL';
  const m = dateStr.match(/(\d{2})\.(\d{2})\.(\d{4})/);
  if (m) docDate = `'${m[3]}-${m[2]}-${m[1]}'`;

  // Вес: убираем все нецифровые символы (в т.ч. неразрывные пробелы типа "1 522")
  const weight = (weightRaw.replace(/[^\d]/g, '') || '0');

  const esc = s => s.replace(/'/g, "''");

  rows.push({
    external_id: number,
    number: number,
    doc_date: docDate,
    address: esc(address),
    weight: weight,
  });
}

let sql = 'SET NAMES utf8mb4;\n';
sql += '-- Заявки (реализации) из 1С УТ 11 для тренировки добавления на карту\n';
sql += 'INSERT INTO orders (external_id, number, doc_date, partner, address, weight_kg, status) VALUES\n';
sql += rows.map(r =>
  `('${r.external_id}','${r.number}',${r.doc_date},NULL,'${r.address}',${r.weight},'new')`
).join(',\n');
sql += ';\n';

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, sql, 'utf8');
console.log('Сгенерировано записей:', rows.length);
console.log('Файл:', OUT);