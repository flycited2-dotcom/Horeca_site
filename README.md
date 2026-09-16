# horeca-shop

Интернет-магазин оборудования и расходников для HoReCa — рестораны, кафе, бары, отели, столовые — в Крыму и по РФ.

**Стек:** Laravel 13 · Livewire 4 · Filament 5 · Tailwind 4 · MariaDB 11.8 · PHP 8.4.

**Статус:** спринт 0 — документация и локальная среда готовы. Кода приложения пока нет, скелет Laravel появится в спринте 1.

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
- Скрипт печатает версии PHP, Composer и MariaDB.

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
- **Консоль:** `mariadb --host=127.0.0.1 --user=horeca --password=horeca_local horeca`.

### Настройки PHP

Файл `devtools\php-8.4\php.ini` сделан из `php.ini-development`:
- включены модули `curl`, `exif`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`;
- `date.timezone = Europe/Moscow`;
- `memory_limit = 512M`.

Папку `C:\Users\TLT-1\php-portable` (PHP 8.3) для этого проекта не использовать: версия и модули не подходят.

### Проверки перед коммитом

Заработают вместе со скелетом Laravel в спринте 1:

```powershell
./vendor/bin/pint
php artisan test
npm run build
```
