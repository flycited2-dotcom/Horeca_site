# horeca-shop

Интернет-магазин оборудования и расходников для HoReCa — рестораны, кафе, бары, отели, столовые — в Крыму и по РФ.

**Стек:** Laravel 13 · Livewire 4 · Filament 5 · Tailwind 4 · MariaDB 11.8 · PHP 8.4.

**Статус:** спринт 2 выполнен. Есть скелет приложения, схема базы данных по ТЗ, админка с двухфакторной аутентификацией, демо-каталог, главная страница со списком категорий и импорт каталога и остатков Росхолода из XML. Витрина в дизайне появится в спринте 3, после утверждения макетов.

## Документы

- [TZ-horeca-shop.md](TZ-horeca-shop.md) — оглавление ТЗ, история изменений и карта «спринт → разделы». Сами разделы — в `docs/tz/`.
- [CLAUDE.md](CLAUDE.md) — правила разработки.
- [DESIGN-BRIEF-horeca-shop.md](DESIGN-BRIEF-horeca-shop.md) — бриф для Claude Design.
- [docs/supplier-data-2026-09-16.md](docs/supplier-data-2026-09-16.md) — разбор выгрузок поставщика.
- `docs/archive/` — прошлые версии документов.

## Локальная разработка

Инструменты — портативные сборки в папке `C:\Users\TLT-1\devtools\`. В систему ничего не установлено: нет служб Windows, системный PATH не менялся. Чтобы удалить всё, достаточно стереть папку (ТЗ §17.0).

| Инструмент | Версия | Папка |
|---|---|---|
| PHP (NTS x64) | 8.4.25 | `devtools\php-8.4` |
| Composer | 2.10.3 | `devtools\composer` |
| MariaDB | 11.8.9 | `devtools\mariadb-11.8`, данные — `devtools\mariadb-data` |
| Node.js | 24 | установлен в системе |
| Git | 2.53 | установлен в системе |

### Подключить инструменты к окну PowerShell

```powershell
. C:\Users\TLT-1\devtools\env.ps1
```

- Точка и пробел в начале обязательны.
- Команды `php`, `composer` и `mariadb` становятся доступны в текущем окне, после закрытия окна всё возвращается как было.
- Composer вызывается напрямую через PHP: так символ `^` в версиях пакетов не теряется.

### База данных

```powershell
C:\Users\TLT-1\devtools\start-mariadb.ps1
C:\Users\TLT-1\devtools\stop-mariadb.ps1
```

- **Запуск и остановка.** Первый скрипт запускает базу в фоне, второй останавливает. Повторный вызов безопасен.
- **Доступ.** База слушает только `127.0.0.1:3306`, из сети недоступна. Кодировка — `utf8mb4_unicode_ci`.
- **Базы:** `horeca` — для разработки, `horeca_test` — для тестов.
- **Пользователь проекта:** `horeca`, пароль `horeca_local`. Это доступ только к локальной базе на этом компьютере — на сервере будут другие доступы.
- **`root`** — без пароля, вход только с этого компьютера.

### Первый запуск проекта

```powershell
. C:\Users\TLT-1\devtools\env.ps1
C:\Users\TLT-1\devtools\start-mariadb.ps1
composer install
npm install
Copy-Item .env.example .env
```

В `.env` указать `DB_PASSWORD=horeca_local`, затем:

```powershell
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve
```

- **Сайт:** http://127.0.0.1:8000
- **Админка:** http://127.0.0.1:8000/manage
- **`migrate:fresh --seed`** пересоздаёт базу `horeca`: справочники из `ProductionSeeder`, а в окружении `local` ещё и демо-каталог из `DemoSeeder`. Занимает около 20 секунд.

### Демо-аккаунты

Созданы `DemoSeeder` только для локальной разработки, пароль у всех — `password`.

| E-mail | Роль |
|---|---|
| `admin@horeca.test` | администратор |
| `manager@horeca.test` | менеджер |
| `opt@horeca.test` | клиент с одобренной оптовой компанией, группа «Опт-1» |

При первом входе в `/manage` админка попросит настроить двухфакторную аутентификацию:
1. Отсканировать QR-код приложением-аутентификатором, например Google Authenticator.
2. Ввести код из приложения.
3. Сохранить коды восстановления.

Без этого шага в админку не попасть.

### Импорт от поставщика

```powershell
php artisan supplier:import rosholod.catalog_xml
php artisan supplier:import rosholod.stock_xml
```

- **Порядок.** Сначала каталог, потом остатки: остатки пишутся только товарам, которые уже есть в каталоге.
- **Проверка без изменений:** `--dry-run` показывает, сколько товаров создастся, обновится и снимется, и ничего не меняет.
- **Повторно прочитать тот же файл:** `--force`. Без него неизменившаяся выгрузка пропускается.
- **Время.** Каталог — до полуминуты, остатки — несколько секунд.
- **Очередь.** Кнопки «Запустить» в админке ставят задачу в очередь `imports`. Локально её обслуживает `php artisan queue:work database-imports --queue=imports --timeout=3600`.
- **Расписание.** `php artisan schedule:work` раз в минуту проверяет, каким включённым профилям пора запускаться, и в 20:00 шлёт итоги дня. Профили после установки выключены: включает их администратор в разделе «Импорт».
- **Telegram.** Пока `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID` в `.env` пустые, сообщения о сбоях и итоги дня пишутся в журнал `storage/logs`.
- **Журналы прогонов:** `storage/logs/imports/{номер}.log`, скачанные файлы — `storage/app/private/imports`.

### Проверки перед коммитом

```powershell
php vendor/bin/pint
php artisan test
npm run build
```

- Тесты идут на MariaDB в базе `horeca_test`, поэтому база должна быть запущена.
- SQLite в проекте не используется.

### Что учесть

- **PHP 8.3.** Папку `C:\Users\TLT-1\php-portable` для этого проекта не использовать: версия и модули не подходят.
- **Модули PHP.** Файл `devtools\php-8.4\php.ini` сделан из `php.ini-development`: включены `curl`, `exif`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`; `date.timezone = Europe/Moscow`; `memory_limit = 512M`.
- **Debugbar.** Работает только при `APP_DEBUG=true`, то есть локально.
