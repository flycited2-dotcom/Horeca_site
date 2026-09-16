<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

it('creates every table of the data model', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'users', 'companies', 'price_tiers',
    'categories', 'brands', 'suppliers', 'supplier_refs', 'products', 'media', 'warehouses', 'product_stocks',
    'attributes', 'attribute_product', 'product_prices', 'related_products', 'collections', 'collection_product',
    'import_profiles', 'import_runs', 'import_rows',
    'carts', 'cart_items', 'orders', 'order_counters', 'order_items', 'order_status_logs', 'leads', 'favorites',
    'pages', 'redirects', 'settings',
    'sessions', 'cache', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
]);

it('implements down() in every migration', function () {
    foreach (glob(database_path('migrations/*.php')) as $file) {
        $migration = require $file;

        expect($migration)->toBeInstanceOf(Migration::class)
            ->and(method_exists($migration, 'down'))->toBeTrue('Migration ['.basename($file).'] has no down() method.');
    }
});

it('keeps a supplier product external id unique within the supplier', function () {
    $product = Product::factory()->create();

    Product::factory()->create([
        'supplier_id' => $product->supplier_id,
        'external_id' => $product->external_id,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('allows the same external id at different suppliers', function () {
    $product = Product::factory()->create();
    Product::factory()->create(['external_id' => $product->external_id]);

    expect(Product::query()->where('external_id', $product->external_id)->count())->toBe(2);
});
