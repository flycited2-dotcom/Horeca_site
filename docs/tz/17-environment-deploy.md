> Раздел ТЗ horeca-shop, версия 1.2. Оглавление, история изменений и карта «спринт → разделы» — [TZ-horeca-shop.md](../../TZ-horeca-shop.md).

# 17. Окружение и развёртывание

## 17.0 Локальная разработка

**Выбрано заказчиком 16.09.2026: Windows, без установки программ в систему.** Код пишется и проверяется на компьютере разработки, на сервер выкладывается готовым.

**Зачем запускать локально.**
- Laravel-проект собирается Composer: он скачивает библиотеки в `vendor/`.
- Витрину и админку Filament нужно открыть в браузере.
- Правила проекта требуют зелёных тестов перед каждым коммитом, а для тестов нужны PHP и база данных.

Вариант «тесты только на сервере по SSH» отклонён: каждая проверка требует выкладки, а ошибка может задеть рабочий сервер.

**Инструменты** — портативные сборки из официальных источников в одной папке `%USERPROFILE%\devtools\`. Службы Windows и права администратора не нужны, всё удаляется вместе с папкой.

| Что | Версия | Источник |
|---|---|---|
| PHP, NTS x64 | 8.4.x | windows.php.net |
| Composer | 2.x, файл `composer.phar` | getcomposer.org |
| MariaDB, ZIP-архив | 11.8.x LTS | mariadb.org |

- **Старый PHP.** Уже лежащий на компьютере `C:\Users\TLT-1\php-portable` (PHP 8.3) не используется и не меняется: Pest 5 и `spatie/laravel-sitemap` 8 требуют PHP 8.4, а модули `intl`, `pdo_mysql` и `gd` в нём не включены.
- **Модули PHP 8.4:** `intl`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `curl`, `exif`.
- **MariaDB** запускается обычным процессом на `127.0.0.1:3306` только на время работы. Базы: `horeca` для разработки и `horeca_test` для тестов.
- **Redis** локально не нужен: `CACHE_STORE`, `SESSION_DRIVER` и `QUEUE_CONNECTION` = `database`.
- **Node 24 и Git** уже установлены.
- **Отличия Windows от Linux** (переводы строк, регистр в именах файлов) закрываются `.gitattributes` и проверкой на стейджинге сервера.
- Точные команды запуска — в README, раздел «Локальная разработка» (спринт 0).

## 17.1 Требования к серверу

- **ОС и панель:** Ubuntu 22.04/24.04, HestiaCP 1.10+.
- **PHP 8.4-FPM** с расширениями: `bcmath`, `intl`, `gd`, `zip`, `mbstring`, `xml`, `xmlreader`, `curl`, `pdo_mysql`, `opcache`, `redis`, `exif`, `fileinfo`.
- **Сервисы и инструменты:** MariaDB 11.8 (ставится HestiaCP по умолчанию) или MySQL 8.4, Redis 7, Node 24 (сборка), Composer 2, Git, Supervisor.
- **Расположение:** сервер в РФ (§15.10).
- **Память:** не меньше 2 ГБ. Если меньше, ассеты собираются локально и загружаются в `public/build`.

## 17.2 Домен

Web-домен создаётся в HestiaCP. Document root указывает на `public/` симлинком:

```
cd /home/<user>/web/<domain>
rm -rf public_html
ln -s /home/<user>/web/<domain>/app/public public_html
```

Другой вариант — собственный шаблон nginx с `root …/app/public`. Код проекта — в `/home/<user>/web/<domain>/app`.

## 17.3 Первичная установка

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

## 17.4 Очереди

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

## 17.5 Cron и расписание

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

## 17.6 Почта

- В HestiaCP: почтовый домен, ящик `shop@<domain>`, SPF и DKIM.
- В `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=localhost`, порт 587, TLS.
- Проверка: `php artisan mail:test {email}` отправляет тестовое письмо.

## 17.7 Деплой обновлений

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

## 17.8 Проверка восстановления

Раз в месяц последний бэкап разворачивается в отдельную базу `horeca_restore_check` по инструкции из README, результат фиксируется. До запуска проверка выполняется один раз обязательно.
