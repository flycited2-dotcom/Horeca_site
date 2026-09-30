#!/usr/bin/env bash
# Запуск боевого сайта на gastrosnab.ru (ТЗ §17, спринт 8). Запуск на сервере под root,
# когда DNS уже указывает на сервер:
#
#   /opt/gastrosnab/src/docker/go-live.sh <почта для проверочного письма>
#
# Скрипт останавливается на первой же ошибке и ничего не переключает, пока не прошли
# проверки: DNS, копия базы, nginx, сертификат. Повторный запуск безопасен.
set -euo pipefail

base=/opt/gastrosnab
env_file="$base/.env"
src="$base/src"
server_ip=212.116.115.150
main=gastrosnab.ru
hosts=(gastrosnab.ru www.gastrosnab.ru xn--80aadf5cfnhch.xn--p1ai www.xn--80aadf5cfnhch.xn--p1ai)
mail_to="${1:?Нужна почта для проверочного письма: go-live.sh <адрес>}"

dc() { "$src/docker/dc" "$@"; }

step() { echo; echo "== $*"; }

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

step "1. DNS: все четыре имени должны вести на этот сервер ($server_ip)"
for host in "${hosts[@]}"; do
    ip=$(getent ahostsv4 "$host" | awk '{ print $1; exit }')
    if [ "$ip" != "$server_ip" ]; then
        echo "Имя $host ведёт на ${ip:-«нет записи»}, а нужен $server_ip. Поправьте A-запись у Спринтхоста и повторите." >&2
        exit 1
    fi
    echo "   $host -> $ip"
done

step "2. Копия базы перед переключением"
dc exec -T app php artisan backup:database

step "3. nginx сервера для $main"
if [ ! -e /etc/nginx/sites-enabled/$main ]; then
    install -m 644 "$src/docker/host-nginx/$main.conf" /etc/nginx/sites-available/$main
    ln -sf /etc/nginx/sites-available/$main /etc/nginx/sites-enabled/$main
fi
nginx -t
systemctl reload nginx

step "4. Сертификат HTTPS (certbot, со сквозным переходом на https)"
certbot --nginx --non-interactive --agree-tos --redirect --keep-until-expiring \
    $(printf -- '-d %s ' "${hosts[@]}")
nginx -t
systemctl reload nginx

step "5. Боевые настройки магазина в .env"
cp -a "$env_file" "$env_file.before-go-live"
set_var APP_ENV production
set_var APP_DEBUG false
set_var APP_URL "https://$main"
set_var MAIL_MAILER smtp
set_var MAIL_HOST 172.22.0.1
set_var MAIL_PORT 25
set_var MAIL_AUTO_TLS false
set_var MAIL_FROM_ADDRESS "\"shop@$main\""
# Оповещения об ошибках — в Telegram, если бот уже настроен.
if grep -Eq '^TELEGRAM_BOT_TOKEN=.+' "$env_file" && grep -Eq '^TELEGRAM_CHAT_ID=.+' "$env_file"; then
    set_var LOG_STACK daily,telegram
else
    echo "   Telegram не настроен: оповещения об ошибках пока только в журнале."
fi
docker compose --project-name gastrosnab --env-file "$env_file" -f "$src/docker/compose.yml" up -d --wait

step "6. Карта сайта, профили импорта"
dc exec -T app php artisan sitemap:generate
dc exec -T app php artisan tinker --execute='App\Models\ImportProfile::query()->update(["is_active" => true]); echo App\Models\ImportProfile::query()->where("is_active", true)->count()." профиля импорта включено".PHP_EOL;'

step "7. Проверка снаружи"
for url in "https://$main/" "https://$main/robots.txt" "https://$main/sitemap.xml" "https://$main/catalog"; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$url")
    echo "   $url -> $code"
    [ "$code" = "200" ] || { echo "Ожидался 200." >&2; exit 1; }
done
for url in "http://www.$main/catalog?x=1" "https://xn--80aadf5cfnhch.xn--p1ai/"; do
    echo "   $url -> $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 30 "$url")"
done
robots=$(curl -s --max-time 30 "https://$main/robots.txt")
if printf '%s\n' "$robots" | grep -qx 'Disallow: /' || ! printf '%s\n' "$robots" | grep -qi '^Sitemap:'; then
    echo "robots.txt всё ещё закрывает сайт от поисковиков или без карты сайта: проверьте APP_ENV." >&2
    exit 1
fi
echo "   robots.txt: боевой"

step "8. Проверочное письмо на $mail_to"
dc exec -T app php artisan mail:test "$mail_to"

echo
echo "Сайт переключён на боевой режим: https://$main"
echo "Вернуть прежние настройки: cp $env_file.before-go-live $env_file && docker compose … up -d"
