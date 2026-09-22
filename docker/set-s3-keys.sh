#!/usr/bin/env bash
# Ключи хранилища S3 записываются в .env сервера без чата и без git: скрипт спрашивает их
# сам, секретный ключ на экране не виден. Запуск на сервере под root:
#
#   /opt/gastrosnab/src/docker/set-s3-keys.sh
set -euo pipefail

base=/opt/gastrosnab
env_file="$base/.env"

read -rp "Access Key ID: " key
read -rsp "Secret Access Key (при вводе не отображается): " secret
echo

if [ -z "$key" ] || [ -z "$secret" ]; then
    echo "Нужны оба ключа." >&2
    exit 1
fi

KEY="$key" SECRET="$secret" awk '
    /^AWS_ACCESS_KEY_ID=/ { print "AWS_ACCESS_KEY_ID=" ENVIRON["KEY"]; next }
    /^AWS_SECRET_ACCESS_KEY=/ { print "AWS_SECRET_ACCESS_KEY=" ENVIRON["SECRET"]; next }
    { print }
' "$env_file" > "$env_file.tmp"
cat "$env_file.tmp" > "$env_file"
rm "$env_file.tmp"

echo "Ключи записаны. Перезапускаю магазин, чтобы он их увидел."
docker compose --project-name gastrosnab --env-file "$env_file" -f "$base/src/docker/compose.yml" up -d
