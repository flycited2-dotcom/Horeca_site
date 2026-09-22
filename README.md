# horeca-shop

Интернет-магазин оборудования и расходников для HoReCa — рестораны, кафе, бары, отели, столовые — в Крыму и по РФ.

**Стек:** Laravel 13 · Livewire 4 · Filament 5 · Tailwind 4 · MariaDB 11.8 · PHP 8.4.

**Статус:** спринты 0–4 выполнены, идёт спринт 5 — оптовики и личный кабинет. Уже есть:
- импорт каталога и остатков Росхолода;
- админка с двухфакторной аутентификацией, каталогом, заявками и лидами;
- витрина по утверждённым макетам: каталог, поиск, карточка товара, бренды, сравнение, корзина, оформление заявки и короткие заявки.

Тестовый сайт — https://test.gastrosnab.ru.

## Документы

- [TZ-horeca-shop.md](TZ-horeca-shop.md) — оглавление ТЗ, история изменений и карта «спринт → разделы». Сами разделы — в `docs/tz/`.
- [CLAUDE.md](CLAUDE.md) — правила разработки.
- [DESIGN-BRIEF-horeca-shop.md](DESIGN-BRIEF-horeca-shop.md) — бриф для Claude Design.
- `docs/design/` — утверждённые макеты (13 экранов) и спецификация для фронта. `Карточка товара.dc.html` открывается в браузере рядом с `support.js`.
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

### Посмотреть витрину

```powershell
php artisan serve
```

- Сайт — http://127.0.0.1:8000, админка — http://127.0.0.1:8000/manage.
- После импорта все категории выключены, поэтому витрина пуста: включите нужные в админке, раздел «Каталог» → «Категории» → массовое действие «Включить», и отметьте корневые как «На главной».
- Демо-каталог (`migrate:fresh --seed`) уже приходит с включёнными категориями.
- Стайлгайд — http://127.0.0.1:8000/styleguide: все компоненты витрины и их состояния для сверки с экраном 9 макета (`docs/design`). Открывается только при `APP_ENV=local`, в остальных окружениях — 404. Новый компонент добавляется и сюда.

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

## Сервер

Магазин «Гастроснаб» работает на VPS Спринтбокса `212.116.115.150` в своих контейнерах Docker (ТЗ §17). Сервер общий с другими сайтами, их программы магазин не затрагивает.

| Что | Где |
|---|---|
| Тестовый сайт | https://test.gastrosnab.ru — закрыт от поисковиков, письма пишутся в журнал |
| Код | `/opt/gastrosnab/src`, прежняя версия — `/opt/gastrosnab/src.old` |
| Настройки | `/opt/gastrosnab/.env`, права 600, в git не попадает |
| Контейнеры | `app`, `web`, `queue-default`, `queue-imports`, `scheduler`, `mariadb`, `redis` |
| Данные | тома Docker `gastrosnab_db`, `gastrosnab_redis`, `gastrosnab_storage` |
| Фото и счета | S3 Спринтхоста: бакет `s3-968732`, папка `gastrosnab/` (ТЗ §17.9) |
| nginx сервера | `/etc/nginx/sites-available/test.gastrosnab.ru` из `docker/host-nginx/`, HTTPS — certbot |

### Доступ

В `~/.ssh/config` компьютера разработки — хост `gastrosnab`: пользователь root, порт 2222, ключ `~/.ssh/gastrosnab_deploy`. Пароли не используются.

### Выложить новую версию

В Git Bash на компьютере разработки:

```bash
git archive --format=tar.gz -o /tmp/gastrosnab.tar.gz HEAD && scp /tmp/gastrosnab.tar.gz gastrosnab:/tmp/ && ssh gastrosnab 'tar -xzOf /tmp/gastrosnab.tar.gz docker/deploy.sh | bash -s /tmp/gastrosnab.tar.gz'
```

- **Что уходит на сервер.** В архив попадает только закоммиченный код.
- **Сколько ждать.** Образ собирается несколько минут, сайт в это время работает. Закрыт он только на время миграций.
- **Первая выкладка.** Команда та же: скрипт выкладки берётся из архива.

### Служебные команды на сервере

`docker/dc` — это `docker compose` с настройками магазина:

```bash
/opt/gastrosnab/src/docker/dc ps
/opt/gastrosnab/src/docker/dc logs --tail 100 app
/opt/gastrosnab/src/docker/dc exec app php artisan about
/opt/gastrosnab/src/docker/dc exec app php artisan supplier:import rosholod.catalog_xml
```

### Первичная установка

1. **`/opt/gastrosnab/.env`** по образцу `.env.example`:
   - `APP_KEY=base64:` и `DB_PASSWORD` генерируются на сервере через `openssl rand`;
   - `DB_HOST=mariadb`, `REDIS_HOST=redis`;
   - `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `redis`;
   - `MEDIA_DISK=s3`, `INVOICES_DISK=s3-private`;
   - права — `chmod 600`.
2. **Выкладка** — командой выше.
3. **Справочники:** `/opt/gastrosnab/src/docker/dc exec app php artisan db:seed --class=ProductionSeeder --force`.
4. **Ключи S3.** Заказчик сам запускает `/opt/gastrosnab/src/docker/set-s3-keys.sh` в консоли сервера. Скрипт спрашивает ключи и перезапускает контейнеры.
5. **nginx сервера и HTTPS:**
   - скопировать `docker/host-nginx/test.gastrosnab.ru.conf` в `/etc/nginx/sites-available/test.gastrosnab.ru`;
   - включить ссылкой в `sites-enabled`;
   - `nginx -t && systemctl reload nginx`;
   - `certbot --nginx -d test.gastrosnab.ru`.
6. **Администратор.** Заказчик запускает `/opt/gastrosnab/src/docker/dc exec app php artisan make:filament-user` и вводит имя, почту и пароль. Затем роль повышается: `dc exec app php artisan tinker --execute="App\Models\User::where('email', '<почта>')->update(['role' => 'admin'])"`. При первом входе в `/manage` админка попросит настроить двухфакторную аутентификацию.
