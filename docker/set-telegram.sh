#!/usr/bin/env bash
# Токен Telegram-бота записывается в .env сервера без чата и без git: скрипт спрашивает его
# сам, на экране он не виден (ТЗ §13, §15.9). Идентификатор группы уже в .env. Запуск на
# сервере под root:
#
#   /opt/gastrosnab/src/docker/set-telegram.sh
#
# В конце скрипт отправляет в группу проверочное сообщение.
set -euo pipefail

base=/opt/gastrosnab
env_file="$base/.env"

read -rsp "Токен бота (при вводе не отображается): " token
echo

if [ -z "$token" ]; then
    echo "Нужен токен." >&2
    exit 1
fi

if ! grep -Eq '^TELEGRAM_CHAT_ID=.+' "$env_file"; then
    read -rp "Идентификатор группы (например -1001234567890): " chat
    [ -n "$chat" ] || { echo "Нужен идентификатор группы." >&2; exit 1; }
    printf 'TELEGRAM_CHAT_ID=%s\n' "$chat" >> "$env_file"
fi

if grep -q '^TELEGRAM_BOT_TOKEN=' "$env_file"; then
    TOKEN="$token" awk -F= '$1 == "TELEGRAM_BOT_TOKEN" { print "TELEGRAM_BOT_TOKEN=" ENVIRON["TOKEN"]; next } { print }' "$env_file" > "$env_file.tmp"
    cat "$env_file.tmp" > "$env_file"
    rm "$env_file.tmp"
else
    printf 'TELEGRAM_BOT_TOKEN=%s\n' "$token" >> "$env_file"
fi

echo "Токен записан. Перезапускаю магазин, чтобы он его увидел."
docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml" up -d --wait

echo "Отправляю проверочное сообщение в группу..."
docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml" exec -T app \
    php artisan tinker --execute='app(App\Services\Notifications\TelegramNotifier::class)->deliver("Проверка связи: бот магазина «Гастроснаб» подключён, о новых заявках сообщу сюда."); echo "Сообщение отправлено — проверьте группу.".PHP_EOL;'
