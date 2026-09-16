# Техническое задание: интернет-магазин оборудования и расходников HoReCa

**Версия:** 1.1
**Дата:** 16.09.2026
**Заказчик:** Алексей (Симферополь)
**Исполнитель:** Claude Code
**Кодовое имя проекта:** `horeca-shop`

---

## Что изменилось в версии 1.1

- **Стек** обновлён до поддерживаемых версий: PHP 8.4, Laravel 13, Livewire 4, Filament 5, Tailwind 4, MariaDB 11.8 LTS, Node 24 LTS. Убраны пакеты, которые дублируют встроенные возможности (§3).
- **Импорт** переписан под будущий API поставщика: единый внутренний формат данных, XML — временный адаптер. Убраны мастер сопоставления колонок, XLS/XLSX-профили и извлечение характеристик регулярками (§6).
- **Учтён ответ Росхолода от 16.09.2026:** фото, РРЦ, числовые остатки, дерево категорий и характеристики будут в API (§1, §6, §7).
- **Факты о данных** взяты из разбора реальных файлов 16.09.2026, подробности — в `docs/supplier-data-2026-09-16.md` (§6.1).
- **Наличие:** одна модель статусов и одна таблица отображения (§6.5). «Под заказ» — основное состояние каталога, такой товар можно положить в корзину.
- **Цены:** розница = РРЦ вместо «закупка + наценка»; оптовая скидка ограничена, пока закупочная цена неизвестна (§7).
- **Исправлены противоречия v1.0:** несуществующее поле `stock`, расходящиеся статусы наличия, «более 10 шт», включённый по умолчанию фильтр «Только в наличии», теги кэша без Redis, минимальная длина поиска, одно имя для двух разных «быстрых заказов», перевёрнутое правило про хардкод строк, циклические связи пользователей и компаний, дублирование ролей, `voltage enum`, размеры изображений, маска телефона.
- **Добавлено:** защита от повторной отправки заказа, безопасный счётчик номеров, раздельные очереди, защита каталога от битого файла, требования 152-ФЗ, режим НДС, варианты локальной среды.
- Удалены ссылки на сторонние проекты. Версия 1.0 — в `docs/archive/v1.0/`.

---

## 0. Как работать с этим документом

- Документ — источник истины. Правила разработки — в `CLAUDE.md`, дизайн — в `DESIGN-BRIEF-horeca-shop.md` и утверждённых макетах, факты о данных поставщика — в `docs/`.
- Перед спринтом читаются: раздел спринта в §18, §5 «Модель данных» и все разделы, на которые ссылается спринт. После спринта — сверка с §19.
- Файл большой: читать по разделам (заголовки `## N.`).
- ТЗ не меняется молча. Если требование расходится с реальностью, исполнитель останавливается, описывает расхождение и предлагает правку. Принятая правка попадает в «Что изменилось» с новым номером версии.

---

## 1. Бизнес-контекст и цель

Продаём оборудование, инвентарь и расходники для сегмента **HoReCa** (рестораны, кафе, бары, отели, столовые, кондитерские, пищевые производства) в Крыму и по РФ.

**Поставщик — Росхолод.**
- **Сейчас:** публичные XML-выгрузки — каталог и остатки. Около 15 000 позиций, фото нет, остатки текстом («много», «несколько»), характеристик нет.
- **Скоро:** API с фото, РРЦ, числовыми остатками, деревом категорий и характеристиками «ключ: значение». Дилерские цены поставщик прорабатывает. Документацию обещал «в ближайшее время».
- **Следствие:** архитектура импорта не зависит от источника. API станет основным источником, XML останется резервом. Переключение не меняет витрину и схему БД.

**Что это значит для витрины на старте (данные 16.09.2026):**
- в наличии хотя бы на одном складе — 12% товаров; остальные — «Под заказ»;
- у 7% товаров цена поставщика 0 — «Цена по запросу»;
- фото нет ни у одного товара;
- названия длинные и технические, у 12% сокращения («стол охл.»).

**Две аудитории на одной витрине:**

| | B2C (розница) | B2B (опт) |
|---|---|---|
| Кто | частник, маленькая точка, открывающееся кафе | ресторан, отель, сеть, снабженец |
| Цена | розничная, видна всем | оптовая по ценовой группе, после одобрения компании менеджером |
| Итог сессии | заявка | заявка, счёт по реквизитам компании, заказ списком |

**Оплата онлайн в MVP не подключается.** Воронка заканчивается **заявкой** (заказ в статусе «Новая»), уведомлением менеджеру и дальнейшей ручной работой. Схема заказа сразу рассчитана на подключение ЮKassa одним сервисом (§10.5).

**Цель релиза:** работающая витрина с каталогом поставщика, актуальным наличием, поиском, фильтрами, корзиной, оформлением заявки, B2B-кабинетом и админкой, развёрнутая на VPS с HestiaCP.

---

## 2. Роли и пользовательские сценарии

### 2.1 Роли

| Роль | Код `users.role` | Права |
|---|---|---|
| Гость | — | каталог, поиск, корзина, избранное, оформление заявки без регистрации |
| Клиент | `customer` | то же + история заявок. Если компания клиента одобрена — оптовые цены, заказ списком, повтор заказа, прайс |
| Менеджер | `manager` | Filament: заказы, лиды, клиенты и компании, товары (без удаления), категории, соответствия поставщика, запуск импорта |
| Администратор | `admin` | всё, включая настройки, цены и ценовые группы, пользователей, профили импорта, удаление |

«Оптовик» — не отдельная роль, а клиент, чья компания в статусе `approved`.

### 2.2 Ключевые сценарии

Каждый сценарий покрывается feature-тестом.

1. **Гость → заявка.** Главная → «Холодильное оборудование» → подкатегория → фильтры по бренду и характеристике (в тесте — «Мощность, кВт» 4–6 из демо-данных; на реальных данных характеристики появятся с API) → карточка → «Добавить в корзину» → корзина → оформление (имя, телефон, город, комментарий) → согласие на обработку ПДн → «Отправить заявку» → страница «Спасибо» с номером → уведомление менеджеру.
2. **Поиск по артикулу.** В шапке вводится артикул, код 1С или модель → выдача за ≤ 300 мс → карточка.
3. **B2B-регистрация.** «Стать оптовым клиентом» → форма (ИНН, юр. название, сегмент, контакты, пароль) → статус «На проверке» → менеджер в Filament назначает ценовую группу и одобряет → письмо клиенту → цены на витрине пересчитались.
4. **Заказ списком.** B2B-кабинет → «Заказ списком» → вставка строк `артикул;количество` или загрузка XLSX → превью по каждой строке: найден, не найден, несколько совпадений (выбор), цена по запросу (не добавляется) → «Добавить в корзину».
5. **Повтор заказа.** ЛК → заявка → «Повторить заказ» → корзина заполняется по текущим ценам; позиции, которые нельзя купить, перечислены отдельно.
6. **Импорт.** Админ → «Импорт» → профиль «Росхолод: каталог (XML)» → «Запустить» → фоновая задача → отчёт: создано, обновлено, без изменений, сняты с производства, ошибки + скачивание лога.
7. **Товар без остатков.** После импорта остатков на всех складах 0 → статус «Под заказ», кнопка «Добавить в корзину» остаётся, рядом подпись «Срок поставки уточнит менеджер» и ссылка «Узнать срок»; в сортировке товар ниже позиций в наличии.
8. **Цена по запросу.** Цена поставщика 0 → вместо цены «Цена по запросу», вместо корзины кнопка «Запросить цену» → лид.

---

## 3. Стек и обоснование

**Выбор: Laravel 13 + Livewire 4 + Filament 5 + Tailwind CSS 4 + MariaDB 11.8 + Redis.**

- **Серверный рендеринг (Blade + Livewire).** Каталог живёт на органическом поиске («пароконвектомат купить Симферополь»). Страницы отдаются готовым HTML без Node на проде. На HestiaCP это обычный домен на PHP-FPM.
- **Livewire 4** — фильтры, корзина, мгновенный поиск без отдельного REST API и второго фронтенда. Alpine.js поставляется в составе Livewire, отдельно не ставится.
- **Filament 5** — админка B2B-магазина (CRUD, фильтры, массовые действия, экспорт) и встроенная двухфакторная аутентификация.
- **MariaDB 11.8 LTS** ставится HestiaCP по умолчанию: бэкапы, пользователи и phpMyAdmin из панели. Код обязан работать и на MySQL 8.4 LTS, поэтому: без FULLTEXT-парсера `ngram` и без JSON-функций, специфичных для одной СУБД, в запросах. SQLite запрещён во всех окружениях, включая тесты.
- **Redis** — кэш, сессии, очереди. Если Redis недоступен, драйверы `database` обязаны работать без изменений кода (§14, §17.4).
- **Meilisearch в MVP не ставим.** Около 15 000 товаров ищутся по нормализованной колонке за миллисекунды (§8.4). Интерфейс `SearchEngineInterface` оставлен для замены.

**Версии** (проверены по packagist, npm и endoflife.date 16.09.2026; фиксируются в `composer.json`, `package.json` и lock-файлах):

```
PHP 8.4                           поддержка до 31.12.2026, безопасность до 31.12.2028
laravel/framework ^13.0           исправления до 30.09.2027, безопасность до 17.03.2028
livewire/livewire ^4.4
filament/filament ^5.8
openspout/openspout ^4.23         чтение и запись XLSX потоком; та же версия, что требует Filament
spatie/laravel-medialibrary ^11.23
spatie/laravel-sitemap ^8.2
laravel/pint ^1.32                (dev)
pestphp/pest ^5.2                 (dev)
pestphp/pest-plugin-laravel ^5.0  (dev)
barryvdh/laravel-debugbar ^4.4    (dev)

MariaDB 11.8 LTS (до 04.06.2028) или MySQL 8.4 LTS
Redis 7+
Node 24 LTS (только сборка), vite ^8.3, laravel-vite-plugin ^3.2
tailwindcss ^4.3, @tailwindcss/vite ^4.3
@fontsource-variable/golos-text ^5.3, @fontsource-variable/inter ^5.3
```

**Не используем** (были в v1.0):
- `phpoffice/phpspreadsheet`, `maatwebsite/excel` — заменены OpenSpout;
- `spatie/laravel-permission` — четыре фиксированные роли закрываются enum и Policies;
- `intervention/image` — medialibrary работает через `spatie/image`;
- `laravel/fortify` — 2FA встроена в Filament;
- `filament-nestedset` — для двухуровневого дерева не нужен.

Любой пакет вне этого списка — только после согласования (`CLAUDE.md`).

---

## 4. Архитектура и структура каталогов

```
app/
  Actions/
    Cart/          AddToCart, UpdateCartItemQty, RemoveCartItem, RestoreCartItem, MergeGuestCart
    Orders/        CreateOrderFromCart, RepeatOrder, ChangeOrderStatus, GenerateOrderNumber
    Pricing/       CalculateCartTotals
    Leads/         CreateLead
    Companies/     RegisterWholesaleCompany, ApproveCompany, UpdateCompanyDetails
    BulkOrder/     ParseBulkOrderList, AddBulkOrderToCart
    Favorites/     ToggleFavorite, MergeGuestFavorites
  Console/Commands/
    SupplierImportCommand       php artisan supplier:import {profile} {--dry-run} {--force}
    GenerateSitemapCommand, RecalculatePopularityCommand, PruneCartsCommand,
    SendImportDigestCommand, MailTestCommand, AnonymizeUserCommand
  Enums/
    UserRole, CompanyStatus, CompanySegment, Availability, WarehouseStockStatus,
    OrderStatus, OrderType, DeliveryMethod, PaymentMethod, LeadType, LeadStatus,
    PriceKind, ImportRunStatus, ImportTrigger, ImportEntity, SupplierRefEntity,
    AttributeType, AttributeValueSource
  Events/          OrderCreated, CompanyApproved, ImportFinished, ImportFailed
  Filament/
    Resources/     Product, Category, Brand, Collection, Order, Lead, User, Company, PriceTier,
                   Supplier, SupplierRef, Warehouse, ImportProfile, ImportRun, Attribute, Page, Redirect
    Pages/         Dashboard, Settings
    Widgets/       OrdersStats, NewOrders, NewLeads, PendingCompanies, LatestImports, UnmappedSupplierRefs
  Http/
    Controllers/   Home, Catalog, Product, Brand, Search, Cart, Checkout, Lead, Wholesale,
                   Account, AccountOrder, AccountCompany, PriceList, Favorite, Page, Sitemap, Robots
    Middleware/    EnsureWholesaleApproved, TrackUtm, SecurityHeaders
    Requests/      CheckoutRequest, LeadRequest, WholesaleRegisterRequest, CompanyUpdateRequest, BulkOrderRequest
  Jobs/            RunSupplierImport, DownloadProductImages, SendTelegramMessage
  Listeners/       SendOrderNotifications, SendCompanyApprovedNotification, SendImportFailedAlert
  Livewire/
    Catalog/       ProductFilter, ProductGrid
    Cart/          CartCounter, CartPage, AddToCartButton
    Search/        HeaderSearch
    Account/       BulkOrder
    Leads/         LeadForm
  Mail/, Notifications/
  Models/          см. §5
  Observers/       ProductObserver (locked_fields, редиректы при смене slug), CatalogCacheObserver
  Policies/        OrderPolicy, CompanyPolicy, CartPolicy, FavoritePolicy
  Services/
    Supplier/
      Contracts/   SupplierFeedInterface
      Data/        FeedCapabilities, FetchedSource, SupplierCategory, SupplierProduct,
                   SupplierStock, SupplierImage, SupplierAttribute
      Sources/Rosholod/  RosholodCatalogXmlSource, RosholodStockXmlSource, RosholodApiSource (спринт 7)
      Xml/         Cp1251XmlReader
      Import/      ImportRunner, SourceRegistry, StagingWriter, ThresholdGuard, CategorySync,
                   BrandSync, ProductUpserter, StockUpserter, AvailabilityCalculator,
                   AttributeValueParser, StockValueMapper
    Catalog/       CatalogQuery, CatalogCache
    Pricing/       PriceResolver, Price
    Search/        SearchEngineInterface, DatabaseSearchEngine, QueryNormalizer
    Notify/        TelegramNotifier
    Seo/           MetaBuilder, SchemaOrg
    Payments/      PaymentGatewayInterface
  Support/         Money, Percent, Slugger, Phone, Typography
config/suppliers/rosholod.php     карта тегов XML, адреса выгрузок, карта значений остатков
resources/
  css/app.css                     Tailwind 4 + токены в @theme
  js/app.js
  views/
    components/                   Blade-компоненты дизайн-системы (x-product-card, x-price, x-availability, x-button…)
    layouts/, home/, catalog/, product/, brands/, search/, cart/, checkout/, wholesale/,
    account/, pages/, errors/, emails/
lang/ru/
routes/web.php, routes/console.php
database/migrations, database/seeders, database/factories
tests/Feature, tests/Unit, tests/Fixtures/rosholod/
storage/app/imports/{supplier}/
docs/                             отчёты по данным, архив версий документов
```

**Слои.** Контроллер, Livewire-компонент или Filament-ресурс → Action или Service → модель. Бизнес-логика в контроллерах, Livewire и Filament запрещена. Выборки витрины — через `CatalogQuery`.

---

## 5. Модель данных

MariaDB 11.8 / MySQL 8.4, `utf8mb4_unicode_ci`, InnoDB. Типы указаны точно, миграции пишутся ровно по ним. Внешние ключи между `users` и `companies` добавляются отдельной миграцией после создания обеих таблиц. Стандартные таблицы Laravel (`sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) и таблица `media` medialibrary создаются штатными миграциями.

### 5.1 Пользователи и компании

**users**
`id` · `name` string(150) · `email` string(150) unique · `phone` string(20) nullable index · `password` string · `role` enum(customer,manager,admin) default customer · `company_id` bigint nullable FK companies nullOnDelete · `is_active` bool default true · `email_verified_at` timestamp nullable · `last_login_at` timestamp nullable · поля встроенной 2FA Filament (секрет и коды восстановления; имена колонок — по документации Filament 5 при установке) · `remember_token` · timestamps

**companies**
`id` · `legal_name` string(255) · `brand_name` string(255) nullable (вывеска заведения) · `inn` string(12) index · `kpp` string(9) nullable · `ogrn` string(15) nullable · `legal_address` string(500) nullable · `delivery_address` string(500) nullable · `city` string(150) nullable · `bank_name` string(255) nullable · `bik` string(9) nullable · `account` string(20) nullable · `corr_account` string(20) nullable · `contact_person` string(150) · `phone` string(20) · `email` string(150) · `segment` enum(restaurant,cafe,bar,hotel,canteen,bakery,production,retail_chain,other) · `status` enum(pending,approved,rejected,blocked) default pending · `price_tier_id` bigint nullable FK price_tiers nullOnDelete · `manager_comment` text nullable · `approved_at` timestamp nullable · `approved_by` bigint nullable FK users nullOnDelete · timestamps

Пользователь относится к компании через `users.company_id`. В MVP у компании один пользователь — тот, кто подал заявку.

**price_tiers**
`id` · `name` string(100) · `slug` string(100) unique · `discount_percent` decimal(5,2) default 0 · `min_order_amount` decimal(12,2) default 0 · `is_default` bool default false · `sort` smallint default 0 · timestamps

### 5.2 Каталог

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

**Изображения** — medialibrary, коллекция `images` у `Product`. Конверсии в WebP: `thumb` 160×160, `card` 600×600, `full` 1200×1200 (вписывание без обрезки). Пользовательские свойства медиа: `source` (supplier|manual), `source_url`, `source_hash`, `sort`.

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

**collections** — подборки «Соберём кухню под задачу»
`id` · `name` string(150) · `slug` string(160) unique · `description` text nullable · `icon` string(64) nullable · `is_active` bool default false · `sort` smallint default 0 · timestamps

**collection_product**
`collection_id` FK cascade · `product_id` FK cascade · `sort` smallint default 0 · PK(`collection_id`,`product_id`)

### 5.3 Импорт

**import_profiles**
`id` · `supplier_id` FK cascade · `name` string(150) · `source` string(64) — ключ адаптера (`rosholod.catalog_xml`, `rosholod.stock_xml`, `rosholod.api`) · `url` string(500) nullable · `schedule` string(64) nullable (cron-выражение) · `settings` json nullable (пороги §6.3) · `is_active` bool default false · `last_etag` string(191) nullable · `last_modified` string(64) nullable · timestamps

**import_runs**
`id` · `import_profile_id` FK cascade · `user_id` bigint nullable FK users nullOnDelete · `trigger` enum(schedule,manual,cli) · `status` enum(queued,running,success,skipped,failed) · `is_dry_run` bool default false · `file_path` string(500) nullable · `source_version` string(191) nullable (ETag или атрибут `date`) · `rows_total`, `created`, `updated`, `unchanged`, `discontinued`, `errors` int unsigned default 0 · `log` json nullable (первые 500 проблем) · `log_file` string(500) nullable · `error_message` text nullable · `started_at`, `finished_at` timestamp nullable · timestamps

**import_rows** — промежуточная таблица, очищается после прогона
`id` · `import_run_id` FK cascade · `entity` enum(category,product,stock) · `external_id` string(191) · `payload` json · `hash` char(32) · `is_valid` bool · `error` string(500) nullable
Индекс: (`import_run_id`,`entity`).

### 5.4 Продажи

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
timestamps
Индексы: `status`, `created_at`, `user_id`.

**order_counters** — `date` date PK · `last_number` int unsigned

**order_items** — `id` · `order_id` FK cascade · `product_id` bigint nullable FK nullOnDelete · `sku` string(64) nullable · `supplier_code` string(64) nullable · `name` string(255) · `unit` string(16) · `availability` string(16) (снимок на момент заказа) · `qty` int unsigned · `price` decimal(12,2) · `sum` decimal(12,2) · timestamps

**order_status_logs** — `id` · `order_id` FK cascade · `from_status` string(16) nullable · `to_status` string(16) · `user_id` bigint nullable FK nullOnDelete · `comment` text nullable · `created_at` timestamp

**leads**
`id` · `type` enum(callback,question,price_request,availability_request,analog_request,one_click,not_found) · `name` string(150) nullable · `phone` string(20) · `email` string(150) nullable · `product_id` bigint nullable FK nullOnDelete · `message` text nullable · `status` enum(new,in_work,done) default new · `manager_id` bigint nullable FK users nullOnDelete · `utm` json nullable · `ip` string(45) nullable · timestamps

**favorites** — `id` · `user_id` bigint nullable FK cascade · `session_id` string(100) nullable · `product_id` FK cascade · timestamps · unique(`user_id`,`product_id`) · unique(`session_id`,`product_id`). Гостевое избранное объединяется при входе так же, как корзина.

### 5.5 Контент и настройки

**pages** — `id` · `slug` string(160) unique · `title` string(255) · `content` longtext · `meta_title` string(255) nullable · `meta_description` string(500) nullable · `is_active` bool default false · `sort` smallint default 0 · timestamps

Обязательные страницы: `dostavka`, `oplata`, `garantiya`, `optovikam`, `o-kompanii`, `kontakty`, `politika-konfidencialnosti`, `soglasie-na-obrabotku-personalnyh-dannyh`, `polzovatelskoe-soglashenie`.

**redirects** — `id` · `from_path` string(500) unique · `to_path` string(500) · `status_code` smallint default 301 · `hits` int unsigned default 0 · timestamps

**settings** — `id` · `key` string(100) unique · `value` json · timestamps

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

---

## 6. Импорт от поставщика

### 6.1 Источники и факты о данных

Полный разбор — `docs/supplier-data-2026-09-16.md`.

| Источник | Что даёт | Статус |
|---|---|---|
| `https://rosholod.org/price-lists/Catalog.xml` | дерево категорий с GUID; товары: наименование, модель, артикул, код 1С, описание, бренд, цена | используется |
| `https://rosholod.org/price-lists/OstatkiYandex.xml` | остатки по складам текстом, единица на складе, цена | используется |
| API Росхолода | фото, РРЦ (позже дилерская цена), числовые остатки, дерево, характеристики «ключ: значение», справочники брендов, стран и типов по GUID | ждём документацию; спринт 7; станет основным |
| `Ostatki.xml`, XLS/XLSX-прайсы | дублируют данные выше | не используются |

**Факты, которые определяют реализацию:**

1. **Кодировка windows-1251.** Чтение через `XMLReader` с перекодировкой в UTF-8. Если в названиях появились `Ð` или `?` вместо кириллицы, прогон падает, в БД ничего не пишется.
2. **Разные структуры.** В каталоге карточка — `<ДетальнаяЗапись>` с кириллическими тегами внутри YML-обёртки; в остатках — `<item>` с латинскими тегами и пространством имён. Узлы сопоставляются по локальным именам. Карта тегов хранится в `config/suppliers/rosholod.php`, а не в интерфейсе админки.
3. **Ключ — GUID `ID`.** Он одинаков в обоих файлах. Код 1С (`Код`/`iditem`) заполнен всегда и уникален. Артикул пуст у 22% карточек, 93 значения повторяются.
4. **Бренд** — `ТорговаяМарка` / `trademark` (поле `Производитель` пустое). Названия с «хвостами» («Rosso (Китай)», «Марихолодмаш (тепловое)») и мусорные («Прочее оборудование (не ассортимент Росхолода)») сводятся вручную через `supplier_refs`.
5. **Категория товара** указана именем подкатегории (`categoryId`), а не GUID. На 16.09.2026 все имена однозначны. При неоднозначности берётся первая категория с таким именем, конфликт пишется в лог прогона. В дереве 25 корней, два уровня, есть корни без товаров («Услуга», «Товар», «материалы») и мусор в именах («Прилавок нейтральный1»). Код поддерживает любую глубину.
6. **Цены.** В каталоге разряды через неразрывный пробел, дробная часть через запятую («48 605,8»). В остатках — десятичная точка («72581.5»). На пересечении файлов цены совпадают. Цена 0 — у 6,8% карточек каталога, среди них снятые модели и опции.
7. **Тип цены** в XML поставщик не подтвердил. Пока она считается **РРЦ** (`suppliers.price_kind = rrp`): выгрузки публичные, а API первой версии, по словам поставщика, отдаёт РРЦ.
8. **Названия** до 200 символов, у 12,6% сокращения, у 70 обрезаны на 100 символах. Описания пусты у 12,6% и обрезаны на 500 символах у 1 750 карточек. Импорт только чистит пробелы, тексты не переписывает.
9. **Остатки** в XML — только `0`, `несколько`, `в наличии`, `много`. Запись с пустым именем склада означает «остатков нет». Складов 20, в том числе Симферополь.
10. **Покрытие остатками.** 60% товаров каталога нет в файле остатков, у 27% везде 0, в наличии 12%.
11. **4 596 позиций есть только в файле остатков** (без категории). Они не публикуются, их число пишется в лог прогона.
12. **Единица измерения** указана только на уровне склада: шт у 99% записей, реже кг, т, компл, м, пог. м, м3.
13. **Фото и характеристик** в XML нет. Регулярные выражения по названиям дают мощность у 2% товаров и габариты у 14%, поэтому извлечение не делается: характеристики придут из API.
14. **HTTP.** Файлы отдаются с `ETag`, `Last-Modified` и `Accept-Ranges`. Выгрузку перезаливают несколько раз в сутки, время формирования — в атрибуте `yml_catalog@date`.

### 6.2 Внутренний формат и контракт источника

Адаптер источника превращает данные поставщика во внутренние DTO. Всё, что после адаптера, работает только с DTO и не знает, XML это или API.

```php
interface SupplierFeedInterface
{
    /** Какие сущности отдаёт источник, какими полями товара владеет, полный ли это снимок. */
    public function capabilities(): FeedCapabilities;

    /** Получает данные. null — источник не изменился (HTTP 304 по ETag) и $force = false. */
    public function fetch(ImportProfile $profile, bool $force = false): ?FetchedSource;

    /**
     * Потоково читает полученные данные.
     *
     * @return iterable<SupplierCategory|SupplierProduct|SupplierStock>
     */
    public function read(FetchedSource $source): iterable;
}
```

**DTO** (readonly-классы в `Services/Supplier/Data`):
- `SupplierCategory`: `externalId`, `parentExternalId` (nullable), `name`.
- `SupplierProduct`: `externalId`, `supplierCode`, `sku`, `model`, `name`, `description`, `brandKey`, `brandName`, `categoryKey` (GUID или имя, если GUID нет), `rrpPrice` и `purchasePrice` (`Money` или null), `unit`, `images` (список `SupplierImage { url, sort }`), `attributes` (список `SupplierAttribute { key, rawValue }`), `country`.
- `SupplierStock`: `productExternalId`, `warehouseKey`, `warehouseName`, `quantity` (строка-десятичное или null), `rawValue`, `unit`.
- `FeedCapabilities`: `entities` (список `ImportEntity`), `ownedProductFields` (список полей товара), `isFullSnapshot` (содержит ли источник все записи своего типа).

**Профили и владение полями.** Каждое поле товара в каждый момент принадлежит одному активному профилю. При включении профиля в админке пересечение владения проверяется, и активация с конфликтом запрещается.

| Профиль | `source` | Сущности | Владеет полями | Полный снимок | Расписание |
|---|---|---|---|---|---|
| Росхолод: каталог (XML) | `rosholod.catalog_xml` | категории, товары | `name`, `model`, `sku`, `supplier_code`, `description`, `brand_id`, `category_id`, `rrp_price` | да | каждый час, 05:00–23:00 МСК |
| Росхолод: остатки (XML) | `rosholod.stock_xml` | остатки | остатки, `unit` | да (по остаткам) | каждые 30 минут, 06:00–23:00 МСК |
| Росхолод: API | `rosholod.api` | всё, включая фото и характеристики | всё перечисленное + `purchase_price`, фото, характеристики, `weight_kg` и габариты | по документации | по регламенту поставщика |

После подключения API XML-профили выключаются, но остаются рабочими как резерв.

### 6.3 Алгоритм прогона (`ImportRunner`)

1. **Замок на поставщика:** `Cache::lock('import:supplier:'.$supplierId, 7200)`. Два прогона одного поставщика одновременно не идут. Задача из расписания при занятом замке возвращается в очередь с задержкой 5 минут; ручной запуск сообщает «Импорт уже идёт».
2. Создаётся `import_run` со статусом `running`.
3. **Получение.** `fetch()` делает условный GET по сохранённым `ETag` и `Last-Modified`. Ответ 304 → статус `skipped`, прогон завершён, уведомлений нет. `--force` игнорирует проверку. Файл сохраняется в `storage/app/imports/{supplier}/{Y-m-d_His}_{source}.xml`, по каждому профилю хранятся 30 последних.
4. **Чтение в staging.** `read()` → нормализация → запись в `import_rows` пачками по 500 с хешем нормализованной записи. Записи без `externalId`, без имени или с отрицательной ценой получают `is_valid = false` и текст ошибки. Ошибка разбора XML или проверки кодировки → статус `failed`, каталог не меняется.
5. **Пороги** (`ThresholdGuard`, значения в `import_profiles.settings`):
   - невалидных записей больше 30% → `failed`;
   - для полного снимка: валидных записей меньше 80% от последнего успешного прогона этого профиля → `failed` с текстом «Подозрительно мало записей: N против M»;
   - при срабатывании любого порога каталог не меняется, уходит тревога в Telegram и на почту (§13).
6. **Применение** из staging пачками по 500, каждая пачка в транзакции:
   - **категории** → `supplier_refs` (category). Для новой категории создаётся витринная копия с `is_active = false` под сопоставленным родителем, связь пишется в `local_id`. У существующей обновляется только имя в `supplier_refs`: витринные названия и дерево — зона менеджера;
   - **бренды** → `supplier_refs` (brand) по GUID или нормализованному имени. Новый бренд создаётся с очищенным названием;
   - **товары** ищутся по (`supplier_id`, `external_id`). Новый товар получает все поля профиля, `slug` (§6.6) и `is_visible = true`. У существующего пишутся только поля профиля, кроме `locked_fields`. При совпадении `source_hash` меняется только `last_synced_at` (массово), `unchanged++`. Категория и бренд берутся через `supplier_refs.local_id`;
   - **остатки** → склад через `supplier_refs` (warehouse); новый склад создаётся автоматически. Для товаров пачки `product_stocks` заменяются набором из снимка. Записи с пустым именем склада не сохраняются.
7. **Завершение полного снимка** (только если прогон успешен):
   - **каталог:** у товаров поставщика, которых нет в снимке, `missing_runs++`, у встреченных `missing_runs = 0`. При `missing_runs ≥ catalog.discontinued_after_runs` товар получает `availability = discontinued`. Товары не удаляются;
   - **остатки:** у товаров поставщика, которых нет в снимке, удаляются все `product_stocks`.
8. **Пересчёт** для затронутых товаров: `availability` и `availability_rank` (§6.5), `retail_price` (§7), `search_text` (§8.4). Затем `categories.products_count` и версия кэша каталога (§14).
9. **Фото** (только API): задача `DownloadProductImages` ставится для товаров, у которых изменился набор фото: таймаут 15 с, до 10 МБ, проверка реального MIME, конверсии §5.2. Ошибка скачивания пишется в лог и не ломает импорт. Ручные фото (`source = manual`) импорт не удаляет.
10. **Финал:** статистика записывается в `import_run`, строки staging удаляются, статус `success`, событие `ImportFinished`.

### 6.4 Режимы запуска

- **Filament:** «Запустить» и «Запустить без проверки изменений» (`--force`). Задача уходит в очередь `imports`, страница прогона обновляет прогресс опросом.
- **CLI:** `php artisan supplier:import {profile} {--dry-run} {--force}`. `--dry-run` выполняет шаги 1–5 и выводит отчёт «создастся / обновится / снимется», не меняя каталог.
- **Планировщик** запускает активные профили по `schedule`.
- Очередь `imports` обслуживает отдельный воркер с одним процессом (§17.4). Уведомления о заказах идут через `default` и импортом не задерживаются.
- Задача импорта: таймаут 3600 с, память ≤ 256 МБ на 15 000 товаров.
- Первые 500 проблем хранятся в `import_runs.log`, остальные — в `storage/logs/imports/{run_id}.log`, файл скачивается из админки.

### 6.5 Наличие

**Статус на складе** (`product_stocks.status`):

| Источник | Значение | Статус |
|---|---|---|
| XML | `много`, `в наличии` | `in_stock` |
| XML | `несколько` | `low` |
| XML | `0` | `out` |
| XML | любое другое | `out` + предупреждение в лог прогона; карта значений расширяется в `config/suppliers/rosholod.php` |
| API | `quantity ≥ catalog.low_stock_threshold` | `in_stock` |
| API | `0 < quantity < catalog.low_stock_threshold` | `low` |
| API | `quantity ≤ 0` | `out` |

**Статус товара** (`products.availability`) считает `AvailabilityCalculator` по складам с `is_visible = true` — в порядке приоритета:
1. `discontinued` — товар пропал из полного снимка каталога (§6.3, шаг 7) или API пометил его снятым;
2. `in_stock` — хотя бы на одном складе `in_stock`;
3. `low` — `in_stock` нет, но есть `low`;
4. `incoming` — остатков нет, но источник сообщает о поступлении (только если API это отдаёт);
5. `on_order` — всё остальное, включая товары без записей об остатках.

**Отображение:**

| `availability` | Бейдж | Кнопка в листинге | Кнопка в карточке товара | `availability_rank` | schema.org |
|---|---|---|---|---|---|
| `in_stock` | «В наличии» | «В корзину» | «Добавить в корзину» | 1 | `InStock` |
| `low` | «В наличии: мало» | «В корзину» | «Добавить в корзину» | 1 | `LimitedAvailability` |
| `incoming` | «Ожидается» | «В корзину» | «Добавить в корзину» | 2 | `BackOrder` |
| `on_order` | «Под заказ» | «В корзину» | «Добавить в корзину» + подпись «Срок поставки уточнит менеджер» и ссылка «Узнать срок» (лид `availability_request`) | 3 | `MadeToOrder` |
| `discontinued` | «Снят с производства» | товар не выводится в листингах и поиске | «Подобрать аналог» (лид `analog_request`) | 4 | `Discontinued` |

**Цена по запросу** (`retail_price = null`) не зависит от наличия: вместо цены «Цена по запросу», вместо корзины «Запросить цену» (лид `price_request`), разметка `Offer` не выводится.

**Склады в карточке товара:** список видимых складов со статусом словами и сроком до Симферополя, если он заполнен, например «Волжск — в наличии, 5–8 дней». Количество штук клиенту не показывается никогда. Числовые остатки из API используются только для предупреждения в корзине: «Нужно больше, чем есть на складах — срок уточнит менеджер».

### 6.6 Защита ручных правок и адресов

- **`locked_fields`.** При сохранении товара в Filament изменённые поля, которыми владеет импорт, добавляются в `locked_fields`. В форме у таких полей значок замка и действие «Вернуть значение поставщика» — поле удаляется из `locked_fields`, значение восстановится следующим прогоном.
- **`slug`** создаётся при создании товара из названия: `Str::slug`, до 120 символов; при коллизии добавляется суффикс из кода 1С. Импорт `slug` не меняет. Ручная смена `slug` автоматически создаёт 301-редирект со старого адреса.
- **Витринные категории** (названия, дерево, активность, SEO) меняет только менеджер; импорт обновляет `supplier_refs`.
- **Ручные фото** импорт не удаляет и не переставляет.

---

## 7. Ценообразование

```
Цена поставщика: rrp_price (РРЦ) и purchase_price (дилерская, когда появится)
   ↓ suppliers.price_kind
       rrp    → retail = rrp_price, округление вверх до retail_round_to
       dealer → retail = purchase_price × (1 + markup_percent/100), округление вверх до retail_round_to
   ↓ исходной цены нет или она 0 → retail_price = null → «Цена по запросу»
Розничная цена (retail_price) — гости и клиенты без одобренной компании
   ↓ скидка ценовой группы company.price_tier.discount_percent
   ↓ ограничения:
       purchase_price неизвестна → скидка не больше pricing.max_discount_without_purchase
       purchase_price известна   → цена не ниже purchase_price × (1 + pricing.min_margin_percent/100)
Оптовая цена — клиенты одобренной компании
   ↑ product_prices (точечная цена для группы) имеет приоритет, но с тем же нижним пределом
```

**`PriceResolver::for(Product $product, ?User $user): ?Price`** — `null` означает «цена по запросу».
1. `retail_price = null` → `null`.
2. Пользователь состоит в одобренной компании с ценовой группой:
   - есть запись в `product_prices` для группы → эта цена, не ниже нижнего предела;
   - иначе `retail_price × (1 − действующая скидка)`, округление вверх до рубля, не ниже нижнего предела.
3. Иначе → `retail_price`.

**Правила отображения:**
- Гость, клиент без компании и клиент компании в статусе `pending` видят розничную цену. У `pending` дополнительно плашка «Оптовые цены откроются после проверки заявки».
- Клиент одобренной компании видит перечёркнутую розничную и свою цену с подписью «Ваша цена». Название группы показывается только при `pricing.show_tier_name = true`.
- `old_price` (ручная акция) выводится перечёркнутой только для розничной цены. Оптовику показываются две цены — розничная перечёркнутая и его, третьей не бывает.
- Цена в корзине фиксируется при добавлении (`cart_items.price`) и сверяется при каждом открытии корзины. При расхождении — плашка «Цена изменилась: было … стало …» и пересчёт.
- Подпись о НДС берётся из `seller.vat_mode`.

**Деньги:** `decimal(12,2)` в БД, целые копейки в PHP (`Support\Money`), проценты — целые базисные пункты (`Support\Percent`). Округление вверх до рубля — `ceil` в копейках. Флоаты запрещены.

**Пока `pricing.max_discount_without_purchase = 0`, оптовые цены равны розничным.** Значение задаёт заказчик до запуска B2B (§20). Причина: продажа ниже РРЦ при неизвестной закупке может оказаться убыточной или нарушить ценовую политику поставщика.

**Прайс для оптовика:** кнопка в ЛК «Скачать прайс XLSX». Генерация потоком через OpenSpout. Колонки: артикул, код 1С, наименование, бренд, категория, единица, наличие (статус словами), розничная цена, ваша цена. Кэш файла — 1 час на связку «ценовая группа + версия каталога».

---

## 8. Витрина: страницы и маршруты

```
GET  /                                   Главная
GET  /catalog                            Все категории
GET  /catalog/{category:slug}            Листинг категории (с товарами дочерних)
GET  /product/{product:slug}             Карточка товара
GET  /brands, /brands/{brand:slug}       Бренды
GET  /search?q=                          Результаты поиска
GET  /cart                               Корзина
GET  /checkout                           Оформление заявки
POST /checkout                           Создание заявки
GET  /checkout/success/{number}          «Спасибо» — только для сессии, создавшей заказ
POST /leads                              Лиды всех типов
GET  /favorites                          Избранное
GET  /login, /register, /forgot-password, /reset-password/{token}
GET  /wholesale, POST /wholesale         Лендинг «Оптовым клиентам» и заявка
GET  /account                            ЛК: сводка
GET  /account/orders, /account/orders/{order}
POST /account/orders/{order}/repeat
GET  /account/company, PUT /account/company
GET  /account/bulk-order                 Заказ списком
GET  /account/price-list                 Скачивание прайса
GET  /sitemap.xml, /robots.txt
fallback                                 редирект из redirects (301) → статическая страница по slug → 404
```

Статические страницы отдаются через `Route::fallback`, поэтому не перехватывают маршруты Filament и Livewire.

### 8.1 Главная

Секции сверху вниз:
1. **Шапка** (липкая): логотип, кликабельный телефон, поиск с мгновенной выдачей, вход/ЛК, избранное, корзина со счётчиком.
2. **Панель подбора** вместо баннеров.
   - Слева — корневые категории с `show_on_home = true` и иконками, по умолчанию 8: Тепловое и технологическое, Холодильное, Нейтральное, Электромеханическое, Посудомоечное, Линия раздачи, Фаст-фуд, Вентиляционное; плюс «Все категории».
   - Справа — «Соберём кухню под задачу»: три активные подборки (`collections`), стартовые «Кафе до 50 посадок», «Бар», «Кондитерский цех».
   - Ниже — поле «Знаю артикул» с мгновенным поиском.
3. **Ленты карточек:**
   - «В наличии» — `in_stock`, по популярности;
   - «Хиты», «Новинки»;
   - «Со склада в Симферополе» — только если таких товаров не меньше `catalog.local_strip_min_products` (на 16.09.2026 их 38).
4. **«Почему мы»:** четыре факта без иконок-клипарта. Тексты даёт заказчик; утверждения о 44-ФЗ, отсрочке и гарантии — только подтверждённые.
5. **Бренды** списком названий (логотипов у поставщика нет).
6. **SEO-текст** из настроек и футер с картой категорий и реквизитами продавца.

### 8.2 Листинг категории

- **Фильтры** — Livewire без перезагрузки, состояние в query string (`pushState`). Без JS работает обычная GET-форма с кнопкой «Показать товары».
  - Подкатегории.
  - Цена (диапазон; товары с ценой по запросу в фильтр не попадают).
  - «Только в наличии» — по умолчанию **выключен**.
  - Бренд — множественный выбор с поиском.
  - Характеристики с `attributes.is_filterable = true`, которые есть у товаров категории (на реальных данных появятся с API).
- **Сортировка:**
  - по популярности (по умолчанию: `availability_rank`, затем `popularity`);
  - цена ↑ и ↓ (цена по запросу — в конце);
  - новизна;
  - название.
- **Популярность** пересчитывается раз в сутки: `10 × число заказов товара за 90 дней + 1000 × is_hit`.
- **Пагинация:** по 24 товара, кнопка «Показать ещё» и обычные ссылки на страницы.
- Товары `discontinued` в листинге не выводятся.
- **Вид «плитка» и «список».** Список — строка таблицы: артикул, наименование, бренд, наличие, цена, количество, кнопка. Выбор запоминается в cookie.
- **Карточка в плитке:**
  - фото или заглушка (§9) с фиксированным соотношением сторон без сдвига вёрстки;
  - бренд, наименование (до 3 строк), артикул;
  - статус наличия;
  - цена или «Цена по запросу»;
  - кнопка по §6.5;
  - «В избранное».
- Хлебные крошки с разметкой `BreadcrumbList`.

### 8.3 Карточка товара

- **Слева** — галерея (главное фото, миниатюры, зум по клику, свайп на мобиле) или крупная заглушка.
- **Справа — блок покупки:**
  - наименование (`h1`), бренд, артикул и код 1С;
  - статус и склады (§6.5);
  - цена: одна, две для оптовика или «Цена по запросу»;
  - счётчик количества (шаг 1, единица рядом);
  - кнопки по §6.5;
  - «Купить в 1 клик» — модалка с телефоном, лид `one_click`;
  - главные характеристики (`is_main`), если есть.
- **Ниже — вкладки:**
  - Описание — скрыта, если описания нет;
  - Характеристики — таблица, скрыта, если характеристик нет;
  - Доставка и оплата — из настроек;
  - Гарантия.
- **«Похожие товары»:** та же категория, без `discontinued`; при известной цене — ±30% от неё, иначе тот же бренд.
- **«Сопутствующие»:** `related_products`.
- **Разметка** `Product` + `Offer` (`price`, `priceCurrency: RUB`, `availability` по §6.5, `sku`, `brand`). При цене по запросу — только `Product`.

### 8.4 Поиск

- **Мгновенная выдача** в шапке: Livewire, задержка 250 мс, от 2 символов, до 6 товаров и до 3 категорий.
- **`products.search_text`** хранит:
  - наименование, модель и бренд в нижнем регистре с заменой «ё» на «е»;
  - «сжатые» артикул, код 1С и модель — без пробелов, дефисов, точек и слешей (`КИП-27Н-3,5` → `кип27н35`).
  - Строка пересчитывается при импорте и ручном изменении товара.
- **Приоритет выдачи:**
  1. точное совпадение сжатого запроса с артикулом, кодом 1С или моделью;
  2. начало наименования или модели;
  3. все слова запроса входят в строку;
  4. хотя бы одно слово входит.
- **Нормализация запроса:** нижний регистр, «ё» → «е», сжатая форма для кодов. Если результатов нет — повтор со сменой раскладки (lat ↔ кир).
- **Движок** `DatabaseSearchEngine` — `LIKE` по `search_text`. Цель: ≤ 300 мс на сервере при 15 000 товаров. Интерфейс `SearchEngineInterface` оставлен для замены.
- **Страница `/search`** с теми же фильтрами, что листинг. Товары `discontinued` не выводятся.
- **Пустая выдача:** «Ничего не нашли по запросу «X»», форма «Найдём за вас» (лид `not_found`) и популярные категории.

---

## 9. Дизайн-система

Визуальный язык и экраны — в `DESIGN-BRIEF-horeca-shop.md`. Вёрстка витрины начинается после утверждения макетов (§18). Ниже — обязательная для реализации часть.

**Токены** (Tailwind 4, блок `@theme` в `resources/css/app.css`):

```
--color-steel-50:    #F4F6F7   фон страницы
--color-steel-100:   #E7EBEE   фон секций, заливка полей
--color-steel-200:   #D3D9DE   границы карточек, разделители
--color-steel-400:   #8A959E   рамки полей, иконки, неактивное; не для текста (3,1:1 на белом)
--color-steel-600:   #5B6670   вторичный текст, подписи (5,9:1)
--color-graphite:    #15191D   основной текст, шапка, футер, бейдж «Под заказ»
--color-flame:       #0B5FFF   акцент: кнопки, ссылки, активные фильтры, фокус (5,1:1)
--color-flame-700:   #0847C4   hover и active
--color-stock:       #1F8A5F   индикатор «В наличии» (точка, заливка)
--color-stock-text:  #177A52   текст «В наличии» (5,3:1)
--color-incoming:    #B45309   «Ожидается» (5,0:1)
--color-danger:      #B42318   ошибки форм (6,6:1)
--color-surface:     #FFFFFF   карточки
```

- **Шрифты:** Golos Text (заголовки, 600/700) и Inter (текст, 400/500) — вариативные, локально через `@fontsource-variable`, подмножества cyrillic и latin, `font-display: swap`, предзагрузка файла Inter. Цены, артикулы и характеристики — табличные цифры (`font-variant-numeric: tabular-nums`).
- **Шкала:** 13 / 14 / 16 / 18 / 22 / 28 / 36 / 48 px. Межстрочный интервал 1.5 для текста, 1.15 для заголовков. Текстовые блоки — не шире 72 символов.
- **Форма:** радиус 6 px у кнопок и полей, 4 px у карточек, 0 у таблиц характеристик. Теней по умолчанию нет, карточка отделена границей 1 px `steel-200`. Тень `0 4px 16px rgba(21,25,29,.10)` — только на hover и у выпадающих меню.
- **Сетка:** контейнер 1320 px, 12 колонок. Промежуток 24 px от 1024 px, 16 px от 768 px, 12 px ниже. Листинг: 4 / 3 / 2 карточки. Выравнивание по левому краю, по центру — только «Спасибо» и пустые состояния.
- **Движение:** только отклик на действие пользователя. `prefers-reduced-motion: reduce` отключает анимации.
- **Доступность:**
  - mobile-first, тач-цели ≥ 44 px;
  - фокус `outline: 2px solid var(--color-flame); outline-offset: 2px`;
  - контраст текста ≥ 4.5:1, элементов интерфейса ≥ 3:1;
  - статус никогда не передаётся только цветом — всегда словом и формой бейджа;
  - `alt` у изображений, `<label>` у полей, семантические теги;
  - каталог читается без JS.
- **Заглушка вместо фото** — HTML/CSS-компонент без сетевых запросов: бренд, модель, иконка типа оборудования. Вид — по утверждённой концепции из брифа.
- **Тексты:**
  - кнопки называют действие: «Добавить в корзину», «Отправить заявку», «Запросить счёт»;
  - ошибка объясняет, что исправить: «Телефон не распознан. Формат: +7 978 123-45-67»;
  - пустая корзина приглашает: «В корзине пусто. Начните с холодильного оборудования или введите артикул».
- **Типографика данных** (`Support\Typography`):
  - `383 995 ₽` — разряды и знак рубля через неразрывный пробел U+00A0;
  - `7 кВт`, `230 В` — неразрывный пробел перед единицей;
  - `400×750×470 мм` — знак «×» (U+00D7);
  - кавычки «ёлочки», тире «—».

---

## 10. Корзина и оформление заявки

### 10.1 Корзина

- **Серверная** (таблицы `carts` и `cart_items`): оптовику нужна одна корзина на всех устройствах, магазину — аналитика брошенных корзин.
- **Гость:** корзина по `session_id`, живёт 30 дней. При входе `MergeGuestCart` суммирует количества и пересчитывает цены под пользователя.
- **Что нельзя добавить:** товары с ценой по запросу и снятые с производства — кнопки нет, а действие `AddToCart` возвращает ошибку.
- **Действия:**
  - количество: ± и ручной ввод, целое от 1 до 9999;
  - удаление с кнопкой «Вернуть» (10 с);
  - очистка.
- **Показываются:**
  - сумма и число позиций;
  - общий вес — только если он известен у всех позиций;
  - подсказка о бесплатной доставке по городу (`delivery.free_city_from`);
  - у позиций «Под заказ» — подпись «Срок поставки уточнит менеджер».
- **Сверка при каждом открытии:**
  - изменилась цена → плашка «Цена изменилась»;
  - товар стал «Снят с производства» или «Цена по запросу» → позиция подсвечена с кнопкой «Удалить», оформление недоступно до удаления.

### 10.2 Оформление

Одна страница, три блока, без пошагового мастера:
1. **Контакты:**
   - имя;
   - телефон — маска `+7 ___ ___-__-__`, проверка российского номера;
   - e-mail — необязательный, нужен для письма-подтверждения;
   - «Я представляю юрлицо» → раскрываются ИНН и название.
   - Для вошедших пользователей всё предзаполнено.
2. **Доставка:**
   - самовывоз — адрес из `pickup.address`;
   - транспортная компания — город и выбор ТК: СДЭК, ПЭК, Деловые Линии, КИТ, другая;
   - курьер по городу — адрес.
3. **Оплата:** «Счёт на оплату (для юрлиц)» и «Наличными при получении». Вариант `online` есть в коде и БД, но скрыт настройкой `payments.online_enabled = false`.

- Справа — липкая сводка заказа.
- **Кнопка «Отправить заявку» активна всегда.** Чекбокс согласия со ссылками на согласие на обработку ПДн и политику конфиденциальности обязателен; без него — ошибка «Отметьте согласие на обработку персональных данных».
- В форме — скрытое поле `idempotency_key` (UUID, создаётся при открытии страницы).

### 10.3 После отправки

1. **Транзакция:** `order` + `order_items` со снимком артикула, кода, названия, единицы, наличия и цены; очистка корзины. `type = wholesale`, если заявку оформил клиент одобренной компании, иначе `retail`. Повторная отправка с тем же `idempotency_key` ведёт на страницу уже созданного заказа.
2. **Номер** `HR-{ymd}-{NNNN}`: строка `order_counters` за текущую дату блокируется `SELECT … FOR UPDATE`. Дата — по `Europe/Moscow`.
3. **Уведомления** — через очередь `default`, ответ пользователю не ждёт:
   - **Telegram:** номер, тип (розница/опт), сумма, число позиций и до 10 названий, город, ссылка на заказ в админке. Имя и телефон — только при `notify.telegram_include_contacts = true` (§15);
   - **e-mail менеджеру:** полная таблица;
   - **e-mail клиенту**, если указан: подтверждение, номер, что будет дальше.
4. **«Спасибо»:** номер, что произойдёт дальше и когда (текст обещания даёт заказчик), контакты менеджера, «Продолжить покупки». Страница доступна только сессии, создавшей заказ.
5. Событие `OrderCreated` — точка расширения для аналитики и будущей оплаты.

### 10.4 Антиспам

- Honeypot-поле и метка времени формы: отправка быстрее 3 с отклоняется.
- **Ограничения частоты:**
  - оформление — 10 в час на IP и 3 в час на один телефон;
  - лиды — 10 в час на IP.
- reCAPTCHA не ставим. При необходимости — собственная арифметическая капча.

### 10.5 Задел под онлайн-оплату

Интерфейс `PaymentGatewayInterface { createPayment(Order $order): string; handleWebhook(Request $request): void; }`. Поля `payment_id`, `paid_at` и статус `paid` уже в схеме; переход `invoiced → paid` есть в статусной модели. Подключение ЮKassa = класс шлюза + маршрут вебхука + включение `payments.online_enabled`. В MVP не реализуется.

---

## 11. Личный кабинет и B2B

**Регистрация оптовика** (`/wholesale`).
- **Лендинг:** выгоды — только подтверждённые заказчиком (оптовые цены, персональный менеджер, работа по безналу, отсрочка — если есть).
- **Форма:** ИНН, юр. название, сегмент, контактное лицо, телефон, e-mail, город, пароль, комментарий.
- **Результат:** создаются `user` (`customer`) и `company` (`pending`), пользователь связан с компанией. Вход работает сразу, цены — розничные.
- Автоподстановка реквизитов по ИНН — вне MVP.

**Модерация.**
- Новая заявка → уведомление менеджерам (§13).
- Менеджер назначает ценовую группу и ставит `approved` → письмо клиенту «Оптовые цены открыты».

**Разделы ЛК:**
- **Сводка:** статус компании, подключены ли оптовые цены, последние 5 заявок, контакты менеджера.
- **Заявки:**
  - список со статусами;
  - детальная страница с позициями и историей статусов;
  - «Повторить заказ» (`RepeatOrder`): текущие цены; позиции со статусом «Снят с производства», с ценой по запросу или удалённые — в отдельном списке «Не добавлены»;
  - скачивание счёта (PDF), когда менеджер его прикрепит.
- **Компания:** редактирование реквизитов. Смена ИНН или юр. названия возвращает компанию в `pending`, до повторного одобрения цены розничные.
- **Заказ списком:**
  - строки `артикул;количество` или `артикул<TAB>количество`, либо XLSX до 1 МБ и до 500 строк (чтение через OpenSpout);
  - сопоставление по сжатому артикулу, затем по коду 1С;
  - превью построчно: найден / не найден / несколько совпадений (выбор товара) / цена по запросу (не добавляется, есть кнопка «Запросить цену на эти позиции» — один лид) / снят с производства;
  - «Добавить в корзину».
- **Прайс:** скачивание XLSX (§7).
- **Избранное.**

**Middleware `EnsureWholesaleApproved`** закрывает «Заказ списком» и «Прайс».

---

## 12. Админка (Filament 5)

- **Доступ:** путь `/manage`, общий guard `web`. Вход только для ролей `manager` и `admin` (`canAccessPanel`), только HTTPS.
- **2FA:** встроенная двухфакторная аутентификация Filament обязательна для администраторов и менеджеров.
- **Индексация:** заголовок `X-Robots-Tag: noindex, nofollow` на всех страницах панели.

**Дашборд:**
- заявки за сегодня и неделю (график);
- необработанные новые заявки — крупный счётчик;
- новые лиды;
- компании на проверке;
- последний прогон каждого профиля импорта (статус, время, цифры);
- несопоставленные категории и бренды поставщика;
- число товаров с ценой по запросу.

**Ресурсы:**

- **Товары.**
  - Колонки: фото, артикул, код 1С, наименование, категория, бренд, РРЦ, розничная цена, наличие, видимость.
  - Фильтры: категория, бренд, наличие, «цена по запросу», «без фото», «без категории», «снят с производства», «есть ручные правки».
  - Массовые действия: показать/скрыть, сменить категорию, отметить «хит»/«новинка», экспорт в XLSX.
  - Форма — вкладки: Основное · Цены (включая `product_prices`) · Наличие (склады, только чтение) · Характеристики · Фото · SEO. Замок у полей из `locked_fields` и действие «Вернуть значение поставщика».
- **Категории.** Дерево (родитель + сортировка), массовое включение, `show_on_home`, иконка, SEO-поля.
- **Соответствия поставщика** (`supplier_refs`). Вкладки «Категории», «Бренды», «Склады», «Характеристики». Фильтр «не сопоставлено». Действия: «Сопоставить с…», «Игнорировать», «Объединить бренды».
- **Склады.** Видимость, город, срок доставки до Симферополя.
- **Заказы.**
  - Таблица с быстрым фильтром по статусу и цветными бейджами.
  - Действия: «Сменить статус» (комментарий обязателен для `canceled`), «Прикрепить счёт» (PDF), «Позвонили» — быстрая отметка.
  - Просмотр — читаемый бланк заявки, ссылка `tel:`, копирование состава в буфер.
- **Лиды.** Таблица по типам и статусам, быстрая смена статуса.
- **Клиенты и компании.** Модерация, назначение ценовой группы, заказы компании (RelationManager).
- **Поставщики, профили импорта, прогоны.** Кнопки запуска; страница прогона с таблицей ошибок и скачиванием лога.
- **Ценовые группы, бренды, характеристики** (`is_filterable`, `is_main`, единица), **подборки, страницы, редиректы.**
- **Страница «Настройки»** — ключи из §5.5.

**Удаление** товаров и заказов доступно только администратору (мягкое удаление).

**Таблицы:** поиск, сортировка, пагинация 25/50/100, экспорт — там, где он нужен.

---

## 13. Уведомления

`TelegramNotifier`: `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID` из `.env`. Отправка через очередь `default`, 3 повтора с нарастающей паузой, ошибки логируются. Падение Telegram не ломает заказ.

| Событие | Telegram | E-mail менеджеру | E-mail клиенту |
|---|---|---|---|
| Новая заявка | да | да | да, если указан e-mail |
| Лид (1 клик, цена, срок, аналог, «не нашли», перезвонить) | да | нет | нет |
| Заявка на опт | да | да | да (подтверждение) |
| Опт одобрен | нет | нет | да |
| Смена статуса заказа | нет | нет | да для `confirmed`, `invoiced`, `paid`, `shipped`, `completed`, `canceled` |
| Импорт упал или сработал порог | да, с текстом ошибки | да | нет |
| Итоги импорта за день (в 20:00) | да: по каждому профилю — прогоны, создано, обновлено, снято, ошибки | нет | нет |

- Успешные прогоны по отдельности не уведомляют: остатки обновляются до 35 раз в сутки.
- Внутренние статусы `new` и `processing` клиенту не сообщаются.
- **Письма:** Blade-шаблоны с логотипом и палитрой сайта. Отправка через SMTP домена в HestiaCP, `MAIL_FROM_ADDRESS` на своём домене, SPF и DKIM настроены (§17.6).

---

## 14. SEO, производительность, аналитика

**SEO:**
- ЧПУ везде, без `id` в адресах.
- **`MetaBuilder`** заполняет пустые `meta_*` по шаблонам из настроек:
  - товар — «{Наименование} — купить в Симферополе, цена {цена} | {site.name}» (без цены, если она по запросу);
  - категория — «{Категория} — купить в Симферополе | {site.name}».
- **Микроразметка:** `Organization`, `BreadcrumbList`, `Product` + `Offer` (§8.3), `FAQPage` на страницах доставки и оплаты.
- **`sitemap.xml`** генерируется ежедневно в 04:00: активные категории, видимые товары кроме снятых, активные страницы, бренды.
- **`robots.txt`:**
  - `Disallow` — `/account`, `/cart`, `/checkout`, `/search`, `/favorites`;
  - для Яндекса `Clean-param: sort&view&utm_source&utm_medium&utm_campaign&utm_content&utm_term`;
  - `/manage` в файл не вносится — закрыт авторизацией и `X-Robots-Tag`.
- **Канонические адреса.** Листинг с более чем одним активным фильтром или с фильтром и сортировкой — `noindex, follow`. Страницы пагинации канонизируют сами себя.
- **404** — своя страница с поиском и категориями. Редиректы 301 — из таблицы `redirects` (маршрут `fallback`).
- **Снятый с производства товар** отдаёт 200 со статусом и похожими товарами.

**Производительность:**
- **Цели:** LCP ≤ 2,0 с на 4G, CLS ≤ 0,05, TTFB ≤ 300 мс. Lighthouse mobile на категории и карточке товара: ≥ 90 Performance, ≥ 95 Accessibility.
- **Кэш:** дерево категорий, фасеты фильтров, блоки главной — ключи с версией каталога `catalog:v{N}:…`, TTL 1 час.
  - Версия увеличивается после импорта и при правках категорий и товаров в админке (`CatalogCacheObserver`).
  - Теги кэша не используются: схема работает на Redis и на драйвере `database`.
- **Изображения:** WebP; `srcset` из `card` (600w) и `full` (1200w), `thumb` — для миниатюр. `loading="lazy"` везде, кроме первого экрана. Заглушка сетевых запросов не делает.
- **Прод:** `composer install --no-dev -o`, `php artisan optimize`, OPcache.
- **N+1:** в листингах — жадная загрузка бренда, категории и медиа; на главной, в категории, карточке и поиске тесты проверяют число запросов ≤ 20.

**Аналитика:**
- **Яндекс.Метрика** (номер счётчика в настройках) с целями `add_to_cart`, `checkout_start`, `order_created`, `lead_created` (с типом лида), `bulk_order`; электронная коммерция через `dataLayer`.
- Счётчик загружается после согласия в cookie-баннере (§15).

---

## 15. Безопасность и персональные данные

1. **СУБД.** Только MariaDB/MySQL, отдельный пользователь БД с правами только на свою базу.
2. **Пароли.** bcrypt, минимум 8 символов, `Password::defaults()->uncompromised()`.
3. **Ограничения частоты:**
   - вход — 5 в минуту на IP + e-mail;
   - оформление — 10 в час на IP и 3 в час на телефон;
   - лиды — 10 в час на IP;
   - поиск — 60 в минуту на IP.
4. **Формы и модели.** Все формы — через `FormRequest` с явными правилами. `$fillable` явный, `$guarded = []` запрещён.
5. **Policies** на `Order`, `Company`, `Cart`, `Favorite`: пользователь видит только своё. Проверяется тестами на 403.
6. **Админка и сессии.** `/manage` — HTTPS и 2FA для администраторов и менеджеров. Сессии `secure`, `httponly`, `same_site=lax`.
7. **Загрузка файлов.** Белый список: XLSX заказа списком — до 1 МБ, PDF счетов — до 10 МБ, JPG/PNG/WebP — до 10 МБ. Проверка реального MIME. Приватные файлы (счета) — вне `public`, отдаются контроллером с проверкой Policy.
8. **Веб-доступ.** Document root — `public/`; `.env`, `config`, `storage` из веба недоступны.
9. **Логи.** Канал `daily`, 14 дней; уровень `critical` дублируется в Telegram.
10. **Персональные данные (152-ФЗ).** До запуска проверяется юристом заказчика.
    - База данных с персональными данными размещается на сервере в РФ.
    - Согласие на обработку ПДн — отдельный документ (страница `soglasie-na-obrabotku-personalnyh-dannyh`), ссылка у каждой формы. Политика конфиденциальности — отдельная страница.
    - Уведомление Роскомнадзора об обработке ПДн подаёт заказчик.
    - Telegram — зарубежный сервис: по умолчанию имя и телефон клиента в уведомления не попадают (`notify.telegram_include_contacts = false`).
    - Cookie-баннер; Метрика загружается после согласия.
    - Удаление данных по запросу: `php artisan users:anonymize {user}` обезличивает пользователя и его заявки.
11. **Заголовки:** `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP в режиме `Report-Only`. Включение CSP в блокирующем режиме требует отдельной настройки Livewire и Alpine — вне MVP.
12. **XML поставщика.** `XMLReader` без загрузки внешних сущностей и DTD (`LIBXML_NONET`, без `LIBXML_NOENT` и `LIBXML_DTDLOAD`). Скачивание — только с адресов из профиля, до 100 МБ.

---

## 16. Тестирование

Pest 5. Тестовая база — MariaDB той же мажорной версии, что на проде, трейт `RefreshDatabase`.

**Unit:**
- `Money` и `Percent`: копейки, базисные пункты, округление вверх;
- `PriceResolver`: РРЦ; дилерская цена с наценкой; скидка группы; `product_prices`; нижний предел по закупке; предел скидки без закупки; цена по запросу;
- нормализация цен: `48 605,8` с неразрывным пробелом и `72581.5`; распознавание испорченной кодировки;
- `StockValueMapper`: `много`, `в наличии`, `несколько`, `0`, неизвестное значение, пустой склад, числовые остатки;
- `AvailabilityCalculator`: все пять статусов, скрытые склады не учитываются;
- `AttributeValueParser`: «ширина:400мм» → 400 и «мм»; «AISI 430» → строка;
- `QueryNormalizer`: смена раскладки, «ё», сжатая форма кода;
- `GenerateOrderNumber`: счётчик по дням, часовой пояс;
- `Slugger`: коллизии, длина.

**Feature:**
- главная, `/catalog`, категория, карточка, поиск, бренд отдают 200 с ожидаемым содержимым; снятый товар — 200 со статусом и отсутствует в листинге;
- фильтры по цене, бренду, наличию и характеристике (демо); состояние в URL; «Только в наличии» по умолчанию выключен;
- корзина: добавление гостем, объединение при входе, запрет на товары с ценой по запросу и снятые, плашка «Цена изменилась»;
- оформление:
  - создаёт заказ и позиции, отправляет уведомления (`Notification::fake`, `Queue::fake`);
  - валидация телефона и согласия;
  - повторная отправка с тем же ключом — один заказ;
  - ограничение частоты;
- лиды всех типов создаются и уведомляют;
- цены: гость и клиент — розничная; одобренная компания — своя; `pending` — розничная; название группы скрыто;
- `EnsureWholesaleApproved` отдаёт 403 на заказ списком и прайс; чужой заказ — 403;
- заказ списком: найден, не найден, несколько совпадений, цена по запросу;
- импорт на фикстурах `tests/Fixtures/rosholod/catalog_sample.xml` и `stock_sample.xml` — около 60 реальных карточек, вырезанных из выгрузок, в windows-1251:
  - **что входит в фикстуры:** пустой артикул, цена с неразрывным пробелом и запятой, нулевая цена, название с сокращениями, повторяющееся имя категории, корень без товаров; все четыре значения остатка, пустой склад, позиция только в остатках, склад Симферополь;
  - **что проверяется:**
    - создаются зеркало категорий (неактивное), бренды, товары, остатки и статусы;
    - повторный прогон даёт `unchanged`;
    - ответ 304 (`Http::fake`) даёт `skipped`;
    - `locked_fields` не перезаписываются;
    - прогон остатков не меняет названия и категории;
    - товар, пропавший из 3 снимков, становится `discontinued`;
    - обрезанный файл и файл с < 80% записей дают `failed` без изменений каталога;
- число запросов ≤ 20 на ключевых страницах.

**Фабрики** для всех моделей.

**`DemoSeeder`** — чтобы верстать и показывать без реального импорта:
- 8 корневых категорий с подкатегориями, 6 брендов;
- 150 товаров: разное наличие, 10% с ценой по запросу, часть с фото, фильтруемые характеристики «Мощность, кВт», «Напряжение, В», «Ширина, мм»;
- 3 ценовые группы;
- пользователи `admin@horeca.test`, `manager@horeca.test`, `opt@horeca.test` (одобренная компания);
- 5 заказов, 3 подборки.

**`ProductionSeeder`:** ценовые группы, ключи настроек со значениями по умолчанию, обязательные страницы (неактивные до наполнения текстами), профили импорта Росхолода (выключены до проверки).

---

## 17. Окружение и развёртывание

### 17.0 Локальная разработка

**Состояние на 16.09.2026.** Компьютер разработки — Windows 11. Есть Node 24 и Git. Нет PHP, Composer, MariaDB, Redis и Docker. WSL установлен без дистрибутивов.

Вариант выбирается с заказчиком в спринте 0 и записывается в README. Установка требует прав администратора, её выполняет заказчик.

| | A. WSL2 + Ubuntu 24.04 | B. Windows напрямую |
|---|---|---|
| Состав | PHP 8.4, Composer, MariaDB 11.8, Redis, Node 24 внутри Ubuntu | PHP 8.4 и Composer (Laravel Herd или официальная сборка PHP), MariaDB 11.8 для Windows; Redis локально не нужен — драйверы `database` |
| Плюсы | совпадает с продом: Linux, регистр имён файлов, Redis | быстрые команды и файловые операции, без обёрток `wsl` |
| Минусы | команды через `wsl`, медленная работа с файлами на диске Windows | отличия от Linux ловятся только на стейджинге |

В обоих вариантах тесты идут на MariaDB (база `horeca_test`), SQLite не используется.

### 17.1 Требования к серверу

- **ОС и панель:** Ubuntu 22.04/24.04, HestiaCP 1.10+.
- **PHP 8.4-FPM** с расширениями: `bcmath`, `intl`, `gd`, `zip`, `mbstring`, `xml`, `xmlreader`, `curl`, `pdo_mysql`, `opcache`, `redis`, `exif`, `fileinfo`.
- **Сервисы и инструменты:** MariaDB 11.8 (ставится HestiaCP по умолчанию) или MySQL 8.4, Redis 7, Node 24 (сборка), Composer 2, Git, Supervisor.
- **Расположение:** сервер в РФ (§15.10).
- **Память:** не меньше 2 ГБ. Если меньше, ассеты собираются локально и загружаются в `public/build`.

### 17.2 Домен

Web-домен создаётся в HestiaCP. Document root указывает на `public/` симлинком:

```
cd /home/<user>/web/<domain>
rm -rf public_html
ln -s /home/<user>/web/<domain>/app/public public_html
```

Другой вариант — собственный шаблон nginx с `root …/app/public`. Код проекта — в `/home/<user>/web/<domain>/app`.

### 17.3 Первичная установка

```
git clone <repo> app && cd app
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# заполнить .env: APP_URL, APP_TIMEZONE=Europe/Moscow, DB_*, REDIS_*, MAIL_*, TELEGRAM_*
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
php artisan storage:link
npm ci && npm run build
php artisan optimize
chown -R <user>:<user> storage bootstrap/cache
```

### 17.4 Очереди

Две программы supervisor: импорт не занимает воркеры уведомлений.

```
[program:horeca-imports]
command=php /home/<user>/web/<domain>/app/artisan queue:work redis-imports --queue=imports --sleep=3 --tries=1 --timeout=3600 --max-time=7200
user=<user>
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/home/<user>/web/<domain>/app/storage/logs/worker-imports.log

[program:horeca-default]
command=php /home/<user>/web/<domain>/app/artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
user=<user>
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=180
stdout_logfile=/home/<user>/web/<domain>/app/storage/logs/worker-default.log
```

- **`redis-imports`** — отдельное соединение в `config/queue.php` с `retry_after = 3700`. Параметр больше таймаута задачи, иначе долгий импорт будет запущен повторно.
- **Без Redis:** `QUEUE_CONNECTION=database` и соединение `database-imports` с тем же `retry_after`. Конфиг обязан работать в обоих режимах.

### 17.5 Cron и расписание

В HestiaCP → Cron:

```
* * * * * cd /home/<user>/web/<domain>/app && php artisan schedule:run >> /dev/null 2>&1
```

Расписание в `routes/console.php`:

| Задача | Когда |
|---|---|
| профили импорта | по `schedule` каждого профиля |
| `popularity:recalculate` | ежедневно в 03:00 |
| бэкап БД (`mariadb-dump` + gzip, 14 копий) и копия в удалённое хранилище (бэкапы HestiaCP на SFTP или S3-совместимое хранилище в РФ) | ежедневно в 03:30 |
| `sitemap:generate` | ежедневно в 04:00 |
| `carts:prune` (просроченные гостевые корзины) | ежедневно |
| очистка файлов импорта сверх 30 на профиль | ежедневно |
| `queue:prune-batches`, `queue:prune-failed` | ежедневно |
| `imports:digest` — итоги импорта в Telegram | ежедневно в 20:00 |

### 17.6 Почта

- В HestiaCP: почтовый домен, ящик `shop@<domain>`, SPF и DKIM.
- В `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=localhost`, порт 587, TLS.
- Проверка: `php artisan mail:test {email}` отправляет тестовое письмо.

### 17.7 Деплой обновлений

Скрипт `deploy.sh` в корне:

```
php artisan down --render=errors::503
git pull
composer install --no-dev -o
npm ci && npm run build        # или загрузка собранного public/build при нехватке памяти
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

### 17.8 Проверка восстановления

Раз в месяц последний бэкап разворачивается в отдельную базу `horeca_restore_check` по инструкции из README, результат фиксируется. До запуска проверка выполняется один раз обязательно.

---

## 18. План спринтов

Порядок работ — два параллельных трека. **Бэкенд** не зависит от дизайна. **Вёрстка** витрины начинается только после утверждения соответствующих этапов макетов. Длительность зависит от скорости утверждения макетов и от поставщика, поэтому в днях не фиксируется.

**Трек «Дизайн»** (заказчик + Claude Design, по `DESIGN-BRIEF-horeca-shop.md`):
- **Д1** — название, логотип, референсы;
- **Д2** — токены, заглушка, состояния карточки товара;
- **Д3** — листинг и страница товара;
- **Д4** — шапка, поиск, главная;
- **Д5** — корзина, оформление, модалки;
- **Д6** — B2B и личный кабинет;
- **Д7** — служебные экраны и письма.

**Спринт 0 — Подготовка.**
- Выбор и настройка локальной среды (§17.0).
- Git-репозиторий, README с запуском.
- Отправка вопросов поставщику (§20).
- *DoD:* `php -v`, `composer -V` и подключение к MariaDB работают; репозиторий создан.

**Спринт 1 — Скелет.**
- Laravel 13, MariaDB, Redis с запасным режимом `database`.
- Filament 5 на `/manage` с 2FA; роли на enum и Policies.
- Все миграции §5, модели со связями, фабрики, `DemoSeeder`, `ProductionSeeder`.
- Pint, Pest, `.env.example`, README.
- Tailwind 4 с токенами §9 и минимальный layout без дизайна витрины.
- *DoD:* `php artisan test` зелёный; администратор входит в `/manage` с 2FA; демо-данные в базе.

**Спринт 2 — Импорт XML.**
- Контракт, DTO, `Cp1251XmlReader`, адаптеры каталога и остатков, условный GET.
- Staging, пороги, `CategorySync`, `BrandSync`, `ProductUpserter`, `StockUpserter`, `AvailabilityCalculator`.
- Расчёт розничной цены, `search_text`.
- Очередь `imports` с отдельным соединением, команда `supplier:import`.
- Filament-ресурсы: поставщики, профили, прогоны, соответствия, склады.
- Итоги дня и тревоги в Telegram, фикстуры и тесты §16.
- *DoD:*
  - оба реальных файла заливаются: около 15 000 товаров, 324 категории в неактивном зеркале, статусы наличия совпадают с разбором данных с поправкой на свежесть файлов;
  - повторный прогон даёт `skipped` или `unchanged`;
  - ручные правки не затираются;
  - битый файл не меняет каталог.

**Спринт 3 — Витрина каталога** (после Д2–Д4).
- Библиотека Blade-компонентов и локальная страница-стайлгайд для сверки с макетами.
- Главная, `/catalog`, листинг с фильтрами, сортировкой и режимами вида, карточка товара, бренды, поиск.
- Хлебные крошки, микроразметка, мобильная версия, скелетоны, 404.
- Выкладка на стейджинг для проверки на реальных устройствах.
- *DoD:* Lighthouse mobile ≥ 90 / ≥ 95 на категории и карточке; вёрстка совпадает с утверждёнными макетами.

**Спринт 4 — Корзина и заявка** (после Д5).
- Серверная корзина и объединение, оформление, `idempotency_key`, антиспам.
- Номера заказов, «Спасибо», уведомления.
- Лиды и модалки, Filament-ресурсы заказов и лидов.
- *DoD:* сценарии 1, 7, 8 проходят тестами и руками.

**Спринт 5 — B2B** (после Д6).
- Регистрация оптовика, модерация, ценовые группы, `PriceResolver` с ограничениями, две цены.
- ЛК: сводка, заявки, повтор, компания, избранное.
- Заказ списком, прайс XLSX, middleware.
- *DoD:* сценарии 3–5 проходят тестами; неодобренная компания не видит опт.

**Спринт 6 — Контент и SEO** (Д7).
- Страницы CMS, страница настроек, `MetaBuilder`, sitemap, robots, редиректы.
- Метрика с целями и cookie-баннер, сопутствующие товары, подборки на главной, пересчёт популярности, письма.
- *DoD:* обязательные страницы наполнены текстами заказчика; sitemap валиден; цели Метрики срабатывают.

**Спринт 7 — API Росхолода** (когда поставщик даст документацию и доступ).
- `RosholodApiSource`, авторизация.
- Фото через medialibrary.
- Характеристики: `AttributeValueParser`, новые характеристики создаются нефильтруемыми.
- Числовые остатки, дилерская цена, когда появится.
- Отчёт dry-run по сопоставлению GUID до переключения; перевод XML-профилей в резерв.
- *DoD:*
  - не меньше 99% товаров API сопоставлены с существующими по GUID;
  - фото и характеристики на витрине;
  - XML-профили выключены, но запускаются вручную.

**Спринт 8 — Прод.**
- Развёртывание по §17, HTTPS.
- Бэкапы с удалённой копией и проверкой восстановления, supervisor, cron, почта.
- Мониторинг ошибок в Telegram, нагрузочная проверка каталога, финальный прогон §19.

Запуск возможен на XML-источниках: спринт 7 выполняется, когда поставщик будет готов, до или после спринта 8.

---

## 19. Чек-лист приёмки

**Каталог**
- [ ] Импорт реальных выгрузок создаёт каталог без дублей: товары по GUID, зеркало категорий неактивно до проверки, бренды сопоставлены
- [ ] Повторный импорт без изменений даёт `skipped` или `unchanged`; ручные правки не затираются
- [ ] Обрезанный или подозрительно маленький файл не меняет каталог и приходит тревогой в Telegram
- [ ] Товар без остатков показан «Под заказ» и добавляется в корзину; пропавший из 3 снимков — «Снят с производства» и доступен по адресу
- [ ] Товар с ценой 0 показан «Цена по запросу» и в корзину не добавляется
- [ ] После подключения API: фото в WebP трёх размеров, характеристики разобраны в числа с единицами

**Витрина**
- [ ] Фильтры меняют выдачу без перезагрузки, отражаются в URL и работают без JS
- [ ] Поиск находит товар по полному или частичному (от 2 символов) артикулу, коду 1С и модели, в том числе в другой раскладке
- [ ] Карточка содержит цену или «Цену по запросу», наличие по складам без количества, характеристики (если есть), галерею или заглушку, микроразметку
- [ ] Мобильная версия на 360 px без горизонтального скролла; клавиатурная навигация и фокус работают; контраст соответствует §9

**Продажи**
- [ ] Заявка создаётся и приходит в Telegram и на почту в течение 30 с, в том числе во время импорта
- [ ] Номер заказа уникален, состав не меняется при изменении товара
- [ ] Корзина переживает закрытие браузера и объединяется при входе
- [ ] Повторная отправка формы не создаёт дубль заказа

**B2B**
- [ ] Клиент одобренной компании видит свою цену, гость и `pending` — розничную; название группы клиенту не показывается
- [ ] Оптовая цена не опускается ниже ограничений §7
- [ ] Заказ списком распознаёт артикулы и сообщает о проблемных строках
- [ ] Прайс выгружается с ценами конкретной группы

**Эксплуатация**
- [ ] В админке 2FA у администраторов и менеджеров, `/manage` не индексируется
- [ ] Бэкап создаётся ежедневно, копия хранится вне сервера, восстановление проверено
- [ ] Очереди переживают перезапуск сервера; импорт не задерживает уведомления
- [ ] Падение импорта приходит в Telegram с текстом ошибки
- [ ] `php artisan test` зелёный
- [ ] ПДн: сервер в РФ, согласие отдельным документом, cookie-баннер

---

## 20. Что нужно от заказчика

**Блокеры дизайна и запуска**
1. **Название магазина и логотип** (или задача на отрисовку текстового логотипа на Golos Text).
2. **Референсы для дизайна:** 3–5 сайтов, которые нравятся, и 2–3 прямых конкурента, у каждого — что именно нравится.
3. **Домен** (новый или поддомен) и **VPS в РФ** с HestiaCP.
4. **Реквизиты продавца, режим НДС** (цены с НДС или без), адрес самовывоза, график, телефоны, e-mail.
5. **Telegram:** бот через @BotFather и `chat_id` рабочей группы менеджеров.
6. **Юрист по 152-ФЗ:** согласие, политика, уведомление Роскомнадзора, cookie и Метрика, контакты клиентов в Telegram.
7. **Выбор локальной среды разработки** (§17.0) и установка с правами администратора.

**Цены**
8. Подтверждение, что цена в XML — РРЦ; обязательна ли РРЦ к соблюдению и можно ли продавать ниже неё, в том числе оптовикам.
9. `pricing.max_discount_without_purchase` и `pricing.min_margin_percent`.
10. Ценовые группы и скидки. Стартовые: Розница 0%, Опт-1 10%, Опт-2 15%, Сеть 20% — подтвердить.

**Вопросы поставщику по API**
11. Совпадает ли GUID товара в API с полем `ID` в `Catalog.xml` и `OstatkiYandex.xml`.
12. Пример ответа API на 5–10 реальных товаров до выхода документации.
13. Сроки документации и тестового доступа; способ авторизации; лимиты запросов.
14. Выборка изменений (`updated_since`) или вебхуки; частота обновления остатков и цен.
15. Фото: размер, формат, фон; можно ли хранить копии у себя.
16. Характеристики: можно ли отдавать значение и единицу раздельно; есть ли единый справочник ключей.
17. Как помечаются снятые с производства товары; есть ли статус «в пути» с датой поступления.

**Контент и логистика**
18. Сроки доставки со складов поставщика до Симферополя по складам; порог бесплатной доставки по городу.
19. Тексты: «Почему мы» (только подтверждённые факты), выгоды для оптовиков, обещание по времени перезвона, обязательные страницы.
20. Состав подборок «Кафе до 50 посадок», «Бар», «Кондитерский цех» — или поручение менеджеру после импорта.

---

## 21. Вне объёма MVP

Онлайн-оплата, интеграция с 1С:УТ, личные скидки клиента сверх группы, отзывы и рейтинги, сравнение товаров, блог, мультиязычность, калькулятор подбора кухни, фиды для Авито и Яндекс.Маркета, мобильное приложение, собственный склад и резервирование, электронный документооборот (УПД, ЭДО), автоподстановка реквизитов по ИНН, Meilisearch, автоматическое переписывание названий поставщика, CSP в блокирующем режиме.

Всё перечисленное совместимо с текущей схемой БД и добавляется без её переделки.
