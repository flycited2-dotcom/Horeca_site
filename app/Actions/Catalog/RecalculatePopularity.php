<?php

namespace App\Actions\Catalog;

use App\Enums\OrderStatus;
use App\Services\Catalog\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * Популярность товара (ТЗ §8.2: вторая ступень сортировки после наличия; ленты главной
 * «по популярности»): сколько заявок — кроме отменённых — и лидов было по товару за последние
 * 90 дней. Количество в строке заявки не считается: одна крупная закупка не должна перевесить
 * спрос многих покупателей. Пересчитывается раз в сутки, в 03:00 (§17.5), одним UPDATE.
 */
final class RecalculatePopularity
{
    public const int DAYS = 90;

    public function __construct(private readonly CatalogCache $cache) {}

    /**
     * @return int how many products have a non-zero popularity now
     */
    public function handle(): int
    {
        $since = now()->subDays(self::DAYS);

        $orders = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Canceled->value)
            ->where('orders.created_at', '>=', $since)
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id AS product_id, COUNT(DISTINCT orders.id) AS score')
            ->groupBy('order_items.product_id');

        $leads = DB::table('leads')
            ->where('created_at', '>=', $since)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) AS score')
            ->groupBy('product_id');

        $scores = DB::query()
            ->fromSub($orders->unionAll($leads), 'demand')
            ->selectRaw('product_id, SUM(score) AS score')
            ->groupBy('product_id');

        DB::transaction(function () use ($scores): void {
            DB::table('products')->where('popularity', '!=', 0)->update(['popularity' => 0]);

            DB::table('products')
                ->joinSub($scores, 'scores', 'scores.product_id', '=', 'products.id')
                ->update(['products.popularity' => DB::raw('scores.score')]);
        });

        $this->cache->bump();

        return DB::table('products')->where('popularity', '>', 0)->count();
    }
}
