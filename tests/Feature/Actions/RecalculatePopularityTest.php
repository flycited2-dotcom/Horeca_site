<?php

use App\Enums\LeadType;
use App\Enums\OrderStatus;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

it('counts orders and leads of the last 90 days and nothing else', function () {
    [$wanted, $canceled, $old, $forgotten] = Product::factory()->count(4)->create()->all();
    $forgotten->forceFill(['popularity' => 7])->save();

    foreach ([1, 10] as $qty) {
        OrderItem::factory()->create(['order_id' => Order::factory()->create(['status' => OrderStatus::Confirmed])->id, 'product_id' => $wanted->id, 'qty' => $qty]);
    }
    Lead::factory()->create(['type' => LeadType::PriceRequest, 'product_id' => $wanted->id]);

    OrderItem::factory()->create(['order_id' => Order::factory()->create(['status' => OrderStatus::Canceled])->id, 'product_id' => $canceled->id]);
    $deleted = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $deleted->id, 'product_id' => $canceled->id]);
    $deleted->delete();

    $oldOrder = Order::factory()->create();
    $oldOrder->forceFill(['created_at' => now()->subDays(91)])->save();
    OrderItem::factory()->create(['order_id' => $oldOrder->id, 'product_id' => $old->id]);

    $this->artisan('popularity:recalculate')
        ->assertSuccessful()
        ->expectsOutputToContain('спрос за 90 дней есть у 1 товаров');

    expect($wanted->refresh()->popularity)->toBe(3)
        ->and($canceled->refresh()->popularity)->toBe(0)
        ->and($old->refresh()->popularity)->toBe(0)
        ->and($forgotten->refresh()->popularity)->toBe(0);
});
