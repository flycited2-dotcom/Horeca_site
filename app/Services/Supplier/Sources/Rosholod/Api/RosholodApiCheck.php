<?php

namespace App\Services\Supplier\Sources\Rosholod\Api;

use App\Enums\SupplierRefEntity;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierRef;
use App\Models\Warehouse;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * Пробный прогон Dealer API Росхолода (ТЗ §6): только чтение, ничего в каталоге не меняется.
 * Отвечает на вопросы, без которых нельзя включать API: принимает ли он токен и какие права
 * есть, каким полем товар API связывается с нашим (`source_id`, `id`, код или артикул), сколько
 * наших товаров он знает и чего у него нет у нас, как устроены виды номенклатуры, склады и
 * характеристики. Токен в сводку не попадает.
 */
final class RosholodApiCheck
{
    /**
     * Права токена и маршрут, которым каждое проверяется.
     */
    private const array ACCESS = [
        'catalog:read' => '/api/v1/dealer/brands',
        'prices:read' => '/api/v1/dealer/prices',
        'stocks:read' => '/api/v1/dealer/stocks',
    ];

    public function __construct(private readonly RosholodApiClient $api) {}

    /**
     * @return list<string> the lines of the report
     *
     * @throws FeedReadException when the token is not accepted at all
     */
    public function run(Supplier $supplier, bool $withPrices = false, bool $withStocks = false): array
    {
        $lines = [];

        $lines[] = '== Доступ ==';
        $lines = [...$lines, ...$this->access()];

        $lines[] = '';
        $lines[] = '== Товары (/products) ==';
        $lines = [...$lines, ...$this->products($supplier)];

        $lines[] = '';
        $lines[] = '== Виды номенклатуры (/product-types) ==';
        $lines = [...$lines, ...$this->productTypes($supplier)];

        $lines[] = '';
        $lines[] = '== Склады (/warehouses) ==';
        $lines = [...$lines, ...$this->warehouses($supplier)];

        $lines[] = '';
        $lines[] = '== Полная карточка: образец (/products/export, одна страница) ==';
        $lines = [...$lines, ...$this->sample()];

        if ($withPrices) {
            $lines[] = '';
            $lines[] = '== Цены (/prices, полный обход) ==';
            $lines = [...$lines, ...$this->prices()];
        }

        if ($withStocks) {
            $lines[] = '';
            $lines[] = '== Остатки (/stocks, полный обход) ==';
            $lines = [...$lines, ...$this->stocks()];
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function access(): array
    {
        $lines = [];

        foreach (self::ACCESS as $right => $path) {
            try {
                $this->api->get($path, ['limit' => 1]);
                $lines[] = $right.': есть';
            } catch (FeedReadException $exception) {
                $lines[] = $right.': нет — '.$exception->getMessage();
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function products(Supplier $supplier): array
    {
        $field = config('suppliers.rosholod.api.external_id_field') === 'id' ? 'id' : 'source_id';
        $api = ['total' => 0, 'no_source_id' => 0, 'no_article' => 0, 'no_images' => 0, 'no_default_image' => 0, 'images' => 0];
        $hosts = [];
        $unmatched = [];

        $local = Product::query()->where('supplier_id', $supplier->id)->get(['external_id', 'supplier_code', 'sku']);
        $externalIds = array_flip($local->pluck('external_id')->filter()->all());
        $localBy = [
            'source_id' => $externalIds,
            'id' => $externalIds,
            'code' => array_flip($local->pluck('supplier_code')->filter()->all()),
            'article' => array_flip($local->pluck('sku')->filter()->all()),
        ];
        $matched = ['source_id' => 0, 'id' => 0, 'code' => 0, 'article' => 0];

        foreach ($this->api->items('/api/v1/dealer/products') as $item) {
            $api['total']++;
            $api['no_source_id'] += blank($item['source_id'] ?? null) ? 1 : 0;
            $api['no_article'] += blank($item['article'] ?? null) ? 1 : 0;

            $images = is_array($item['images'] ?? null) ? $item['images'] : [];
            $api['images'] += count($images);
            $api['no_images'] += $images === [] ? 1 : 0;
            $api['no_default_image'] += $images !== [] && ! in_array(true, array_column($images, 'is_default'), true) ? 1 : 0;

            foreach ($images as $image) {
                $host = parse_url((string) ($image['url'] ?? ''), PHP_URL_HOST);

                if (is_string($host)) {
                    $hosts[$host] = ($hosts[$host] ?? 0) + 1;
                }
            }

            foreach (array_keys($matched) as $key) {
                $value = $item[$key] ?? null;

                if (is_string($value) && isset($localBy[$key][$value])) {
                    $matched[$key]++;
                }
            }

            $chosen = $item[$field] ?? null;

            if (! (is_string($chosen) && isset($externalIds[$chosen])) && count($unmatched) < 5) {
                $unmatched[] = ($item['code'] ?? '?').' · '.($item['name'] ?? '?');
            }
        }

        $lines = [
            'в API: '.$api['total'].', у нас у поставщика: '.$local->count(),
            'без source_id: '.$api['no_source_id'].', без артикула: '.$api['no_article'],
            'фото: всего '.$api['images'].', товаров без фото '.$api['no_images'].', без основного '.$api['no_default_image'],
            'хосты фото: '.($hosts === [] ? '—' : implode(', ', array_map(fn (string $host, int $count): string => "{$host} ({$count})", array_keys($hosts), $hosts))),
            'разрешённые хосты фото: '.implode(', ', (array) config('suppliers.rosholod.api.media_hosts')),
            '',
            'совпадение с нашими товарами (сколько товаров API нашлось у нас):',
            '  source_id ↔ external_id: '.$matched['source_id'],
            '  id ↔ external_id: '.$matched['id'],
            '  code ↔ supplier_code: '.$matched['code'],
            '  article ↔ sku: '.$matched['article'],
            'выбрано в настройке («'.$field.'»): '.$matched[$field].' из '.$api['total'],
            'у нас есть, а в API нет: '.max(0, $local->count() - $matched[$field]),
        ];

        if ($unmatched !== []) {
            $lines[] = 'товары API, которых нет у нас (первые 5): '.implode('; ', $unmatched);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function productTypes(Supplier $supplier): array
    {
        $types = [];

        foreach ($this->api->items('/api/v1/dealer/product-types') as $type) {
            $types[(string) $type['id']] = $type;
        }

        $roots = array_filter($types, fn (array $type): bool => $type['parent_id'] === null);
        $orphans = array_filter($types, fn (array $type): bool => $type['parent_id'] !== null && ! isset($types[$type['parent_id']]));
        $depth = 0;

        foreach ($types as $type) {
            $level = 1;

            for ($parent = $type['parent_id'] ?? null; $parent !== null && isset($types[$parent]) && $level < 20; $parent = $types[$parent]['parent_id'] ?? null) {
                $level++;
            }

            $depth = max($depth, $level);
        }

        $ours = SupplierRef::query()
            ->where('supplier_id', $supplier->id)
            ->where('entity', SupplierRefEntity::Category)
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->flip();

        $same = collect($types)->filter(fn (array $type): bool => $ours->has(mb_strtolower(trim((string) $type['name']))))->count();

        return [
            'видов: '.count($types).', корневых: '.count($roots).', глубина: '.$depth.', без родителя в выдаче: '.count($orphans),
            'корневые: '.(implode('; ', array_map(fn (array $type): string => (string) $type['name'], array_slice($roots, 0, 25))) ?: '—'),
            'категорий поставщика у нас: '.$ours->count().', из видов API совпали по названию: '.$same,
        ];
    }

    /**
     * @return list<string>
     */
    private function warehouses(Supplier $supplier): array
    {
        $ours = Warehouse::query()->where('supplier_id', $supplier->id)->pluck('name')->map(fn (string $name): string => mb_strtolower(trim($name)))->flip();
        $lines = [];
        $total = 0;
        $same = 0;

        foreach ($this->api->items('/api/v1/dealer/warehouses') as $warehouse) {
            $total++;
            $match = $ours->has(mb_strtolower(trim((string) $warehouse['name'])));
            $same += $match ? 1 : 0;
            $lines[] = '  '.($match ? '[есть у нас] ' : '[нет у нас]  ').$warehouse['name'].' — '.$warehouse['city'];
        }

        return ['складов в API: '.$total.', у нас: '.$ours->count().', совпали по названию: '.$same, ...$lines];
    }

    /**
     * @return list<string>
     */
    private function sample(): array
    {
        $page = $this->api->get('/api/v1/dealer/products/export', ['limit' => (int) config('suppliers.rosholod.api.export_limit')]);
        $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        $keys = [];
        $times = [];
        $options = [];
        $withDescription = 0;

        foreach ($items as $item) {
            $withDescription += filled($item['description'] ?? null) ? 1 : 0;

            foreach (array_keys(is_array($item['attributes'] ?? null) ? $item['attributes'] : []) as $key) {
                $keys[$key] = ($keys[$key] ?? 0) + 1;
            }

            $times[(string) ($item['production_time'] ?? '—')] = true;
            $options[(string) ($item['order_option'] ?? '—')] = true;
        }

        arsort($keys);

        return [
            'карточек на странице: '.count($items).', с описанием: '.$withDescription,
            'ключи характеристик: '.($keys === [] ? '—' : implode('; ', array_map(fn (string $key, int $count): string => "{$key} ({$count})", array_keys($keys), $keys))),
            'production_time: '.implode(', ', array_slice(array_keys($times), 0, 10)),
            'order_option: '.implode(', ', array_slice(array_keys($options), 0, 10)),
        ];
    }

    /**
     * @return list<string>
     */
    private function prices(): array
    {
        $total = 0;
        $zero = 0;
        $stale = 0;
        $products = [];

        foreach ($this->api->items('/api/v1/dealer/prices') as $price) {
            $total++;
            $zero += (float) ($price['price'] ?? 0) === 0.0 ? 1 : 0;
            $stale += blank($price['last_received_at'] ?? null) ? 1 : 0;
            $products[$price['product_id'] ?? ''] = true;
        }

        return ['строк цен: '.$total.', товаров: '.count($products).', с нулевой ценой: '.$zero.', без времени получения: '.$stale];
    }

    /**
     * @return list<string>
     */
    private function stocks(): array
    {
        $total = 0;
        $zero = 0;
        $products = [];
        $warehouses = [];

        foreach ($this->api->items('/api/v1/dealer/stocks') as $stock) {
            $total++;
            $zero += (int) ($stock['available'] ?? 0) === 0 ? 1 : 0;
            $products[$stock['product_id'] ?? ''] = true;
            $warehouses[$stock['warehouse_id'] ?? ''] = ($warehouses[$stock['warehouse_id'] ?? ''] ?? 0) + 1;
        }

        return ['строк остатков: '.$total.', товаров: '.count($products).', складов: '.count($warehouses).', нулевых: '.$zero];
    }
}
