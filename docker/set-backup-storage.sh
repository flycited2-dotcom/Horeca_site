#!/usr/bin/env bash
# Место для копии базы вне сервера (ТЗ §17.9, §19): S3-совместимое хранилище с ЗАКРЫТЫМ
# бакетом — в дампе данные клиентов. Ключи записываются в .env сервера без чата и без git:
# скрипт спрашивает их сам, секретный ключ на экране не виден. Запуск на сервере под root:
#
#   /opt/gastrosnab/src/docker/set-backup-storage.sh
#
# В конце скрипт делает пробную копию и говорит, дошла ли она до хранилища.
set -euo pipefail

base=/opt/gastrosnab
env_file="$base/.env"

read -rp "Адрес хранилища (endpoint, например https://storage.yandexcloud.net): " endpoint
read -rp "Регион (например ru-central1): " region
read -rp "Имя закрытого бакета для копий: " bucket
read -rp "Access Key ID: " key
read -rsp "Secret Access Key (при вводе не отображается): " secret
echo

if [ -z "$endpoint" ] || [ -z "$region" ] || [ -z "$bucket" ] || [ -z "$key" ] || [ -z "$secret" ]; then
    echo "Нужны все пять значений." >&2
    exit 1
fi

# Заменяет строку NAME=... в .env или дописывает её, если строки нет. Значение идёт через
# окружение awk, поэтому кавычки и слэши в нём безопасны.
set_var() {
    local name="$1" value="$2"
    if grep -q "^${name}=" "$env_file"; then
        NAME="$name" VALUE="$value" awk -F= '$1 == ENVIRON["NAME"] { print ENVIRON["NAME"] "=" ENVIRON["VALUE"]; next } { print }' "$env_file" > "$env_file.tmp"
        cat "$env_file.tmp" > "$env_file"
        rm "$env_file.tmp"
    else
        printf '%s=%s\n' "$name" "$value" >> "$env_file"
    fi
}

set_var BACKUP_DISK backups
set_var BACKUP_S3_ENDPOINT "$endpoint"
set_var BACKUP_S3_REGION "$region"
set_var BACKUP_S3_BUCKET "$bucket"
set_var BACKUP_S3_KEY "$key"
set_var BACKUP_S3_SECRET "$secret"
set_var BACKUP_S3_PATH_STYLE true

echo "Настройки записаны. Перезапускаю магазин, чтобы он их увидел."
docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml" up -d --wait

echo "Делаю пробную копию базы и отправляю её в хранилище..."
docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml" exec -T app php artisan backup:database
