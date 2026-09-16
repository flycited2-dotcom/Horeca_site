# Техническое задание: интернет-магазин оборудования и расходников HoReCa

**Версия:** 1.0
**Дата:** 15.09.2026
**Заказчик:** Алексей (Симферополь)
**Исполнитель:** Claude Code
**Кодовое имя проекта:** `horeca-shop`

---

## 0. Как работать с этим документом

Этот файл — источник истины. Перед каждым спринтом Claude Code читает раздел спринта и раздел «Модель данных», после выполнения — сверяется с чек-листом приёмки (раздел 19).

**Правила разработки (жёсткие):**

1. Никаких заглушек, `TODO`, `// implement later`, «примерного кода». Каждый файл создаётся в рабочем виде целиком.
2. Никаких выдуманных пакетов и API. Если пакет не установлен — сначала `composer require`, потом код.
3. Каждый спринт завершается рабочим состоянием: `php artisan test` зелёный, `npm run build` проходит, сайт открывается.
4. Все тексты интерфейса — на русском языке, в файлах `lang/ru/`. Хардкод русских строк в Blade запрещён только для витринных заголовков разделов; всё остальное — через `__()`.
5. Никаких изменений в `.env` из кода. Все новые переменные документируются в `.env.example`.
6. Миграции — только вперёд (`up`/`down` обе реализованы), данные не теряем.
7. Git: ветка на спринт (`feature/sprint-N-name`), коммиты атомарные, сообщение на русском в императиве.
8. Перед коммитом: `./vendor/bin/pint` (стиль), `php artisan test`.

---

## 1. Бизнес-контекст и цель

Продаём оборудование, инвентарь и расходники для сегмента **HoReCa** (отели, рестораны, кафе, бары, столовые, кондитерские, пищепроизводства) в Крыму и по РФ.

**Товарное наполнение** приходит от одного основного поставщика в виде XLS-файла с остатками и ценами. В течение ближайших суток появится личный кабинет поставщика — значит, источник данных станет вторым (API/выгрузка из ЛК). Архитектура импорта обязана быть **источник-агностичной с первого дня**.

**Две аудитории на одной витрине:**

| | B2C (розница) | B2B (опт) |
|---|---|---|
| Кто | частник, дачник, маленькая точка | ресторан, отель, сеть, снабженец |
| Цена | розничная, видна всем | оптовая по ценовой группе, видна после входа в ЛК и одобрения менеджером |
| Итог сессии | заявка на товар | заявка на товар + запрос счёта, реквизиты подставляются из карточки компании |

**Оплата онлайн в MVP не подключается.** Финал воронки — **заявка** (заказ в статусе «Новая»), уведомление менеджеру в Telegram и на e-mail, дальше менеджер работает руками. Архитектура заказа сразу проектируется так, чтобы платёжный шлюз (ЮKassa) добавлялся одним сервисом без переделки схемы БД (см. 10.5).

**Цель релиза:** работающая витрина с полным каталогом поставщика, живыми остатками, поиском, фильтрами, корзиной, оформлением заявки, B2B-кабинетом и админкой, задеплоенная на VPS с HestiaCP.

---

## 2. Роли и пользовательские сценарии

### 2.1 Роли

| Роль | Код | Права |
|---|---|---|
| Гость | `guest` | каталог, поиск, корзина, оформление заявки как физлицо/без регистрации |
| Розничный клиент | `retail` | то же + история заявок, избранное |
| Оптовый клиент | `wholesale` | оптовые цены, карточка компании, быстрый заказ по списку артикулов, повтор заказа, скачивание прайса под свою ценовую группу |
| Менеджер | `manager` | Filament: заказы, клиенты, модерация B2B-заявок, товары (без удаления), импорт |
| Администратор | `admin` | всё, включая настройки, наценки, пользователей, профили импорта |

### 2.2 Ключевые сценарии (E2E, каждый покрывается feature-тестом)

1. **Гость → заявка.** Главная → категория → фильтр «мощность 4–6 кВт» → карточка → «В корзину» → корзина → форма (имя, телефон, город, комментарий) → согласие с политикой → «Отправить заявку» → страница «Спасибо» с номером → Telegram менеджеру.
2. **Поиск по артикулу.** Ввод артикула поставщика в шапке → мгновенная выдача (до 300 мс) → карточка.
3. **B2B-регистрация.** «Стать оптовым клиентом» → форма (ИНН, название, контакты) → автоподстановка реквизитов по ИНН из ручного ввода → статус «На модерации» → менеджер в Filament ставит ценовую группу «Опт-1» и одобряет → письмо клиенту → на витрине цены пересчитались.
4. **Быстрый заказ списком.** B2B-кабинет → «Быстрый заказ» → вставка `артикул;количество` построчно или загрузка XLSX → превью распознанного → «Добавить всё в корзину».
5. **Повтор заказа.** ЛК → заказ №… → «Повторить» → корзина заполняется с учётом текущих цен и остатков, недоступные позиции подсвечиваются.
6. **Импорт.** Админ → «Импорт» → профиль «Основной поставщик, XLSX» → «Запустить» → фоновая задача → отчёт: создано/обновлено/скрыто/ошибок + возможность скачать лог.
7. **Товар кончился.** После импорта остаток 0 → на витрине «Под заказ», кнопка меняется на «Уточнить срок», товар не исчезает из выдачи, но опускается в сортировке.

---

## 3. Стек и обоснование выбора

**Выбор: Laravel 11 + Livewire 3 + Filament 3 + Tailwind CSS 3 + MySQL 8 + Redis.**

Почему именно так (мне нужно было выбрать самому — вот аргументы):

- **Laravel 11 + Filament 3** — у тебя уже работает `ritualb2b.ru` на этой связке, а значит нет расходов на изучение, одинаковый деплой, одинаковый бэкап, переиспользуемые куски (корзина, палитра, Filament-ресурсы). Админка B2B-магазина — это 60% работы, и Filament отдаёт её почти бесплатно: CRUD, фильтры, массовые действия, права, импорт/экспорт.
- **Server-side рендеринг (Blade + Livewire) вместо Next.js.** Каталог HoReCa живёт на органике: «пароконвектомат купить Симферополь». SSR + Blade даёт идеальный SEO без SSR-инфраструктуры Node в проде. На HestiaCP это просто PHP-FPM-домен, без `pm2`, без отдельного порта, без reverse-proxy-возни. Node нужен только на этапе сборки ассетов.
- **Livewire 3 для интерактива** (фильтры, корзина, быстрый поиск) — не надо строить отдельный REST API и второй фронтенд. Filament и так тянет Livewire, значит нет дублирования зависимостей.
- **MySQL 8**, а не SQLite и не PostgreSQL: в HestiaCP MySQL/MariaDB нативен (бэкапы, phpMyAdmin, пользователи БД из панели). SQLite в проде — та же ошибка, что вылезла в аудите `ritualb2b`, повторять её не будем.
- **Redis** — кэш каталога, сессии, очереди импорта. Если Redis на VPS нет, ставится `apt install redis-server`; фолбэк-конфиг с `database`-драйвером очереди обязан работать (см. 17.4).
- **Meilisearch не ставим в MVP.** Поиск — на MySQL (`FULLTEXT` с `ngram`-подобной нормализацией + `LIKE` по артикулу). Интерфейс `SearchEngineInterface` закладываем, чтобы позже подменить на Meilisearch без переписывания витрины.

**Версии (фиксируем в `composer.json` / `package.json`):**

```
PHP 8.3
laravel/framework ^11.0
livewire/livewire ^3.5
filament/filament ^3.2
phpoffice/phpspreadsheet ^2.1     // чтение XLS/XLSX
maatwebsite/excel ^3.1            // экспорт прайсов и выгрузок
spatie/laravel-sitemap ^7.2
spatie/laravel-medialibrary ^11.0 // изображения товаров + конвертации
spatie/laravel-permission ^6.7    // роли
intervention/image ^3.6
laravel/pint ^1.17 (dev)
pestphp/pest ^2.34 (dev)
barryvdh/laravel-debugbar (dev)
Node 20, Vite 5, Tailwind 3.4, Alpine.js 3
```

---

## 4. Архитектура и структура каталогов

```
app/
  Actions/
    Cart/AddToCart.php, UpdateQty.php, RemoveItem.php, MergeGuestCart.php
    Orders/CreateOrderFromCart.php, RepeatOrder.php, ChangeOrderStatus.php
    Pricing/CalculatePrice.php, CalculateCartTotals.php
  Console/Commands/
    ImportSupplierCommand.php        // php artisan supplier:import {profile?} {--dry-run}
    RebuildCatalogCacheCommand.php
    GenerateSitemapCommand.php
  Enums/
    OrderStatus.php, OrderType.php, DeliveryMethod.php, PaymentMethod.php,
    StockStatus.php, UserRole.php, CompanyStatus.php, ImportRunStatus.php,
    SourceType.php, AttributeType.php
  Filament/
    Resources/  (ProductResource, CategoryResource, BrandResource, OrderResource,
                 UserResource, CompanyResource, PriceTierResource, SupplierResource,
                 ImportProfileResource, ImportRunResource, AttributeResource,
                 PageResource, LeadResource, SettingResource)
    Pages/Dashboard.php, RunImport.php
    Widgets/OrdersStatsWidget.php, StockAlertsWidget.php, LatestOrdersWidget.php
  Http/
    Controllers/ (HomeController, CatalogController, ProductController,
                  SearchController, CartController, CheckoutController,
                  AccountController, PageController, SitemapController)
    Middleware/ (EnsureWholesaleApproved.php, TrackUtm.php)
    Requests/ (CheckoutRequest, WholesaleRegisterRequest, LeadRequest, QuickOrderRequest)
  Livewire/
    Catalog/ProductFilter.php, ProductGrid.php, SortSelect.php
    Cart/CartIcon.php, CartPage.php, AddToCartButton.php
    Search/HeaderSearch.php
    Account/QuickOrder.php
  Models/ (см. раздел 5)
  Services/
    Import/
      SupplierFeedInterface.php
      Sources/XlsxFileSource.php, XlsxUrlSource.php, SupplierApiSource.php
      RowMapper.php, RowNormalizer.php, ProductUpserter.php, ImportRunner.php
    Pricing/PriceResolver.php
    Search/SearchEngineInterface.php, MysqlSearchEngine.php
    Notify/TelegramNotifier.php
    Seo/MetaBuilder.php
  Support/
    Money.php, Slugger.php, Phone.php
resources/
  views/
    layouts/app.blade.php, layouts/account.blade.php
    home/, catalog/, product/, cart/, checkout/, account/, pages/, partials/
  css/app.css      // Tailwind + дизайн-токены
  js/app.js        // Alpine + мелкие утилиты
routes/web.php, routes/console.php
database/migrations, database/seeders, database/factories
tests/Feature, tests/Unit
storage/app/imports/    // исходные файлы поставщика с датой
```

**Слои:** контроллер → Action/Service → модель. Бизнес-логика в контроллерах запрещена. Запросы к БД для витрины — через методы-скоупы моделей и репозиторные методы в `CatalogQuery`.

---

## 5. Модель данных

MySQL 8, кодировка `utf8mb4_unicode_ci`, движок InnoDB. Ниже — состав таблиц. Типы указаны точно; Claude Code пишет миграции ровно по ним.

### 5.1 Пользователи и компании

**users**
`id` bigint PK · `name` string(150) · `email` string(150) unique · `phone` string(20) nullable index · `password` string · `role` enum(retail,wholesale,manager,admin) default retail · `company_id` bigint nullable FK companies · `price_tier_id` bigint nullable FK price_tiers · `is_active` bool default true · `email_verified_at` timestamp nullable · `last_login_at` timestamp nullable · `remember_token` · timestamps

**companies**
`id` · `user_id` bigint FK users (владелец) · `legal_name` string(255) · `brand_name` string(255) nullable (вывеска заведения) · `inn` string(12) index · `kpp` string(9) nullable · `ogrn` string(15) nullable · `legal_address` string(500) nullable · `delivery_address` string(500) nullable · `bank_name` string(255) nullable · `bik` string(9) nullable · `account` string(20) nullable · `corr_account` string(20) nullable · `contact_person` string(150) · `phone` string(20) · `email` string(150) · `segment` enum(restaurant,cafe,bar,hotel,canteen,bakery,production,retail_chain,other) · `status` enum(pending,approved,rejected,blocked) default pending · `manager_comment` text nullable · `approved_at` timestamp nullable · `approved_by` bigint nullable FK users · timestamps

**price_tiers** (ценовые группы)
`id` · `name` string(100) («Розница», «Опт-1», «Опт-2», «Сеть») · `slug` unique · `discount_percent` decimal(5,2) default 0 — скидка от розничной цены · `min_order_amount` decimal(12,2) default 0 · `is_default` bool · `sort` smallint · timestamps

### 5.2 Каталог

**categories**
`id` · `parent_id` nullable FK self · `name` string(255) · `slug` string(255) unique · `description` text nullable · `image` string nullable · `icon` string(64) nullable (имя SVG-иконки) · `meta_title`, `meta_description`, `h1` nullable · `seo_text` longtext nullable (текст под листингом) · `sort` int default 0 · `is_active` bool default true · `products_count` int default 0 (денормализация, пересчёт после импорта) · timestamps
Индексы: `parent_id`, `slug`, составной `is_active,sort`.

**brands**
`id` · `name` · `slug` unique · `logo` nullable · `country` string(64) nullable · `description` text nullable · `is_active` bool · timestamps

**products**
`id` ·
`supplier_id` FK suppliers ·
`sku` string(64) — артикул поставщика, **unique вместе с supplier_id** ·
`external_id` string(64) nullable index — id из ЛК поставщика (появится позже) ·
`name` string(500) · `slug` string(500) unique ·
`category_id` FK categories nullable · `brand_id` FK brands nullable ·
`short_description` text nullable · `description` longtext nullable ·
`unit` string(16) default 'шт' ·
`purchase_price` decimal(12,2) default 0 — закупка от поставщика ·
`retail_price` decimal(12,2) default 0 — расчётная розничная ·
`old_price` decimal(12,2) nullable — для зачёркнутой цены ·
`stock_status` enum(in_stock,low,on_order,out) default on_order — **точного количества поставщик не даёт, поля `stock` в products нет**; детализация по складам в `product_stocks` ·
`is_active` bool default true · `is_visible` bool default true (ручное скрытие менеджером, импорт его не трогает) ·
`is_new` bool · `is_hit` bool ·
`weight` decimal(8,3) nullable (кг) · `length`,`width`,`height` int nullable (мм) ·
`power_kw` decimal(6,3) nullable · `voltage` enum(220,380) nullable ·
`warranty_months` smallint nullable ·
`meta_title`,`meta_description`,`h1` nullable · `seo_text` longtext nullable ·
`views` int default 0 · `rating_sort` int default 0 (вес для сортировки «по популярности») ·
`source_hash` char(32) nullable — md5 строки импорта, чтобы не писать в БД без изменений ·
`last_synced_at` timestamp nullable ·
timestamps + softDeletes
Индексы: `(supplier_id,external_id)` unique, `(supplier_id,sku)` обычный (артикул бывает пустым и неуникальным), `slug` unique, `(is_active,is_visible,stock_status)`, `category_id`, `brand_id`, FULLTEXT(`name`,`short_description`).

**product_images** — `id` · `product_id` FK cascade · `path` · `alt` nullable · `sort` · `is_main` bool · timestamps
(Если используется medialibrary — коллекция `images` + конверсии `thumb 300x300`, `card 600x600`, `full 1400x1400`, формат WebP. Тогда таблица не нужна; решение принимает Claude Code, но **одно из двух**, не оба.)

**attributes** — `id` · `name` · `slug` unique · `unit` string(16) nullable · `type` enum(string,number,bool,select) · `is_filterable` bool · `is_main` bool (показывать в карточке товара в блоке «Главное») · `sort` · timestamps

**attribute_values** — `id` · `attribute_id` FK · `value` string(255) · `slug` · `sort`

**attribute_product** — `product_id` FK · `attribute_id` FK · `attribute_value_id` nullable FK · `value_string` nullable · `value_number` decimal(12,3) nullable · `value_bool` bool nullable · PK(`product_id`,`attribute_id`)

**product_prices** (персональная цена под ценовую группу, приоритет выше расчётной)
`id` · `product_id` FK · `price_tier_id` FK · `price` decimal(12,2) · unique(`product_id`,`price_tier_id`)

### 5.3 Импорт

**suppliers** — `id` · `name` · `slug` unique · `type` enum(file,url,api) · `config` json (хост, токен, логин — шифруется через `encrypted:json` cast) · `markup_percent` decimal(5,2) default 35 · `round_to` smallint default 10 (округление цены до N рублей вверх) · `is_active` bool · `last_import_at` timestamp nullable · timestamps

**import_profiles** — `id` · `supplier_id` FK · `name` · `source_type` enum(upload,url,api) · `source_url` string(500) nullable · `sheet_name` string(100) nullable · `header_row` smallint default 1 · `column_map` json · `defaults` json (категория по умолчанию, ед. изм.) · `rules` json (фильтры строк: пропускать пустые, пропускать цену=0) · `is_active` bool · `schedule` string(32) nullable (cron-выражение) · timestamps

**import_runs** — `id` · `import_profile_id` FK · `user_id` nullable · `status` enum(queued,running,success,failed,partial) · `file_path` nullable · `file_hash` char(32) nullable · `rows_total`,`created`,`updated`,`unchanged`,`hidden`,`errors` int default 0 · `log` longtext nullable (JSON-массив проблемных строк) · `started_at`,`finished_at` nullable · timestamps

**warehouses** (склады поставщика, создаются импортом автоматически)
`id` · `supplier_id` FK · `name` string(150) · `slug` · `city` string(150) nullable · `is_visible` bool default true (показывать ли клиенту название склада) · `sort` smallint · timestamps · unique(`supplier_id`,`name`)

**product_stocks** (наличие по складам, перезаписывается каждым прогоном остатков)
`id` · `product_id` FK cascade · `warehouse_id` FK cascade · `status` enum(in_stock,low,on_order,out) · `raw_value` string(64) — исходный текст из выгрузки, для разбора спорных случаев · `orderable` bool · `synced_at` timestamp · unique(`product_id`,`warehouse_id`)

### 5.4 Продажи

**carts** — `id` · `user_id` nullable FK · `session_id` string(100) nullable index · `expires_at` · timestamps
**cart_items** — `id` · `cart_id` FK cascade · `product_id` FK · `qty` int · `price` decimal(12,2) (снимок на момент добавления) · timestamps · unique(`cart_id`,`product_id`)

**orders**
`id` · `number` string(16) unique (формат `HR-260915-0042`) · `user_id` nullable · `company_id` nullable ·
`type` enum(retail,wholesale) ·
`status` enum(new,processing,confirmed,invoiced,paid,shipped,completed,canceled) default new ·
`customer_name`,`phone`,`email` · `inn` string(12) nullable ·
`delivery_method` enum(pickup,transport_company,courier_city) ·
`delivery_city` string(150) nullable · `delivery_address` string(500) nullable · `tk_name` string(100) nullable ·
`payment_method` enum(invoice,cash,card_on_delivery,online) default invoice ·
`comment` text nullable · `manager_comment` text nullable · `manager_id` nullable FK users ·
`subtotal`,`discount`,`total` decimal(12,2) ·
`invoice_path` string(500) nullable ·
`payment_id` string(64) nullable · `paid_at` timestamp nullable — **поля под будущий ЮKassa, в MVP всегда null** ·
`utm` json nullable · `ip` string(45) nullable · `user_agent` string(500) nullable ·
timestamps
Индексы: `status`, `created_at`, `user_id`.

**order_items** — `id` · `order_id` FK cascade · `product_id` nullable (SET NULL) · `sku` · `name` string(500) · `qty` int · `price` decimal(12,2) · `sum` decimal(12,2) · timestamps
**order_status_logs** — `id` · `order_id` FK · `from` · `to` · `user_id` nullable · `comment` nullable · timestamps

**leads** — `id` · `type` enum(callback,question,price_request,quick_order) · `name` · `phone` · `email` nullable · `product_id` nullable · `message` text nullable · `status` enum(new,in_work,done) · `utm` json nullable · timestamps

**favorites** — `user_id`/`session_id` · `product_id` · timestamps

### 5.5 Контент

**pages** — `id` · `slug` unique · `title` · `content` longtext · `meta_title`,`meta_description` · `is_active` · `sort` · timestamps
(Обязательные страницы: `dostavka`, `oplata`, `garantiya`, `optovikam`, `o-kompanii`, `kontakty`, `politika-konfidencialnosti`, `polzovatelskoe-soglashenie`.)

**settings** — key-value JSON: телефоны, e-mail, адрес, график, соцсети, Telegram chat_id, реквизиты продавца, тексты шапки/подвала, режим «показывать цены гостям».

---

## 6. Импорт от поставщика (ядро системы)

### 6.1 Источник: Росхолод (проверено на реальных файлах 15.09.2026)

Поставщик — **Росхолод**, дилерский портал «МиР», `rosholod.org`. Выгрузки лежат по постоянным прямым ссылкам и отдаются без авторизации. Страница со списком: `https://rosholod.org/price-list`.

| Файл | URL | Что внутри | Время генерации |
|---|---|---|---|
| Каталог | `/price-lists/Catalog.xml` | дерево категорий + карточки товаров с ценой и брендом, **без остатков** | 05:00 |
| Остатки | `/price-lists/Ostatki.xml` | карточки + блок складов с наличием, **без категорий** | 14:00 |
| Прайс | `/price-lists/rosholod_price.xlsx` | табличный прайс | — |
| Прайс импорт | `/price-lists/rosholod_price_import.xlsx` | импортные позиции отдельно | — |
| Остатки | `/price-lists/Ostatki.xls` | остатки таблицей | — |
| Остатки по столбцам | `/price-lists/OstatkiPoStolbcam.xls` | остатки в столбцовой структуре | — |
| Остатки для Маркета | `/price-lists/OstatkiYandex.xml` | **основной источник наличия**: карточки + склады + единицы измерения, теги латиницей | 14:00 |

**Рабочая связка — `Catalog.xml` + `OstatkiYandex.xml`.** Каталог даёт дерево категорий и привязку товара к категории. «Яндексовый» файл, вопреки названию, полноценным YML для Маркета не является (нет `<picture>`, нет `<param>`), но именно он удобнее всех для разбора: имена тегов латинские и читаемые, есть единица измерения и нормальные названия складов. `Ostatki.xml` содержит то же наличие, но с кириллическими тегами — держим его как резервный профиль. XLSX/XLS — второй резерв, профили создаются, в расписании выключены.

Склейка всех источников — по GUID `ID`, он одинаков во всех трёх XML (проверено на совпадающих позициях).

```
SupplierFeedInterface
   ├─ XmlUrlSource      Catalog.xml       — категории и привязка, 1 раз в сутки после 05:20
   ├─ XmlUrlSource      OstatkiYandex.xml — наличие, цена, единицы, каждые 2 часа
   ├─ XmlUrlSource      Ostatki.xml       — резерв на случай поломки предыдущего, выключен
   ├─ XlsxUrlSource     второй резерв, выключен по умолчанию
   ├─ XlsxFileSource    ручная загрузка в админке
   └─ SupplierApiSource ЛК Росхолода — спринт 7, тот же интерфейс
```

```php
interface SupplierFeedInterface
{
    /** @return iterable<int, array<string, scalar|null>> построчные сырые данные */
    public function rows(ImportProfile $profile): iterable;

    public function fingerprint(ImportProfile $profile): string; // md5 источника: не гоняем импорт зря

    public function supports(SourceType $type): bool;
}
```

Витрина **никогда** не знает, откуда пришли данные. Появится API в ЛК — пишется только `SupplierApiSource` и новый профиль, остальной код не трогается.

#### Формат XML: то, что ломает наивный парсер

1. **Кодировка `windows-1251`.** Читать через `XMLReader` с явной перекодировкой в UTF-8, не через `simplexml_load_file` на сыром потоке. Проверка: если в имени товара появились `?` или `Ð`, перекодировка сделана неправильно — падаем с ошибкой, а не пишем мусор в БД.
2. **Имена тегов различаются между файлами.** В `OstatkiYandex.xml` они латинские и осмысленные: `item` · `name` · `fullname` · `articule` · `iditem` · `exttext` · `URL` · `manufacture` · `manufactureid` · `priceinruble` · `price` · `currencyitem` · `unit` · `ID` · `trademark` · `stocks` → `stock` → (`namestock`, `balance`, `unitstock`). В `Catalog.xml` и `Ostatki.xml` теги внутри карточки **кириллические** (`Артикул`, `Цена`, `Склады`) при латинской YML-обёртке (`yml_catalog`, `shop`, `categories`, `category`, `offers`) и двух латинских полях внутри — `categoryId` и `ID`. Всё это выгрузки 1С 8.3.27, а не канонический YML: готовая YML-библиотека их не разберёт.
3. **Имена тегов не хардкодить.** Первый прогон профиля запускается в режиме разведки: парсер читает первые 20 карточек, собирает фактический список тегов после перекодировки и показывает его в мастере маппинга — менеджер сопоставляет теги с полями системы ровно так же, как колонки XLS. Карта сохраняется в `column_map` профиля. Поставщик добавит поле — мастер это покажет, код останется прежним.
4. `Ostatki.xml` объявляет собственное пространство имён (`xmlns="https://www.1cteh.ru/xml/rosholod/ext_catalog_stock"`), `Catalog.xml` — нет. Парсер обязан работать с обоими, то есть сопоставлять локальные имена узлов, игнорируя namespace.

#### Состав карточки

**Catalog.xml** (поля идут в фиксированном порядке): модель · полное наименование · артикул · код 1С · описание · URL изображения · цена · `categoryId` · валюта · `ID` · производитель.

**Ostatki.xml**: наименование · полное наименование · артикул · код 1С · производитель · описание · URL изображения · GUID (пустой) · базовая цена · цена · валюта · `ID` · торговая марка · блок складов.

Блок складов повторяется по количеству складов и содержит название склада, остаток и флаг доступности к заказу.

#### Ключи и сопоставление

- **`ID` (GUID номенклатуры 1С) — единственный надёжный ключ.** Он одинаков в обоих файлах: одна и та же позиция имеет один GUID и в каталоге, и в остатках. Пишем его в `products.external_id` с уникальным индексом, склейка двух источников идёт только по нему.
- **Артикул бывает пустым** (реально встречается `<Артикул/>`). Использовать его как первичный ключ нельзя. Правило: `sku` = артикул, если он есть; иначе код 1С. Код 1С присутствует всегда.
- **`categoryId` содержит НАЗВАНИЕ категории, а не её GUID**, хотя в блоке `<categories>` категории описаны через `id`/`parentId` с GUID. Сопоставление идёт по имени.
- **Имена категорий не уникальны.** В дереве есть разные GUID с одинаковым текстом названия у одного родителя. Правило: при неоднозначном совпадении берём первую по порядку, пишем конфликт в лог прогона с перечислением GUID-кандидатов, товар в витрину пускаем. Ручное разведение — в админке.
- Дерево категорий — двухуровневое в текущей выгрузке (`parentId` ссылается только на корни), но код обязан поддерживать произвольную вложенность: поставщик добавит уровень — ничего не сломается.

#### Чего в выгрузках нет (и что с этим делать)

- **Фотографий нет ни в одной из семи выгрузок.** Поле URL изображения есть во всех трёх XML и пустое везде; `<picture>` отсутствует даже в «яндексовом» файле. Следствия: витрина проектируется так, чтобы жить без фото не уродливо (заглушка — плашка с брендом и моделью на стальном фоне, не серый прямоугольник «нет фото»), загрузка картинок делается вручную через админку и через отдельный источник, когда он появится. Фото — не блокер запуска, но первым делом спрашиваем поставщика, отдают ли они архив изображений: на их собственном сайте карточки с фотографиями, значит база существует.
- **Цена может быть нулевой при живом товаре** — в проверенных файлах таких позиций много. Ноль нельзя пропускать через наценку и выводить как `0 ₽`. Правило: `price = 0` → товар публикуется со статусом **«Цена по запросу»**, кнопка «В корзину» заменяется на «Запросить цену» (лид типа `price_request`), в сортировке по цене такие позиции идут последними, в фильтр по цене не попадают.
- **Характеристик в структурированном виде нет** — ни `<param>`, ни аналога. Все параметры зашиты в полное наименование и описание: габариты (`1120×700×872 мм`), температурный режим (`+5…+15 °С`), объём, мощность в кВт, напряжение (`400В`, `230В`), количество полок, материал (`AISI 430`). Пишем фоновый job `ExtractAttributesFromName`, который регулярками достаёт габариты, объём, мощность, напряжение, температурный диапазон и материал и раскладывает в `attribute_product`. Точность не 100% — в админке у характеристики есть флаг «распознано автоматически», менеджер правит, правка попадает в `locked_fields`.
- **Точного количества нет.** Остаток по складу приходит текстом: `0`, `В наличии`, `Много`. Значит, витрина не имеет права писать «осталось 3 шт» — только статус. Это влияет на UX карточки и на корзину: количество не ограничиваем остатком, а показываем статус и предупреждение «наличие уточняет менеджер».

#### Цены: открытый вопрос, который нельзя закрывать догадкой

Цена в публичных выгрузках присутствует в двух полях (базовая и основная), в проверенных карточках они совпадают. Дилерская это цена или розничная — по самим файлам определить невозможно. До подтверждения из ЛК поставщика система считает её **закупочной** (`purchase_price`) и накручивает наценку. Если выяснится, что это уже РРЦ, меняется одно число в настройках поставщика (`markup_percent = 0`) и добавляется поле скидки дилера — схема БД не меняется.

Форматы чисел в двух файлах разные: в каталоге разряды разделены неразрывным пробелом (`0xA0` в cp1251) и дробной части нет; в остатках — обычное десятичное с точкой (`18910.57`). Нормализатор обязан снимать оба варианта.

### 6.2 Маппинг колонок для XLS/XLSX-профилей (резервный путь)

Механизм общий: и для колонок таблицы, и для тегов XML (см. 6.1, пункт 3) профиль импорта хранит `column_map` вида:

```json
{
  "sku":            "Артикул",
  "name":           "Наименование",
  "brand":          "Бренд",
  "category_path":  "Группа",
  "purchase_price": "Цена, руб",
  "stock":          "Остаток",
  "unit":           "Ед.изм",
  "weight":         "Вес, кг",
  "power_kw":       "Мощность, кВт",
  "voltage":        "Напряжение",
  "image_url":      "Фото",
  "description":    "Описание"
}
```

В админке — **страница «Импорт» с мастером**: загрузили файл → система читает `header_row`, показывает найденные заголовки и по 5 примеров значений из каждой колонки → менеджер сопоставляет их с полями системы через селекты → карта сохраняется в профиль. Повторные импорты идут без мастера. Обязательные для маппинга поля: `sku`, `name`, `purchase_price`, `stock`. Остальные — опциональные.

`category_path` поддерживает вложенность через разделитель (` / `, `>`, `\`) — разделитель настраивается в `rules`. Категории создаются автоматически, если не найдены, с `is_active = false`, чтобы менеджер их проверил и включил (иначе на витрину полезет мусорное дерево).

### 6.3 Алгоритм прогона (`ImportRunner`)

1. Создать `import_run` в статусе `running`, зафиксировать `started_at`.
2. Получить источник, посчитать `fingerprint`. Если совпал с последним успешным и не передан флаг `--force` — завершить со статусом `success`, `unchanged = rows_total`, записать в лог «источник не изменился».
3. Сохранить исходный файл в `storage/app/imports/{supplier_slug}/{Y-m-d_His}.xlsx` (хранить 30 последних, старые удалять).
4. Читать построчно **чанками по 500 строк** через `PhpSpreadsheet` в режиме `setReadDataOnly(true)` + `ReadFilter` по чанкам — файл на 50 000 строк не должен съедать больше 256 МБ.
5. Для каждой строки: `RowMapper` (сырые заголовки → поля) → `RowNormalizer`:
   - `sku`: trim, uppercase, убрать неразрывные пробелы;
   - цены: снять неразрывный пробел (`0xA0` после перекодировки — `\u{00A0}`) и обычные пробелы, заменить запятую на точку, убрать `руб.`/`₽`, привести к `decimal`; `null`/пусто → 0; отрицательная цена → строка в ошибки;
   - остаток: приходит текстом и по каждому складу отдельно. Карта: `0`, `нет`, `-`, пусто → `out`; `Мало` → `low`; `В наличии`, `Есть`, `Много` → `in_stock`; `Ожидается` → `incoming` (отдельный статус «в пути», на витрине это не то же самое, что «под заказ»). Неизвестное значение → `on_order` + запись в лог (чтобы расширить карту, а не молча потерять товар). Сводный статус товара — лучший по всем складам: есть хоть где-то `in_stock` → товар в наличии, иначе `incoming`, иначе `out`;
   - единица измерения берётся из поля склада (`шт`, `м`, `кг`) и пишется в `products.unit`; пусто → `шт`;
   - `name`: схлопнуть двойные пробелы, обрезать до 500;
   - `voltage`: `~220`, `220В`, `1ф` → 220; `380`, `3ф` → 380;
   - строки без `sku` или без `name` → в лог ошибок, не прерывая прогон.
6. Считать `source_hash = md5(json_encode(нормализованная строка))`. Если у товара тот же хеш — `unchanged++`, только обновить `last_synced_at`.
7. `ProductUpserter` в транзакции на чанк:
   - поиск по `(supplier_id, external_id)` — GUID из выгрузки; fallback на `(supplier_id, sku)` только для XLS-профилей, где GUID не приходит;
   - **создание:** заполнить всё, сгенерировать уникальный `slug` (`Str::slug` от name + sku при коллизии), `is_active = true`, `is_visible = true`;
   - **обновление:** обновлять только «поставщицкие» поля — `name` (если менеджер не редактировал вручную, см. ниже), `purchase_price`, `stock`, `weight`, характеристики. **Никогда не перезаписывать** `is_visible`, `meta_*`, `seo_text`, `description`, если они заполнены вручную, `category_id`, если товар был вручную перемещён.
   - защита ручных правок: таблица `products` получает поле `locked_fields` json — список имён полей, которые менеджер отредактировал в админке; `ProductUpserter` их пропускает. Filament при сохранении товара дописывает изменённые поля в `locked_fields`.
8. Пересчитать `retail_price` = `PriceResolver::retailFromPurchase(purchase_price, supplier.markup_percent, supplier.round_to)`.
9. Обновить `stock_status`: `stock = 0 → on_order`; `1..3 → low`; `>3 → in_stock`. Порог `low` — в настройках.
10. Товары этого поставщика, не встреченные в файле: `stock = 0`, `stock_status = out`, `is_active` **не трогаем** (товар остаётся по своему URL, чтобы не терять позиции в поиске), в карточке — «Снят с производства / уточняйте наличие». Если товар отсутствовал в 3 прогонах подряд — `is_active = false` (поле `missing_runs` int в products).
11. Изображения: код скачивания пишем сразу (`DownloadProductImage` — таймаут 10 с, лимит 5 МБ, проверка реального MIME, конверсия в WebP трёх размеров), но в выгрузках Росхолода поле URL пустое, поэтому job просто не находит работы. Ошибка скачивания никогда не ломает импорт. Дополнительно после прогона запускается `ExtractAttributesFromName` (см. 6.1) — характеристики из наименования.
12. Финал: пересчитать `categories.products_count`, сбросить кэш каталога (теги `catalog`), записать статистику в `import_run`, отправить Telegram-сводку:
    `Импорт «Основной поставщик» завершён: 12 430 строк, +38 новых, 1 204 обновлено, 22 скрыто, 3 ошибки. 4 мин 12 с.`

### 6.4 Режимы запуска

- Кнопка «Запустить импорт» в Filament (ставит job в очередь, страница показывает прогресс через polling `import_runs`).
- `php artisan supplier:import {profile_id} {--dry-run} {--force}` — `dry-run` выполняет всё, кроме записи в БД, и выдаёт отчёт.
- Планировщик: каждые 3 часа с 07:00 до 22:00 по МСК для профилей с `schedule`.
- Загрузка файла вручную через Filament (`FileUpload`, до 64 МБ).

### 6.5 Требования к устойчивости

- Импорт не должен блокировать витрину: очередь `imports`, отдельный воркер.
- Повторный запуск при уже идущем прогоне — запрещён (замок `Cache::lock('import:'.$profileId, 3600)`).
- Любая ошибка строки — в `log`, максимум 500 записей, остальное в файл.
- Прогон падает целиком только если не удалось прочитать источник или ошибок > 30% строк.

---

## 7. Ценообразование

```
Закупка (purchase_price, из файла поставщика)
   ↓ наценка поставщика markup_percent, округление вверх до round_to
Розничная цена (retail_price)  ← видят гости и retail-клиенты
   ↓ скидка ценовой группы price_tier.discount_percent
Оптовая цена ← видят одобренные wholesale-клиенты
   ↑ переопределяется product_prices (точечная цена на товар для группы)
```

`PriceResolver::for(Product $product, ?User $user): Price`

Порядок разрешения:
1. Если пользователь — одобренный `wholesale` и есть запись в `product_prices` для его `price_tier_id` → эта цена.
2. Иначе если одобренный `wholesale` → `retail_price * (1 - discount_percent/100)`, округление вверх до рубля.
3. Иначе → `retail_price`.

Правила отображения:
- Гостю и retail всегда видна розничная цена (скрывать цены — плохо для SEO и конверсии).
- Одобренному оптовику в карточке и листинге показываются **обе**: перечёркнутая розничная и своя оптовая с бейджем «Ваша цена, Опт-1».
- Пользователю `wholesale` со статусом `pending` — розничная цена + плашка «Оптовые цены станут доступны после проверки заявки».
- Цена в корзине фиксируется на момент добавления (`cart_items.price`), но при открытии корзины сверяется с актуальной; при расхождении — жёлтая плашка «Цена изменилась: было / стало» и пересчёт.
- Все деньги в БД — `decimal(12,2)`, в PHP — целые копейки через `Support/Money`. Флоаты в расчётах запрещены.

**Экспорт прайса** для оптовика: кнопка в ЛК «Скачать прайс XLSX» — генерация через `maatwebsite/excel` с его ценами, остатками, артикулами; кэш файла на 1 час.

---

## 8. Витрина: страницы и маршруты

```
GET  /                                   Главная
GET  /catalog                            Все категории
GET  /catalog/{category:slug}            Листинг категории (+ дочерние)
GET  /product/{product:slug}             Карточка товара
GET  /brands, /brands/{brand:slug}       Бренды
GET  /search?q=                          Результаты поиска
GET  /cart                               Корзина
GET  /checkout                           Оформление заявки
POST /checkout                           Создание заявки
GET  /checkout/success/{number}          Спасибо + номер заявки
GET  /login /register /password/*        Аутентификация
GET  /wholesale                          Лендинг «Оптовым клиентам» + форма
POST /wholesale                          Заявка на опт
GET  /account                            ЛК: сводка
GET  /account/orders, /account/orders/{order}
GET  /account/company                    Реквизиты компании (редактирование)
GET  /account/quick-order                Быстрый заказ списком
GET  /account/price                      Скачивание прайса
GET  /favorites
GET  /{page:slug}                        Статические страницы (catch-all, последним)
GET  /sitemap.xml, /robots.txt
```

### 8.1 Главная

Секции сверху вниз:
1. **Шапка** (sticky): логотип, телефон кликабельный, поиск с мгновенной выдачей, вход/ЛК, избранное, корзина со счётчиком.
2. **Панель подбора** вместо баннера-карусели: слева дерево из 8 корневых категорий с иконками, справа блок «Соберём кухню под задачу» — три сценария (кафе до 50 посадок / бар / кондитерский цех), каждый ведёт в подборку. Ниже — поле «Знаю артикул» с моментальным поиском.
3. Полосы: «В наличии на складе в Симферополе», «Хиты», «Новинки» — горизонтальные ленты карточек.
4. Блок «Почему мы»: 4 факта без иконок-клипарта (сроки поставки, гарантия, работа по 44-ФЗ/безналу, сервис).
5. Популярные бренды.
6. SEO-текст (из настроек), футер с картой категорий.

### 8.2 Листинг категории

- Слева — фильтры (Livewire, без перезагрузки, с обновлением query-string и `pushState`):
  цена (двойной слайдер), наличие (чекбокс «Только в наличии» — по умолчанию **включён**), бренд (мультиселект с поиском), напряжение 220/380, мощность (диапазон), + динамические фильтры из `attributes.is_filterable` для этой категории.
- Сортировка: по популярности (default), по цене ↑↓, по новизне, по названию.
- Пагинация 24 товара, кнопка «Показать ещё» + классическая пагинация для SEO (`rel=next/prev`).
- Товары `out`/`on_order` всегда в конце выдачи.
- Карточка товара в листинге: фото (WebP, `loading="lazy"`, фиксированный `aspect-ratio`, без CLS), бренд, название в 2 строки, ключевой параметр (мощность/объём), статус наличия, цена, кнопка «В корзину» / «Узнать срок», иконка «в избранное».
- Хлебные крошки + `BreadcrumbList` микроразметка.

### 8.3 Карточка товара

Слева — галерея (главное фото + миниатюры, зум по клику, свайп на мобиле). Справа — блок покупки: артикул, бренд, статус наличия с количеством («более 10 шт»), цена (для оптовика — две цены), счётчик количества с шагом из `unit`, «В корзину», «Купить в 1 клик» (быстрая заявка: только телефон), «Скачать КП» позже.

Ниже — табы: Описание · Характеристики (таблица из `attribute_product`, главные вынесены в правый блок) · Доставка и оплата (из настроек) · Гарантия.
Затем — «Похожие товары» (та же категория, ±30% цены) и «Сопутствующие» (ручная связь `related_products`, спринт 6).

Микроразметка `Product` + `Offer` (`availability`, `price`, `priceCurrency: RUB`, `sku`, `brand`).

### 8.4 Поиск

- Мгновенная выдача в шапке (Livewire, debounce 250 мс, минимум 2 символа): до 6 товаров + до 3 категорий.
- Приоритет: точное совпадение артикула → начало названия → вхождение в название → FULLTEXT.
- Нормализация запроса: раскладка (лат↔кир), «ё»→«е», пробелы/дефисы в артикулах.
- Страница `/search` с теми же фильтрами, что и листинг.
- Пустая выдача — не тупик: «Ничего не нашли по запросу X» + форма «Найдём за вас: оставьте телефон» + топ категорий.

---

## 9. Дизайн-система

**Субъект:** профессиональное кухонное оборудование. Материалы отрасли — нержавеющая сталь, матовый металл, синее пламя горелки. Оттуда и берём язык: холодный стальной фон, графитовая типографика, один живой акцент цвета пламени. Никаких кремовых фонов, терракоты, стеклянных карточек и градиентных заливок ради красоты.

**Токены (в `tailwind.config.js` + CSS-переменные в `resources/css/app.css`):**

```
--steel-50:  #F4F6F7   фон страницы
--steel-100: #E7EBEE   фон секций, инпуты
--steel-200: #D3D9DE   границы, разделители (hairline 1px)
--steel-400: #8A959E   вторичный текст, подписи
--graphite:  #15191D   основной текст, шапка, футер
--flame:     #0B5FFF   акцент: кнопки, активные фильтры, ссылки
--flame-700: #0847C4   hover/active
--stock:     #1F8A5F   «в наличии»
--alert:     #C2410C   «под заказ», ошибки формы
--surface:   #FFFFFF   карточки
```

**Типографика:** заголовки — **Golos Text** 600/700 (нативная кириллица, инженерная геометрия, не дефолтный Inter); текст — **Inter** 400/500. Подключение через `@fontsource` локально (не Google CDN — санкционные риски и скорость), `font-display: swap`.
Шкала: 13 / 14 / 16 / 18 / 22 / 28 / 36 / 48 px, line-height 1.5 для текста, 1.15 для заголовков. Длина строки в текстовых блоках ≤ 72 символа.

**Форма элементов:** `radius: 6px` для кнопок/инпутов, `4px` для карточек, `0` для таблиц характеристик. Тени по умолчанию нет — карточка отделена границей `1px var(--steel-200)`; тень `0 4px 16px rgba(21,25,29,.10)` появляется только на hover и у выпадающих меню. Это сознательно против «SaaS-набора одинаковых скруглённых карточек с мягкой тенью».

**Сетка:** контейнер 1320 px, 12 колонок, gutter 24 px. Листинг: 4 карточки в ряд на десктопе, 3 на планшете, 2 на мобиле. Выравнивание — по левому краю везде; центрируется только страница «Спасибо» и пустые состояния.

**Движение:** только отклик на действие — раскрытие фильтра, добавление в корзину (счётчик пульсирует один раз), открытие галереи. Никаких появлений секций при скролле. `prefers-reduced-motion: reduce` отключает всё.

**Квалификационный минимум:** mobile-first, тач-цели ≥ 44 px, видимый фокус клавиатуры (`outline: 2px solid var(--flame)`), контраст текста ≥ 4.5:1, все изображения с `alt`, формы с `<label>`, семантические теги, работа без JS для чтения каталога.

**Копирайтинг:** кнопки говорят, что произойдёт — «Добавить в корзину», «Отправить заявку», «Запросить счёт». Не «Отправить», не «Submit». Ошибка формы объясняет, что чинить: «Телефон не распознан. Формат: +7 978 123-45-67». Пустая корзина — приглашение: «В корзине пусто. Начните с холодильного оборудования или введите артикул».

---

## 10. Корзина и оформление заявки

### 10.1 Корзина

- Серверная (таблица `carts`), а не localStorage: у `ritualb2b` корзина в localStorage — здесь так не делаем, потому что нужна сквозная корзина между устройствами для B2B и аналитика брошенных корзин.
- Гость: корзина привязана к `session_id`, живёт 30 дней. При входе — `MergeGuestCart`: позиции суммируются, цены пересчитываются под роль.
- Действия: изменение количества (±, ручной ввод), удаление с «Вернуть» (undo 10 с), очистка, «Сохранить как шаблон» (B2B, спринт 6).
- Показывается: сумма, количество позиций, суммарный вес, ориентир «бесплатная доставка по городу от 30 000 ₽» (из настроек).

### 10.2 Оформление

Одна страница, три блока, без многошагового визарда:
1. **Контакты.** Имя, телефон (маска `+7 (___) ___-__-__`, валидация RU), e-mail, «Я представляю юрлицо» → раскрывается ИНН + название. Для авторизованных всё предзаполнено.
2. **Доставка.** Самовывоз (адрес склада из настроек) / Транспортная компания (город + выбор ТК: СДЭК, ПЭК, Деловые Линии, КИТ, другое) / Курьер по городу (адрес).
3. **Оплата.** В MVP: «Счёт на оплату (для юрлиц)» и «Наличными при получении». Радиокнопка `online` существует в коде и БД, но скрыта флагом настройки `payments.online_enabled = false`.

Справа — липкая сводка заказа. Кнопка «Отправить заявку». Чекбокс согласия на обработку персональных данных со ссылкой на политику — обязателен, без него кнопка неактивна.

### 10.3 После отправки

1. Транзакция: создать `order` + `order_items` (снимок sku, name, price — чтобы переименование товара не искажало историю), очистить корзину.
2. Номер: `HR-{ymd}-{счётчик за день, 4 знака}`.
3. Уведомления (в очереди, не блокируют ответ):
   - Telegram менеджеру: номер, тип (розница/опт), клиент, телефон, сумма, список позиций (до 10 + «и ещё N»), ссылка на заказ в админке;
   - e-mail менеджеру (полная таблица);
   - e-mail клиенту (подтверждение приёма заявки, номер, что дальше).
4. Страница «Спасибо»: номер, что произойдёт дальше и когда («перезвоним в рабочее время в течение 30 минут»), контакты менеджера, кнопка «Продолжить покупки».
5. Событие `OrderCreated` — точка расширения для аналитики и будущей оплаты.

### 10.4 Антиспам

Honeypot-поле + временная метка формы (отправка быстрее 3 с — отклоняем) + rate limit 5 заявок в час на IP. reCAPTCHA не ставим (зависимость от Google), при необходимости — собственная арифметическая капча.

### 10.5 Задел под онлайн-оплату (не реализуем, но не мешаем)

Интерфейс `PaymentGatewayInterface { createPayment(Order): string; handleWebhook(Request): void; }`, поля `payment_id`/`paid_at`/статус `paid` уже в схеме, статус-машина заказа уже содержит переход `invoiced → paid`. Подключение ЮKassa в будущем = один класс + один роут вебхука + включение флага.

---

## 11. Личный кабинет и B2B

**Регистрация оптовика** (`/wholesale`): лендинг с выгодами (оптовые цены, отсрочка, персональный менеджер, работа по безналу) и форма: ИНН, юр. название, сегмент (ресторан/кафе/бар/отель/столовая/кондитерская/производство/сеть), контактное лицо, телефон, e-mail, город, комментарий. Создаётся `user(role=wholesale)` + `company(status=pending)`. Пароль задаёт сам пользователь, вход работает сразу, но цены — розничные.

**Модерация:** менеджер в Filament видит очередь `pending`, назначает `price_tier`, ставит `approved` → письмо клиенту «Оптовые цены открыты» + Telegram-уведомление админу о новой заявке.

**Разделы ЛК:**
- Сводка: статус аккаунта, ценовая группа, последние 5 заявок, менеджер с телефоном.
- Заявки: список со статусами, детальная страница с позициями и историей статусов, кнопка «Повторить заказ», скачивание счёта (PDF, когда менеджер прикрепит).
- Компания: редактирование реквизитов (после изменения ИНН/названия — повторная модерация).
- Быстрый заказ: textarea формата `артикул;количество` (или `артикул<tab>количество`), либо загрузка XLSX; превью распознанного с ошибками построчно («SKU не найден», «нет в наличии, добавим под заказ»); «Добавить всё в корзину».
- Прайс: скачивание XLSX со своими ценами.
- Избранное.

**Middleware `EnsureWholesaleApproved`** защищает разделы «Прайс» и «Быстрый заказ».

---

## 12. Админка (Filament 3)

Путь `/manage` (не `/admin`), отдельный гард, обязательный HTTPS, ограничение по ролям `manager`/`admin`, 2FA для `admin` (Filament + `laravel/fortify` TOTP).

**Дашборд:** виджеты — заявки за сегодня/неделю (график), новые заявки без обработки (счётчик крупно), товары с нулевым остатком, последний импорт (статус, время, цифры), новые B2B-заявки на модерацию.

**Ресурсы и особенности:**

- **Товары:** таблица с колонками фото/артикул/название/категория/закупка/розница/остаток/статус; фильтры по категории, бренду, наличию, поставщику, «есть фото», «без категории»; массовые действия: включить/выключить, сменить категорию, назначить наценку, экспорт в XLSX. Форма — вкладки: Основное · Цены (в т.ч. персональные цены по группам) · Характеристики (repeater) · Фото · SEO. При ручном изменении поля оно попадает в `locked_fields`.
- **Категории:** дерево с drag&drop (`filament-nestedset` или собственная сортировка), массовое включение, SEO-поля.
- **Заказы:** канбан по статусам не нужен — обычная таблица с быстрым фильтром по статусу, цветные бейджи, действие «Сменить статус» с обязательным комментарием при `canceled`, «Прикрепить счёт» (PDF), «Позвонили» — быстрая отметка. Просмотр — читаемый бланк заявки, кнопка «Открыть телефон» (`tel:`), копирование состава в буфер.
- **Клиенты и компании:** модерация, назначение ценовой группы, история заказов внутри карточки (RelationManager).
- **Поставщики / профили импорта / прогоны импорта:** см. раздел 6. На странице прогона — таблица ошибок с номерами строк и скачивание полного лога.
- **Ценовые группы, бренды, характеристики, страницы, лиды, настройки.**

Все таблицы — с поиском, сортировкой, пагинацией 25/50/100 и экспортом.

---

## 13. Уведомления

`TelegramNotifier` — один сервис, `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID` из `.env`, отправка через очередь с 3 ретраями и логированием ошибок (падение Telegram не должно ронять заказ).

События → уведомления:

| Событие | Telegram | E-mail менеджеру | E-mail клиенту |
|---|---|---|---|
| Новая заявка | да | да | да |
| Быстрый заказ в 1 клик | да | да | нет |
| Заявка на опт | да | да | да (подтверждение) |
| Опт одобрен | нет | нет | да |
| Смена статуса заказа | нет | нет | да (кроме внутренних статусов) |
| Импорт завершён | да | нет | нет |
| Импорт упал | да (с текстом ошибки) | да | нет |

Письма — Blade-шаблоны с логотипом и палитрой сайта, отправка через SMTP домена (HestiaCP), `MAIL_FROM_ADDRESS` на своём домене, SPF/DKIM настроены (раздел 17.6).

---

## 14. SEO, производительность, аналитика

**SEO:**
- ЧПУ везде, без `id` в URL.
- `MetaBuilder`: если поля `meta_*` пустые — генерация по шаблону из настроек («{Название} — купить в Симферополе, цена {цена} ₽ | {Сайт}»).
- Микроразметка: `Organization`, `BreadcrumbList`, `Product`+`Offer`, `FAQPage` на страницах доставки/оплаты.
- `sitemap.xml` генерируется командой по расписанию (категории, товары `is_active`, страницы, бренды), `robots.txt` закрывает `/manage`, `/account`, `/cart`, `/checkout`, `?sort=`, `?page=` оставляет открытым.
- Канонические URL, `hreflang` не нужен. Фильтры — `noindex, follow` при более чем одном активном фильтре.
- 404 — своя страница с поиском и категориями; редиректы 301 из таблицы `redirects` (на будущий перенос со старого сайта).

**Производительность (цели):**
- LCP ≤ 2.0 с на 4G, CLS ≤ 0.05, TTFB ≤ 300 мс.
- Кэш: дерево категорий, фильтры категории, главная — `Cache::tags('catalog')` на 1 час, сброс после импорта.
- Изображения только WebP + `srcset` 300/600/1200, `loading="lazy"` кроме первого экрана.
- `composer install --no-dev -o`, `config:cache`, `route:cache`, `view:cache`, `event:cache` на проде. OPcache включён.
- Запрет N+1: во всех листингах `with(['brand','category','images'])`; в тестах — проверка количества запросов на ключевых страницах (не больше 20).

**Аналитика:** Яндекс.Метрика с вебвизором и целями (`add_to_cart`, `checkout_start`, `order_created`, `quick_order`, `callback`), счётчик подключается из настроек, электронная коммерция через `dataLayer`.

---

## 15. Безопасность

Учитываем ошибки, найденные в аудите `ritualb2b`:

1. **Никакого SQLite в проде.** MySQL 8, отдельный пользователь БД с правами только на свою базу.
2. Пароли — `bcrypt` (дефолт Laravel), минимум 8 символов, проверка по `Password::defaults()->uncompromised()`.
3. Rate limiting: `login` 5/мин на IP+email, `checkout` 5/час, `search` 60/мин, API импорта — только внутренние.
4. Все формы — через `FormRequest` с явными правилами; массовое присвоение закрыто (`$fillable` явный, `$guarded = []` запрещён).
5. Policies на `Order`, `Company`, `Cart` — пользователь видит только своё (проверяется тестами на 403).
6. `/manage` за HTTPS + 2FA для админа; сессии `secure`, `httponly`, `same_site=lax`.
7. Загрузка файлов: белый список MIME (`xlsx`, `xls`, `csv`, `pdf`, `jpg`, `png`, `webp`), проверка реального MIME, хранение вне `public` с отдачей через контроллер для приватных (счета).
8. `config`, `.env`, `storage` недоступны из веба (document root = `public/`).
9. Логирование: `daily`, 14 дней; ошибки прода — в `storage/logs` + Telegram для уровня `critical`.
10. Персональные данные: политика конфиденциальности, чекбокс согласия, срок хранения заявок, возможность удаления аккаунта по запросу.
11. Заголовки: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP в режиме отчёта на старте.

---

## 16. Тестирование

Pest. Минимум, без которого спринт не принимается:

**Unit:** `PriceResolver` (розница, опт, персональная цена, округление), `RowNormalizer` (цены с запятой, «в наличии», напряжение), `Money`, генератор номера заказа, генератор slug при коллизии.

**Feature:**
- главная, категория, карточка, поиск отдаются 200 и содержат ожидаемое;
- фильтр по цене/бренду/наличию отдаёт корректную выборку;
- добавление в корзину гостем, слияние корзины при логине;
- оформление заявки создаёт заказ, позиции, отправляет уведомления (`Notification::fake`, `Queue::fake`);
- валидация checkout (телефон, согласие) возвращает ошибки;
- оптовик видит свою цену, гость — розничную, `pending`-оптовик — розничную;
- `EnsureWholesaleApproved` блокирует прайс для неодобренных (403);
- чужой заказ в ЛК — 403;
- импорт из фикстурного XLSX (`tests/Fixtures/supplier_sample.xlsx`, 50 строк, включая «грязные»): создаёт товары, обновляет цены, помечает отсутствующие, не перезаписывает `locked_fields`, повторный прогон даёт `unchanged`;
- rate limit на checkout срабатывает.

**Фабрики** для всех моделей + сидер `DemoSeeder`: 8 категорий, 4 бренда, 120 товаров, 3 ценовые группы, 2 пользователя (`admin@local`, `opt@local`), 5 заказов — чтобы верстать и демонстрировать без реального импорта.

---

## 17. Развёртывание на VPS (HestiaCP)

### 17.1 Требования к серверу
Ubuntu 22.04/24.04, HestiaCP, PHP 8.3-FPM (`bcmath`, `intl`, `gd`, `zip`, `mbstring`, `xml`, `curl`, `mysqli`, `pdo_mysql`, `opcache`, `redis`), MySQL 8 / MariaDB 10.11, Redis, Node 20 (для сборки), Composer 2, Git, Supervisor.

### 17.2 Домен
Создать web-домен в HestiaCP. Document root должен указывать на `public/`. В HestiaCP это делается симлинком:
```
cd /home/<user>/web/<domain>
rm -rf public_html
ln -s /home/<user>/web/<domain>/app/public public_html
```
(либо через кастомный шаблон nginx с `root .../app/public`). Код проекта — в `/home/<user>/web/<domain>/app`.

### 17.3 Первичная установка
```
git clone <repo> app && cd app
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# заполнить .env: APP_URL, DB_*, REDIS_*, MAIL_*, TELEGRAM_*
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder   # ценовые группы, страницы, настройки
php artisan storage:link
npm ci && npm run build
php artisan config:cache route:cache view:cache
chown -R <user>:<user> storage bootstrap/cache
```

### 17.4 Очереди
```
/etc/supervisor/conf.d/horeca-worker.conf
[program:horeca-worker]
command=php /home/<user>/web/<domain>/app/artisan queue:work redis --queue=imports,default --sleep=3 --tries=3 --max-time=3600
user=<user>
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/home/<user>/web/<domain>/app/storage/logs/worker.log
```
Если Redis недоступен — `QUEUE_CONNECTION=database` и таблица `jobs`; конфиг обязан работать в обоих режимах.

### 17.5 Cron (в HestiaCP → Cron)
```
* * * * * cd /home/<user>/web/<domain>/app && php artisan schedule:run >> /dev/null 2>&1
```
Расписание в `routes/console.php`: импорт по профилям, `sitemap:generate` ежедневно в 04:00, `queue:prune-batches`, `cache:prune-stale-tags`, бэкап БД ежедневно в 03:30 (`mysqldump` + gzip, хранить 14 копий).

### 17.6 Почта
Почтовый домен в HestiaCP, ящик `shop@<domain>`, SPF/DKIM из панели, `MAIL_MAILER=smtp`, `MAIL_HOST=localhost`, порт 587, TLS. Проверка: `php artisan mail:test` (собственная команда) отправляет тестовое письмо.

### 17.7 Деплой обновлений
Скрипт `deploy.sh` в корне: `git pull` → `composer install --no-dev -o` → `php artisan migrate --force` → `npm ci && npm run build` → `php artisan optimize` → `php artisan queue:restart` → `php artisan up`. Перед миграциями — `php artisan down --render=errors::503`.

---

## 18. План спринтов

**Спринт 1 — Скелет (день 1).**
Установка Laravel 11, MySQL, Redis, Tailwind с токенами из раздела 9, базовый layout (шапка, футер, контейнер), Filament с гардом `/manage`, роли через spatie, миграции всех таблиц раздела 5, модели с отношениями, фабрики, `DemoSeeder`, Pint, Pest, `.env.example`, README со стартом.
*DoD:* `php artisan test` зелёный, главная с демо-данными открывается, `/manage` пускает админа.

**Спринт 2 — Импорт (дни 2–3).**
`SupplierFeedInterface`, `XmlUrlSource` (cp1251 + кириллические теги + namespace-agnostic чтение через `XMLReader`), `XlsxUrlSource`, `XlsxFileSource`, `RowMapper`, `RowNormalizer`, `ProductUpserter`, `ImportRunner`, склейка `Catalog.xml` + `Ostatki.xml` по GUID, склады и `product_stocks`, очередь, команда `supplier:import`, Filament-ресурсы поставщика/профилей/прогонов, мастер маппинга с разведкой тегов, `ExtractAttributesFromName`, Telegram-сводка, тесты на урезанных фикстурах обоих XML (включая карточку с пустым артикулом, цену с неразрывным пробелом, текстовый остаток «Много» и дублирующееся имя категории).
*DoD:* оба реальных файла Росхолода заливаются и дают корректный каталог с деревом категорий и статусами наличия; повторный прогон даёт `unchanged`; ручные правки не затираются; прогон остатков не трогает тексты и категории.

**Спринт 3 — Витрина каталога (дни 3–5).**
Главная, дерево категорий, листинг с Livewire-фильтрами и сортировкой, карточка товара, галерея, характеристики, поиск (шапка + страница), хлебные крошки, микроразметка, мобильная адаптация, скелетоны загрузки, 404.
*DoD:* Lighthouse mobile ≥ 90 performance / ≥ 95 accessibility на категории и карточке.

**Спринт 4 — Корзина и заявка (день 6).**
Серверная корзина, слияние гостевой, страница корзины, checkout, валидации, антиспам, создание заказа, страница «Спасибо», Telegram + письма, «Купить в 1 клик», Filament-ресурс заказов со сменой статусов и логом.
*DoD:* E2E-сценарий №1 из раздела 2.2 проходит тестом и руками.

**Спринт 5 — B2B (дни 7–8).**
Регистрация оптовика, компании, модерация, ценовые группы, `PriceResolver` с персональными ценами, отображение двух цен, ЛК (сводка, заказы, повтор, компания, избранное), быстрый заказ списком, экспорт прайса XLSX, middleware.
*DoD:* сценарии №3–5 проходят тестами; неодобренный оптовик не видит опт.

**Спринт 6 — Контент и SEO (день 9).**
Страницы CMS, настройки сайта, SEO-генерация мет, sitemap, robots, редиректы, Метрика с целями, тексты категорий, сопутствующие товары, формы «перезвоните» и «не нашли товар».
*DoD:* все обязательные страницы существуют и наполнены, sitemap валиден, цели в Метрике срабатывают.

**Спринт 7 — Личный кабинет поставщика (когда появятся доступы).**
`SupplierApiSource` по документации ЛК, авторизация/токен, инкрементальное обновление остатков (частое, лёгкое) отдельно от полного обновления каталога (редкое), расписание, мониторинг расхождений «файл vs API».
*DoD:* остатки обновляются по расписанию без ручных действий, старый файловый профиль продолжает работать как резерв.

**Спринт 8 — Прод (день 10).**
Деплой по разделу 17, HTTPS, бэкапы, supervisor, cron, почта, мониторинг (ошибки в Telegram), нагрузочная проверка каталога, финальный прогон чек-листа приёмки.

---

## 19. Чек-лист приёмки

**Каталог**
- [ ] Импорт реального файла поставщика создаёт полный каталог без дублей
- [ ] Повторный импорт не создаёт дублей и не затирает ручные правки
- [ ] Товар без остатка остаётся доступен по URL со статусом «Под заказ»
- [ ] Изображения сконвертированы в WebP в трёх размерах
- [ ] Категории-новички создаются выключенными и не попадают на витрину

**Витрина**
- [ ] Фильтры меняют выдачу без перезагрузки и отражаются в URL (ссылка с фильтром открывается корректно)
- [ ] Поиск по артикулу находит товар с первого символа артикула
- [ ] Карточка содержит цену, наличие, характеристики, галерею, микроразметку
- [ ] Мобильная версия проходит проверку на 360 px без горизонтального скролла
- [ ] Клавиатурная навигация работает, фокус виден

**Продажи**
- [ ] Заявка создаётся, приходит в Telegram и на почту в течение 30 с
- [ ] Номер заказа уникален, состав заказа не меняется при изменении товара
- [ ] Корзина переживает закрытие браузера и объединяется при входе
- [ ] Повторная отправка формы не создаёт дубль заказа

**B2B**
- [ ] Оптовик видит свою цену, гость — розничную, `pending` — розничную
- [ ] Быстрый заказ распознаёт список артикулов и сообщает об ошибочных строках
- [ ] Прайс выгружается с ценами конкретной группы

**Эксплуатация**
- [ ] Админка закрыта 2FA, `/manage` не индексируется
- [ ] Бэкап БД создаётся ежедневно и восстанавливается проверенной командой
- [ ] Очередь переживает перезапуск сервера (supervisor autostart)
- [ ] Падение импорта приходит в Telegram с текстом ошибки
- [ ] `php artisan test` зелёный на проде-конфигурации

---

## 20. Что нужно от заказчика (блокеры)

1. ~~Ссылка на файлы поставщика~~ — получена, структура разобрана (см. 6.1). **Осталось подтвердить главное: цена в выгрузках — дилерская или розничная?** Ответ виден в ЛК Росхолода: сравнить цену любой позиции в кабинете с ценой того же GUID в `Catalog.xml`. От этого зависит, ставим наценку 35% или 0.
2. **Доступы в ЛК Росхолода** (`rosholod.org/profile`) — кабинет уже существует, нужен ответ поставщика: есть ли API или выгрузка персональных дилерских цен, отличается ли каталог в кабинете от публичного. Плюс вопрос, отдают ли они фотографии — их нет ни в одном публичном файле, а это единственное, чего каталогу реально не хватает для продаж.
3. **Домен** и решение: новый или поддомен существующего.
4. Реквизиты продавца, адрес склада/самовывоза, график работы, телефоны, e-mail — для настроек и страниц.
5. Логотип в SVG (или задача на отрисовку), при отсутствии — временный текстовый логотип на Golos Text.
6. Telegram: создать бота через @BotFather, получить `chat_id` рабочей группы менеджеров.
7. Список ценовых групп и размеры скидок (стартовые: Розница 0%, Опт-1 10%, Опт-2 15%, Сеть 20% — уточнить).
8. Наценка на закупку по умолчанию (стартовая: 35%, округление вверх до 10 ₽ — уточнить).

---

## 21. Вне объёма MVP (зафиксировано, чтобы не расползалось)

Онлайн-оплата, интеграция с 1С:УТ, личные скидки на клиента (сверх группы), отзывы и рейтинги, сравнение товаров, блог, мультиязычность, подбор по помещению (калькулятор кухни), Avito/Яндекс.Маркет-фиды, мобильное приложение, склад и резервирование, документооборот (УПД/ЭДО).
Всё перечисленное проектно совместимо с текущей схемой БД и добавляется без её перелопачивания.
