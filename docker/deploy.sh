#!/usr/bin/env bash
# Выкладка магазина на сервер (ТЗ §17.7). С компьютера разработки, в Git Bash:
#
#   git archive --format=tar.gz -o /tmp/gastrosnab.tar.gz HEAD \
#     && scp /tmp/gastrosnab.tar.gz gastrosnab:/tmp/ \
#     && ssh gastrosnab 'tar -xzOf /tmp/gastrosnab.tar.gz docker/deploy.sh | bash -s /tmp/gastrosnab.tar.gz'
#
# Скрипт берётся из того же архива, что и код, поэтому команда одна и для первой выкладки.
# Новый образ собирается, пока работает прежний; сайт закрыт только на время миграций.
# Прежняя версия кода остаётся в src.old до следующей выкладки.
set -euo pipefail

archive="${1:?Нужен путь к архиву кода}"
base=/opt/gastrosnab
release="$base/src.new"

if [ ! -f "$base/.env" ]; then
    echo "Нет $base/.env — сначала первичная установка (README, раздел «Сервер»)." >&2
    exit 1
fi

# Скрипт приходит в bash через стандартный ввод, поэтому docker compose ввод не получает:
# иначе `run` и `exec` съедят остаток скрипта, и выкладка оборвётся на середине.
compose() {
    local dir="$1"
    shift
    docker compose --project-name gastrosnab --env-file "$base/.env" -f "$dir/docker/compose.yml" "$@" < /dev/null
}

rm -rf "$release"
mkdir -p "$release"
tar -xz -C "$release" -f "$archive"
rm -f "$archive"
ln -s ../.env "$release/.env"

compose "$release" build

# Сайт закрывается до запуска новых контейнеров, в том числе при первой выкладке: флаг лежит
# в общем томе storage, и пока он стоит, планировщик и очереди ждут миграций.
compose "$release" run --rm --no-deps app php artisan down --render=errors::503

rm -rf "$base/src.old"
if [ -d "$base/src" ]; then
    mv "$base/src" "$base/src.old"
fi
mv "$release" "$base/src"

# Страница «Обновляем сайт» для nginx сервера: он показывает её, пока контейнеры перезапускаются.
install -D -m 644 "$base/src/docker/nginx/maintenance.html" /var/www/gastrosnab/maintenance.html

# --wait: миграции и открытие сайта — только когда PHP-FPM готов, иначе nginx отдаст 502.
compose "$base/src" up -d --wait --remove-orphans
compose "$base/src" exec -T app php artisan migrate --force
compose "$base/src" exec -T app php artisan up

# Прежние образы магазина; образы других проектов сервера не трогаются.
docker image prune -f --filter label=project=gastrosnab
