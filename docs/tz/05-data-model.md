> Раздел ТЗ horeca-shop. Версия, оглавление, история изменений и карта «спринт → разделы» — [TZ-horeca-shop.md](../../TZ-horeca-shop.md).

# 5. Модель данных

MariaDB 11.8 / MySQL 8.4, `utf8mb4_unicode_ci`, InnoDB. Типы указаны точно, миграции пишутся ровно по ним. Внешние ключи между `users` и `companies` добавляются отдельной миграцией после создания обеих таблиц. Стандартные таблицы Laravel (`sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) и таблица `media` medialibrary создаются штатными миграциями.

## 5.1 Пользователи и компании

**users**
`id` · `name` string(150) · `email` string(150) unique · `phone` string(20) nullable index · `password` string · `role` enum(customer,manager,admin) default customer · `company_id` bigint nullable FK companies nullOnDelete · `is_active` bool default true · `email_verified_at` timestamp nullable · `last_login_at` timestamp nullable · `app_authentication_secret` text nullable · `app_authentication_recovery_codes` text nullable (встроенная 2FA Filament 5; значения шифруются) · `remember_token` · timestamps

**companies**
`id` · `legal_name` string(255) · `brand_name` string(255) nullable (вывеска заведения) · `inn` string(12) index · `kpp` string(9) nullable · `ogrn` string(15) nullable · `legal_address` string(500) nullable · `delivery_address` string(500) nullable · `city` string(150) nullable · `bank_name` string(255) nullable · `bik` string(9) nullable · `account` string(20) nullable · `corr_account` string(20) nullable · `contact_person` string(150) · `phone` string(20) · `email` string(150) · `segment` enum(restaurant,cafe,bar,hotel,canteen,bakery,production,retail_chain,other) · `status` enum(pending,approved,rejected,blocked) default pending · `price_tier_id` bigint nullable FK price_tiers nullOnDelete · `manager_comment` text nullable · `approved_at` timestamp nullable · `approved_by` bigint nullable FK users nullOnDelete · timestamps

Пользователь относится к компании через `users.company_id`. В MVP у компании один пользователь — тот, кто подал заявку.

**price_tiers**
`id` · `name` string(100) · `slug` string(100) unique · `discount_percent` decimal(5,2) default 0 · `min_order_amount` decimal(12,2) default 0 · `is_default` bool default false · `sort` smallint default 0 · timestamps

## 5.2 Каталог

**categories** — витринное дерево, им управляет менеджер
`id` · `parent_id` bigint nullable FK self nullOnDelete · `name` string(255) · `slug` string(160) unique · `description` text nullable · `icon` string(64) nullable (имя SVG-иконки) · `show_on_home` bool default false · `meta_title` string(255) nullable · `meta_description` string(500) nullable · `h1` string(255) nullable · `seo_text` longtext nullable · `sort` int default 0 · `is_active` bool default false · `products_count` int default 0 (денормализация, пересчёт после импорта) · timestamps
Индексы: `parent_id`, (`is_active`,`sort`).

**brands**
`id` · `name` string(150) · `slug` string(160) unique · `country` string(64) nullable · `description` text nullable · `is_active` bool default true · timestamps

**suppliers**
`id` · `name` string(150) · `slug` string(100) unique · `price_kind` enum(rrp,dealer) default rrp · `markup_percent` decimal(5,2) default 0 (только для `dealer`) · `retail_round_to` smallint default 1 (округление розницы вверх до N ₽) · `config` text nullable (cast `encrypted:array`: адреса, токены API) · `is_active` bool default true · `last_import_at` timestamp nullable · timestamps

**supplier_refs** — соответствие сущностей поставщика нашим
`id` · `supplier_id` FK cascade · `entity` enum(category,brand,warehouse,attribute) · `external_key` string(191) (GUID; если источник GUID не даёт — нормализованное имя) · `name` string(255) · `parent_key` string(191) nullable · `local_id` bigint nullable (id в `categories`, `brands`, `warehouses` или `attributes`) · `is_ignored` bool default false · `last_seen_at` timestamp nullable · timestamps
Индексы: unique(`supplier_id`,`entity`,`external_key`), (`entity`,`local_id`).

**products**
`id` ·
`supplier_id` FK suppliers ·
`external_id` string(64) — GUID номенклатуры поставщика ·
`supplier_code` string(64) nullable — код 1С (`ЦБ-Ц0017339`) ·
`sku` string(64) nullable — артикул (пуст у 22%, бывает неуникальным) ·
`model` string(255) nullable ·
`name` string(255) ·
`slug` string(160) unique — создаётся один раз, импорт его не меняет ·
`category_id` bigint nullable FK categories nullOnDelete ·
`brand_id` bigint nullable FK brands nullOnDelete ·
`short_description` text nullable · `description` longtext nullable ·
`unit` string(16) default 'шт' ·
`rrp_price` decimal(12,2) nullable — РРЦ поставщика ·
`purchase_price` decimal(12,2) nullable — дилерская цена, появится в API ·
`retail_price` decimal(12,2) nullable — наша розничная цена (§7); `null` = цена по запросу ·
`old_price` decimal(12,2) nullable — зачёркнутая цена ручной акции ·
`availability` enum(in_stock,low,incoming,on_order,discontinued) default on_order ·
`availability_rank` tinyint default 3 — для сортировки (§6.5) ·
`is_visible` bool default true — ручное скрытие, импорт не трогает ·
`is_new` bool default false · `is_hit` bool default false ·
`weight_kg` decimal(8,3) nullable · `length_mm`, `width_mm`, `height_mm` int unsigned nullable — из характеристик API ·
`warranty_months` smallint nullable ·
`meta_title` string(255) nullable · `meta_description` string(500) nullable · `h1` string(255) nullable · `seo_text` longtext nullable ·
`search_text` text nullable — нормализованная строка поиска (§8.4) ·
`popularity` int default 0 (§8.2) ·
`locked_fields` json nullable — поля, изменённые вручную (§6.6) ·
`source_hash` char(32) nullable ·
`missing_runs` smallint default 0 ·
`last_synced_at` timestamp nullable ·
timestamps + softDeletes
Индексы: unique(`supplier_id`,`external_id`), `supplier_code`, `sku`, unique `slug`, (`is_visible`,`availability_rank`), `category_id`, `brand_id`, `retail_price`.

**Изображения** — medialibrary, коллекция `images` у `Product`. Конверсии в WebP: `thumb` 160×160, `card` 600×600, `full` 1200×1200 (вписывание без обрезки и без увеличения; внешние оптимизаторы изображений не запускаются). Пользовательские свойства медиа: `source` (supplier|manual), `source_url`, `source_hash`, `sort`.

**warehouses**
`id` · `supplier_id` FK cascade · `name` string(150) · `slug` string(160) · `city` string(150) nullable · `delivery_days_min` smallint nullable · `delivery_days_max` smallint nullable (срок до Симферополя; заполняет менеджер) · `is_visible` bool default true · `sort` smallint default 0 · timestamps · unique(`supplier_id`,`name`)

**product_stocks**
`id` · `product_id` FK cascade · `warehouse_id` FK cascade · `status` enum(in_stock,low,out) · `quantity` decimal(12,3) nullable (API) · `raw_value` string(64) nullable (текст из XML) · `unit` string(16) nullable · `synced_at` timestamp · unique(`product_id`,`warehouse_id`)

**attributes**
`id` · `name` string(150) · `slug` string(160) unique · `unit` string(16) nullable · `type` enum(string,number,bool) · `is_filterable` bool default false · `is_main` bool default false (показывать в блоке покупки) · `sort` smallint default 0 · timestamps

**attribute_product**
`product_id` FK cascade · `attribute_id` FK cascade · `value_string` string(255) nullable · `value_number` decimal(14,3) nullable · `value_bool` bool nullable · `raw_value` string(255) nullable (как пришло: «400мм») · `source` enum(supplier,manual) default supplier · PK(`product_id`,`attribute_id`)
Индексы: (`attribute_id`,`value_number`), (`attribute_id`,`value_string`).

**product_prices** — точечная цена товара для ценовой группы
`id` · `product_id` FK cascade · `price_tier_id` FK cascade · `price` decimal(12,2) · timestamps · unique(`product_id`,`price_tier_id`)

**related_products**
`product_id` FK cascade · `related_id` FK products cascade · `sort` smallint default 0 · PK(`product_id`,`related_id`)

**collections** — подборки «Соберём кухню под задачу» (модель `ProductCollection`, чтобы не путать с коллекциями Laravel)
`id` · `name` string(150) · `slug` string(160) unique · `description` text nullable · `icon` string(64) nullable · `is_active` bool default false · `sort` smallint default 0 · timestamps

**collection_product**
`collection_id` FK cascade · `product_id` FK cascade · `sort` smallint default 0 · PK(`collection_id`,`product_id`)

## 5.3 Импорт

**import_profiles**
`id` · `supplier_id` FK cascade · `name` string(150) · `source` string(64) — ключ адаптера (`rosholod.catalog_xml`, `rosholod.stock_xml`, `rosholod.api`) · `url` string(500) nullable · `schedule` string(64) nullable (cron-выражение) · `settings` json nullable (пороги §6.3) · `is_active` bool default false · `last_etag` string(191) nullable · `last_modified` string(64) nullable · timestamps

**import_runs**
`id` · `import_profile_id` FK cascade · `user_id` bigint nullable FK users nullOnDelete · `trigger` enum(schedule,manual,cli) · `status` enum(queued,running,success,skipped,failed) · `is_dry_run` bool default false · `file_path` string(500) nullable · `file_hash` char(32) nullable (MD5 содержимого файла) · `source_version` string(191) nullable (ETag или атрибут `date`) · `rows_total` (все записи staging), `created`, `updated`, `unchanged`, `discontinued`, `errors` (невалидные записи) int unsigned default 0 · `log` json nullable (первые 500 проблем) · `log_file` string(500) nullable · `error_message` text nullable · `started_at`, `finished_at` timestamp nullable · timestamps

**import_rows** — промежуточная таблица, очищается после прогона
`id` · `import_run_id` FK cascade · `entity` enum(category,product,stock) · `external_id` string(191) · `payload` json · `hash` char(32) · `is_valid` bool · `error` string(500) nullable
Индекс: (`import_run_id`,`entity`).

## 5.4 Продажи

**carts** — `id` · `user_id` bigint nullable FK cascade · `session_id` string(100) nullable index · `expires_at` timestamp · timestamps

**cart_items** — `id` · `cart_id` FK cascade · `product_id` FK cascade · `qty` int unsigned · `price` decimal(12,2) (цена на момент добавления) · timestamps · unique(`cart_id`,`product_id`)

**orders**
`id` · `number` string(16) unique (формат `HR-260916-0042`) · `idempotency_key` char(36) unique · `user_id` bigint nullable FK nullOnDelete · `company_id` bigint nullable FK nullOnDelete ·
`type` enum(retail,wholesale) ·
`status` enum(new,processing,confirmed,invoiced,paid,shipped,completed,canceled) default new ·
`customer_name` string(150) · `phone` string(20) · `email` string(150) nullable ·
`is_legal_entity` bool default false · `inn` string(12) nullable · `company_name` string(255) nullable ·
`delivery_method` enum(pickup,transport_company,courier_city) · `delivery_city` string(150) nullable · `delivery_address` string(500) nullable · `tk_name` string(100) nullable ·
`payment_method` enum(invoice,cash,card_on_delivery,online) default invoice ·
`comment` text nullable · `manager_comment` text nullable · `manager_id` bigint nullable FK users nullOnDelete ·
`subtotal`, `discount`, `total` decimal(12,2) ·
`invoice_path` string(500) nullable ·
`payment_id` string(64) nullable · `paid_at` timestamp nullable — под будущую ЮKassa, в MVP всегда null ·
`utm` json nullable · `ip` string(45) nullable · `user_agent` string(500) nullable ·
timestamps · `deleted_at` (мягкое удаление администратором, §12)
Индексы: `status`, `created_at`, `user_id`.

**order_counters** — `date` date PK · `last_number` int unsigned

**order_items** — `id` · `order_id` FK cascade · `product_id` bigint nullable FK nullOnDelete · `sku` string(64) nullable · `supplier_code` string(64) nullable · `name` string(255) · `unit` string(16) · `availability` string(16) (снимок на момент заказа) · `qty` int unsigned · `price` decimal(12,2) · `sum` decimal(12,2) · timestamps

**order_status_logs** — `id` · `order_id` FK cascade · `from_status` string(16) nullable · `to_status` string(16) · `user_id` bigint nullable FK nullOnDelete · `comment` text nullable · `created_at` timestamp

**leads**
`id` · `type` enum(callback,question,price_request,availability_request,analog_request,one_click,not_found) · `name` string(150) nullable · `phone` string(20) · `email` string(150) nullable · `product_id` bigint nullable FK nullOnDelete · `message` text nullable · `status` enum(new,in_work,done) default new · `manager_id` bigint nullable FK users nullOnDelete · `utm` json nullable · `ip` string(45) nullable · timestamps

**favorites** — `id` · `user_id` bigint nullable FK cascade · `session_id` string(100) nullable · `product_id` FK cascade · timestamps · unique(`user_id`,`product_id`) · unique(`session_id`,`product_id`). Гостевое избранное объединяется при входе так же, как корзина.

**compare_items** — `id` · `user_id` FK cascade · `product_id` FK cascade · timestamps · unique(`user_id`,`product_id`). Сравнение вошедшего клиента (§8.5), не больше 4 моделей; у гостя список живёт в сессии и переносится сюда при входе.

## 5.5 Контент и настройки

**pages** — `id` · `slug` string(160) unique · `title` string(255) · `content` longtext (Markdown; HTML внутри текста при выводе вырезается) · `meta_title` string(255) nullable · `meta_description` string(500) nullable · `is_active` bool default false · `sort` smallint default 0 · timestamps

Обязательные страницы: `dostavka`, `oplata`, `garantiya`, `optovikam`, `o-kompanii`, `kontakty`, `politika-konfidencialnosti`, `soglasie-na-obrabotku-personalnyh-dannyh`, `polzovatelskoe-soglashenie`.

**redirects** — `id` · `from_path` string(500) unique · `to_path` string(500) · `status_code` smallint default 301 · `hits` int unsigned default 0 · timestamps

**settings** — `id` · `key` string(100) unique · `value` json nullable (null — значение ещё не задано) · timestamps

| Ключ | Значение по умолчанию | Смысл |
|---|---|---|
| `site.name` | — (от заказчика) | название магазина |
| `contacts.phones`, `contacts.email`, `contacts.address`, `contacts.schedule`, `contacts.socials` | — | контакты |
| `seller.requisites` | — | реквизиты продавца для подвала и счетов |
| `seller.vat_mode` | — (от заказчика) | `with_vat` / `without_vat`: подпись к ценам |
| `pickup.address` | — | адрес самовывоза |
| `delivery.free_city_from` | — | порог бесплатной доставки по городу, ₽ |
| `catalog.low_stock_threshold` | 3 | порог «мало» для числовых остатков |
| `catalog.discontinued_after_runs` | 3 | через сколько прогонов пропавший товар снимается |
| `catalog.local_warehouse_name` | Симферополь | склад для ленты на главной |
| `catalog.local_strip_min_products` | 12 | минимум товаров, чтобы показать ленту |
| `pricing.max_discount_without_purchase` | 0 | предел скидки группы, пока закупка неизвестна, % |
| `pricing.min_margin_percent` | — (от заказчика) | минимальная наценка над закупкой, % |
| `pricing.show_tier_name` | false | показывать ли клиенту название ценовой группы |
| `notify.telegram_include_contacts` | false | имя и телефон клиента в Telegram (§15) |
| `payments.online_enabled` | false | радиокнопка онлайн-оплаты |
| `analytics.metrika_id` | — | счётчик Метрики |
| `seo.product_title_template`, `seo.category_title_template` | §14 | шаблоны мета-тегов |

Токен и `chat_id` Telegram хранятся только в `.env`.
