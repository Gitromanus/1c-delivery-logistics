# 1C Delivery Logistics

Рабочее место логиста: заявки из 1С УТ 11, зоны, машины, рейсы.

## Beget (как у вас сейчас)

Всё в **public_html**:

```
public_html/
  index.php
  bootstrap.php
  config.php          ← ваш файл с паролями БД
  src/                ← ОБЯЗАТЕЛЬНО залить
    Database.php
    ZoneMatcher.php
    TripBuilder.php
  api/
  admin/
  assets/
```

1. Импорт `sql/schema.sql` в MySQL.
2. `config.php` уже рядом с `index.php` — ок.
3. Залейте папку **`src`** из репозитория (`public/src/` → `public_html/src/`).
4. Обновите `bootstrap.php` с GitHub.
5. Смените `api_key` в config на свой секрет.

Откройте `http://z951445o.beget.tech/`

## API 1С

`POST /api/orders.php`  
Заголовок: `X-Api-Key: ...`

```json
{
  "external_id": "РН-18421",
  "number": "РН-18421",
  "doc_date": "2026-09-04",
  "partner": "ООО Тест",
  "address": "пос. Молодёжный, ул. Ленина, 12",
  "weight_kg": 120
}
```
