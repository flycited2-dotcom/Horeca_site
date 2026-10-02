<?php

/*
|--------------------------------------------------------------------------
| Rosholod XML feeds
|--------------------------------------------------------------------------
|
| Tag names of the public feeds as checked on 16.09.2026 (docs/supplier-data-2026-09-16.md).
| Nodes are matched by local name, so the namespace of OstatkiYandex.xml does not matter.
|
*/

return [

    'catalog' => [
        'category_element' => 'category',
        'product_element' => 'ДетальнаяЗапись',
        'fields' => [
            'external_id' => 'ID',
            'supplier_code' => 'Код',
            'sku' => 'Артикул',
            'model' => 'Модель',
            'name' => 'НаименованиеПолное',
            'description' => 'ДополнительноеОписаниеНоменклатурыОписание',
            'brand' => 'ТорговаяМарка',
            'category' => 'categoryId',
            'price' => 'Цена',
        ],
    ],

    'stock' => [
        'product_element' => 'item',
        'external_id' => 'ID',
        'stock_element' => 'stock',
        'warehouse' => 'namestock',
        'balance' => 'balance',
        'unit' => 'unitstock',
    ],

    /*
     * Text balance => warehouse stock status (TZ §6.5). Values are compared in lower case.
     * An unknown value is treated as "out" and reported in the run log.
     */
    'stock_values' => [
        'много' => 'in_stock',
        'в наличии' => 'in_stock',
        'несколько' => 'low',
        '0' => 'out',
    ],

    /*
     * Photos from the product list of the supplier's site until its API is issued (TZ §6,
     * the customer's decision of 23.09.2026 as a Rosholod dealer). Pages of 20 products, one
     * page a second; photos are taken only from the supplier's media folder.
     */
    'site_content' => [
        'url' => env('ROSHOLOD_SITE_CONTENT_URL', 'https://rosholod.org/api/v1/prices/'),
        'media_prefix' => 'https://rosholod.org/media/products_images/',
        'page_pause_ms' => 1000,
        'photo_pause_ms' => 250,
        'timeout' => 30,
        'user_agent' => 'GastrosnabCatalog/1.0 (+https://gastrosnab.ru; photos of a Rosholod dealer)',
    ],

    /*
     * Откуда берутся фото, описания и характеристики: «site» — список товаров на сайте Росхолода
     * (до API), «api» — Dealer API (ТЗ §6). Переключается без выкладки: SUPPLIER_CONTENT_SOURCE.
     */
    'content_source' => env('SUPPLIER_CONTENT_SOURCE', 'site'),

    /*
     * Dealer API Росхолода, только чтение (ответ поставщика от 02.10.2026, docs/supplier-api-2026-10-02.md):
     * на токен пять запросов в секунду, списки — по курсору next_cursor, фото скачиваются без токена
     * и хранятся у нас. Токен выдан на 14 дней для проверки; вводит его заказчик на сервере
     * (docker/set-rosholod.sh), в git его нет.
     */
    'api' => [
        'base_url' => env('ROSHOLOD_API_URL', 'https://api.rosholod.org'),
        'token' => env('ROSHOLOD_API_TOKEN'),
        'requests_per_second' => 4,
        'timeout' => 30,
        'user_agent' => 'GastrosnabCatalog/1.0 (+https://gastrosnab.ru; Rosholod dealer)',

        // Каким полем товара API связывается с нашим `external_id` (GUID из XML): `source_id` —
        // исходный идентификатор из учётной системы, `id` — идентификатор самого API. Проверяется
        // командой supplier:api-check.
        'external_id_field' => env('ROSHOLOD_API_EXTERNAL_ID', 'source_id'),

        // Фото берутся только с этих хостов (и их поддоменов); пауза между скачиваниями — как
        // просит поставщик, «ограничивать число одновременных скачиваний».
        'media_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('ROSHOLOD_API_MEDIA_HOSTS', 'rosholod.org'))))),
        'photo_pause_ms' => 250,
        'page_pause_ms' => 1000,
        'export_limit' => 20,
    ],

];
