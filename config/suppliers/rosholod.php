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
    'site_photos' => [
        'url' => env('ROSHOLOD_SITE_PHOTOS_URL', 'https://rosholod.org/api/v1/prices/'),
        'media_prefix' => 'https://rosholod.org/media/products_images/',
        'page_pause_ms' => 1000,
        'photo_pause_ms' => 250,
        'timeout' => 30,
        'user_agent' => 'GastrosnabCatalog/1.0 (+https://gastrosnab.ru; photos of a Rosholod dealer)',
    ],

];
