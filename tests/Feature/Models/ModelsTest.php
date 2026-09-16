<?php

use App\Enums\Availability;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use App\Support\Percent;
use Illuminate\Support\Facades\DB;

it('stores money exactly and reads it back as Money', function () {
    $product = Product::factory()->create([
        'rrp_price' => Money::fromDecimal('48605.80'),
        'retail_price' => Money::fromDecimal('48605.80'),
    ]);

    $fresh = $product->fresh();

    expect($fresh->retail_price)->toBeInstanceOf(Money::class)
        ->and($fresh->retail_price->kopecks)->toBe(4_860_580)
        ->and(DB::table('products')->where('id', $product->id)->value('retail_price'))->toBe('48605.80');
});

it('refuses to store a float as money', function () {
    Product::factory()->make()->retail_price = 100.5;
})->throws(InvalidArgumentException::class);

it('stores percents as basis points', function () {
    $supplier = Supplier::factory()->create(['markup_percent' => Percent::fromDecimal('35.5')]);

    expect($supplier->fresh()->markup_percent->basisPoints)->toBe(3_550);
});

it('treats a product without a retail price as price on request', function () {
    expect(Product::factory()->priceOnRequest()->create()->isPriceOnRequest())->toBeTrue()
        ->and(Product::factory()->create()->isPriceOnRequest())->toBeFalse();
});

it('derives the availability rank from availability on every save', function (Availability $availability, int $rank) {
    $product = Product::factory()->create();

    $product->update(['availability' => $availability]);

    expect($product->fresh()->availability_rank)->toBe($rank);
})->with([
    [Availability::InStock, 1],
    [Availability::Low, 1],
    [Availability::Incoming, 2],
    [Availability::OnOrder, 3],
    [Availability::Discontinued, 4],
]);

it('connects the catalog models', function () {
    $stock = ProductStock::factory()->create();
    $product = $stock->product;
    $attribute = Attribute::factory()->create(['unit' => 'кВт']);
    $tier = PriceTier::factory()->create();
    $related = Product::factory()->create();
    $collection = ProductCollection::factory()->create();

    $product->attributeValues()->attach($attribute, ['value_number' => '7.500', 'raw_value' => '7,5 кВт']);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price_tier_id' => $tier->id]);
    $product->relatedProducts()->attach($related, ['sort' => 10]);
    $collection->products()->attach($product, ['sort' => 10]);

    $product = Product::query()
        ->with(['supplier', 'category', 'brand', 'stocks.warehouse', 'attributeValues', 'prices.priceTier', 'relatedProducts', 'collections'])
        ->findOrFail($product->id);

    expect($product->stocks->first()->warehouse->is($stock->warehouse))->toBeTrue()
        ->and($product->attributeValues->first()->pivot->raw_value)->toBe('7,5 кВт')
        ->and($product->prices->first()->priceTier->is($tier))->toBeTrue()
        ->and($product->relatedProducts->first()->is($related))->toBeTrue()
        ->and($product->collections->first()->is($collection))->toBeTrue()
        ->and($product->category->products->first()->is($product))->toBeTrue()
        ->and($product->brand->products)->toHaveCount(1)
        ->and($product->supplier->products)->toHaveCount(1);
});

it('connects users, companies and orders', function () {
    $user = User::factory()->withApprovedCompany()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    OrderStatusLog::factory()->create(['order_id' => $order->id, 'user_id' => $user->id]);

    expect($user->hasApprovedCompany())->toBeTrue()
        ->and($user->company->priceTier)->toBeInstanceOf(PriceTier::class)
        ->and($user->orders)->toHaveCount(1)
        ->and($order->items)->toHaveCount(2)
        ->and($order->statusLogs->first()->user->is($user))->toBeTrue()
        ->and($order->company->users->first()->is($user))->toBeTrue();
});

it('does not mass assign role, activity or company of a user', function () {
    $user = new User([
        'name' => 'Злоумышленник',
        'email' => 'intruder@horeca.test',
        'password' => 'secret-password',
        'role' => 'admin',
        'is_active' => false,
        'company_id' => 5,
    ]);

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->is_active)->toBeTrue()
        ->and($user->company_id)->toBeNull();
});

it('keeps two-factor secrets encrypted and hidden', function () {
    $user = User::factory()->create(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

    expect(DB::table('users')->where('id', $user->id)->value('app_authentication_secret'))->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->toArray())->not->toHaveKey('app_authentication_secret');
});

it('stores settings of any JSON type', function (mixed $value) {
    $setting = Setting::factory()->create(['value' => $value]);

    expect($setting->fresh()->value)->toBe($value);
})->with([
    'null' => [null],
    'integer' => [3],
    'boolean' => [false],
    'string' => ['Симферополь'],
    'list' => [['+7 978 000-00-00', '+7 978 111-11-11']],
]);
