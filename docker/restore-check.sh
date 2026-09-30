#!/usr/bin/env bash
# Проверка восстановления (ТЗ §17.8): последняя копия базы разворачивается в одноразовой
# MariaDB и сверяется с рабочей базой. Рабочая база и сайт не затрагиваются: копия
# разворачивается в отдельном контейнере, который удаляется в конце вместе с данными.
# Запускается на сервере раз в месяц и один раз обязательно до запуска (ТЗ §19):
#   /opt/gastrosnab/src/docker/restore-check.sh [имя файла копии]
# Результат дописывается в /opt/gastrosnab/restore-check.log.
set -euo pipefail

base=/opt/gastrosnab
dc="$base/src/docker/dc"
check=gastrosnab-restore-check
password=check-$RANDOM$RANDOM
work=$(mktemp /tmp/restore-check.XXXXXX)
started=$(date '+%Y-%m-%d %H:%M:%S')

cleanup() {
    docker rm -f -v "$check" > /dev/null 2>&1 || true
    rm -f "$work"
}
trap cleanup EXIT

# Таблицы, по которым сверяются строки: каталог, продажи, клиенты, содержимое сайта.
tables="products categories brands attributes attribute_product orders order_items leads users companies pages settings redirects"

counts_sql=""
for table in $tables; do
    counts_sql+="SELECT '$table', COUNT(*) FROM \`$table\` UNION ALL "
done
counts_sql+="SELECT 'ТАБЛИЦ', COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();"

name="${1:-}"
if [ -z "$name" ]; then
    name=$($dc exec -T app sh -c 'ls -1 storage/app/backups | grep -E "^horeca-[0-9]{8}-[0-9]{6}\.sql\.gz$" | sort | tail -n 1' | tr -d '\r')
fi
if [ -z "$name" ]; then
    echo "Копий базы на сервере нет: сначала php artisan backup:database." >&2
    exit 1
fi

echo "Копия: $name"
$dc cp "app:/var/www/html/storage/app/backups/$name" "$work"
size=$(du -m "$work" | cut -f1)

echo "Поднимаю одноразовую MariaDB..."
docker run -d --name "$check" --memory=1g \
    -e MARIADB_ROOT_PASSWORD="$password" -e MARIADB_DATABASE=horeca_restore_check \
    mariadb:11.8 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci \
    --innodb-buffer-pool-size=128M > /dev/null

# Готовность — по TCP: временный сервер первичной настройки слушает только сокет, и по нему
# «готово» приходит раньше, чем сервер перезапустится.
ready=0
for _ in $(seq 1 90); do
    if docker exec "$check" mariadb-admin -h127.0.0.1 -P3306 -uroot -p"$password" ping > /dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 2
done
if [ "$ready" -ne 1 ]; then
    echo "Одноразовая MariaDB не поднялась за 3 минуты." >&2
    exit 1
fi

echo "Разворачиваю копию ($size МБ в сжатом виде)..."
gunzip -c "$work" | docker exec -i "$check" mariadb -uroot -p"$password" --default-character-set=utf8mb4 horeca_restore_check

restored=$(docker exec "$check" mariadb -uroot -p"$password" -N horeca_restore_check -e "$counts_sql")
live=$($dc exec -T mariadb sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -N "$MARIADB_DATABASE" -e "$0"' "$counts_sql" | tr -d '\r')

echo
printf '%-20s %12s %12s\n' "таблица" "в копии" "сейчас"
failed=0
while read -r table count; do
    now=$(printf '%s\n' "$live" | awk -v t="$table" '$1 == t { print $2 }')
    mark=""
    if [ "$table" = "ТАБЛИЦ" ] && [ "$count" != "$now" ]; then
        mark="  <-- число таблиц не совпало"
        failed=1
    fi
    printf '%-20s %12s %12s%s\n' "$table" "$count" "$now" "$mark"
done <<< "$restored"

products=$(printf '%s\n' "$restored" | awk '$1 == "products" { print $2 }')
if [ "${products:-0}" -eq 0 ]; then
    echo "В восстановленной базе нет товаров." >&2
    failed=1
fi

echo
if [ "$failed" -eq 0 ]; then
    result="ПРОЙДЕНА"
    echo "Проверка восстановления пройдена. Расхождения в строках допустимы: копия сделана раньше, чем идёт сверка."
else
    result="НЕ ПРОЙДЕНА"
    echo "Проверка восстановления НЕ пройдена." >&2
fi

echo "$started  $name  $size МБ  товаров в копии: ${products:-0}  $result" >> "$base/restore-check.log"
exit "$failed"
