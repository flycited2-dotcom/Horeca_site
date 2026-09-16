<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Availability;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of a product at the moment of ordering: renaming the product
 * later must not change the order history (TZ §10.3).
 */
#[Fillable(['order_id', 'product_id', 'sku', 'supplier_code', 'name', 'unit', 'availability', 'qty', 'price', 'sum'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'qty' => 'integer',
            'price' => MoneyCast::class,
            'sum' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
