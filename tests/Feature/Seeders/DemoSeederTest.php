<?php

use App\Enums\Availability;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Enums\WarehouseStockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('builds a consistent demo catalog with users and orders', function () {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    expect(Product::query()->count())->toBe(DemoSeeder::PRODUCTS)
        ->and(Product::query()->whereNull('retail_price')->count())->toBe(15)
        ->and(Product::query()->distinct()->pluck('availability')->map->value->sort()->values()->all())
        ->toBe(collect(Availability::cases())->map->value->sort()->values()->all())
        ->and(Category::query()->whereNull('parent_id')->where('show_on_home', true)->count())->toBe(8)
        ->and(Brand::query()->count())->toBe(6)
        ->and(ProductCollection::query()->where('is_active', true)->count())->toBe(3)
        ->and(Order::query()->count())->toBe(5)
        ->and(Media::query()->count())->toBe(30);

    Product::query()->with('stocks')->get()->each(function (Product $product): void {
        $statuses = $product->stocks->pluck('status');

        match ($product->availability) {
            Availability::InStock => expect($statuses)->toContain(WarehouseStockStatus::InStock),
            Availability::Low => expect($statuses->all())->toBe([WarehouseStockStatus::Low]),
            default => expect($statuses)->toBeEmpty(),
        };
    });

    Order::query()->with('items')->get()->each(function (Order $order): void {
        $itemsTotal = $order->items->reduce(fn (Money $sum, $item): Money => $sum->add($item->sum), Money::zero());

        expect($itemsTotal->equals($order->total))->toBeTrue()
            ->and($order->subtotal->subtract($order->discount)->equals($order->total))->toBeTrue()
            ->and($order->discount->isZero())->toBe($order->type === OrderType::Retail);
    });

    expect((int) Category::query()->whereNull('parent_id')->sum('products_count'))
        ->toBe(Product::query()->where('availability', '!=', Availability::Discontinued)->count());

    $wholesaler = User::query()->where('email', 'opt@horeca.test')->firstOrFail();

    expect($wholesaler->hasApprovedCompany())->toBeTrue()
        ->and($wholesaler->company->priceTier->slug)->toBe('opt-1')
        ->and(Hash::check(DemoSeeder::PASSWORD, $wholesaler->password))->toBeTrue()
        ->and(User::query()->where('email', 'admin@horeca.test')->firstOrFail()->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', 'manager@horeca.test')->firstOrFail()->role)->toBe(UserRole::Manager);
});

it('leaves a filled catalog untouched', function () {
    Product::factory()->create();

    $this->seed(DemoSeeder::class);

    expect(Product::query()->count())->toBe(1);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    // Called directly: in production the db:seed command would ask for confirmation first.
    app(DemoSeeder::class)->setContainer(app())->__invoke();
})->throws(RuntimeException::class, 'DemoSeeder must not run in production.');
