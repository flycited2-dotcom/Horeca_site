#!/usr/bin/env bash
# Токен Dealer API Росхолода записывается в .env сервера без чата и без git: скрипт спрашивает
# его сам, на экране он не виден (ТЗ §6). Запуск на сервере под root:
#
#   /opt/gastrosnab/src/docker/set-rosholod.sh
#
# После записи магазин перезапускается и сам проверяет API (только чтение): токен, права, связь
# товаров с нашими. Источник содержимого этот скрипт не переключает — это отдельное решение
# после проверки (SUPPLIER_CONTENT_SOURCE=api).
set -euo pipefail

base=/opt/gastrosnab
env_file="$base/.env"
compose=(docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml")

read -rsp "Токен API Росхолода (при вводе не отображается): " token
echo

if [ -z "$token" ]; then
    echo "Нужен токен." >&2
    exit 1
fi

if grep -q '^ROSHOLOD_API_TOKEN=' "$env_file"; then
    TOKEN="$token" awk -F= '$1 == "ROSHOLOD_API_TOKEN" { print "ROSHOLOD_API_TOKEN=" ENVIRON["TOKEN"]; next } { print }' "$env_file" > "$env_file.tmp"
    cat "$env_file.tmp" > "$env_file"
    rm "$env_file.tmp"
else
    printf 'ROSHOLOD_API_TOKEN=%s\n' "$token" >> "$env_file"
fi

echo "Токен записан. Перезапускаю магазин, чтобы он его увидел."
"${compose[@]}" up -d --wait

echo "Проверяю API Росхолода (только чтение, около минуты)..."
"${compose[@]}" exec -T app php artisan supplier:api-check
