> Раздел ТЗ horeca-shop. Версия, оглавление, история изменений и карта «спринт → разделы» — [TZ-horeca-shop.md](../../TZ-horeca-shop.md).

# 3. Стек и обоснование

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
filament/spatie-laravel-media-library-plugin ^5.8   загрузка и порядок фото товара в админке
spatie/laravel-sitemap ^8.2
laravel/pint ^1.32                (dev)
pestphp/pest ^5.2                 (dev)
pestphp/pest-plugin-laravel ^5.0  (dev)
barryvdh/laravel-debugbar ^4.4    (dev)

MariaDB 11.8 LTS (до 04.06.2028) или MySQL 8.4 LTS
Redis 7+
Node 24 LTS (только сборка), vite ^8.3, laravel-vite-plugin ^3.2
tailwindcss ^4.3, @tailwindcss/vite ^4.3
@fontsource-variable/manrope ^5.3, @fontsource-variable/jetbrains-mono ^5.3
```

**Не используем** (были в v1.0):
- `phpoffice/phpspreadsheet`, `maatwebsite/excel` — заменены OpenSpout;
- `spatie/laravel-permission` — четыре фиксированные роли закрываются enum и Policies;
- `intervention/image` — medialibrary работает через `spatie/image`;
- `laravel/fortify` — 2FA встроена в Filament;
- `filament-nestedset` — для двухуровневого дерева не нужен.

Любой пакет вне этого списка — только после согласования (`CLAUDE.md`).
