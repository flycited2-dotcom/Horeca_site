<?php

use App\Enums\CompanyStatus;
use App\Enums\ImportTrigger;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Company;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cart\CartStore;
use App\Services\Supplier\Import\ImportRunner;
use Database\Seeders\ProductionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MariaDB database "horeca_test" (phpunit.xml),
| each inside a transaction that is rolled back afterwards.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Импорт от поставщика
|--------------------------------------------------------------------------
|
| Фикстуры вырезаны из реальных выгрузок 16.09.2026 и лежат в windows-1251,
| поэтому читаются байтами: перекодировать их нельзя (ТЗ §16).
|
*/

function rosholodFixture(string $name): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/rosholod/{$name}"));
}

/**
 * Правит фикстуру в UTF-8 и возвращает её обратно в windows-1251:
 * иначе регулярное выражение с кириллицей не найдёт кириллические теги.
 *
 * @param  callable(string): string  $edit
 */
function editRosholodFixture(string $name, callable $edit): string
{
    $utf8 = (string) mb_convert_encoding(rosholodFixture($name), 'UTF-8', 'windows-1251');

    return (string) mb_convert_encoding($edit($utf8), 'windows-1251', 'UTF-8');
}

/**
 * @param  array<string, string>  $headers
 */
function fakeRosholodFeeds(?string $catalog = null, ?string $stock = null, array $headers = [], int $status = 200): void
{
    // Http::fake() добавляет заглушки к прежним, и срабатывает первая подходящая.
    // Тест подменяет выгрузку между прогонами, поэтому клиент создаётся заново.
    Http::clearResolvedInstances();
    app()->forgetInstance(HttpFactory::class);

    Http::fake([
        '*Catalog.xml' => Http::response($catalog ?? rosholodFixture('catalog_sample.xml'), $status, $headers + ['ETag' => '"catalog-1"']),
        '*OstatkiYandex.xml' => Http::response($stock ?? rosholodFixture('stock_sample.xml'), $status, $headers + ['ETag' => '"stock-1"']),
    ]);
}

function rosholodSupplier(): Supplier
{
    test()->seed(ProductionSeeder::class);

    return Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->sole();
}

function rosholodProfile(string $source): ImportProfile
{
    return ImportProfile::query()->where('source', $source)->with('supplier')->sole();
}

function runImport(ImportProfile $profile, bool $force = false, bool $dryRun = false): ImportRun
{
    return app(ImportRunner::class)->run($profile, ImportTrigger::Cli, $force, $dryRun)->refresh();
}

/**
 * Puts a product into the cart and keeps the cart cookie for the next requests of the test,
 * as a browser does: the test client carries the session between requests, not cookies.
 */
function putInCart(Product $product, int $quantity = 1): void
{
    test()->post(route('cart.add', $product->id), ['quantity' => $quantity]);
    test()->withCookie(CartStore::COOKIE, (string) Cart::query()->latest('id')->value('session_id'));
}

/**
 * A manager or an administrator with two-factor authentication set up: the admin panel
 * lets only such staff in (TZ §12).
 */
function staffUser(UserRole $role = UserRole::Manager): User
{
    return User::factory()->create([
        'role' => $role,
        'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
    ]);
}

/**
 * Sets a shop setting (TZ §5.5) straight in the table.
 */
function setting(string $key, mixed $value): void
{
    Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
}

/**
 * A customer whose company has the given status and price tier.
 */
function wholesaleCustomer(PriceTier $tier, CompanyStatus $status = CompanyStatus::Approved): User
{
    $company = Company::factory()->create(['status' => $status, 'price_tier_id' => $tier->id]);
    $user = User::factory()->create();
    $user->forceFill(['company_id' => $company->id])->save();

    return $user->refresh();
}
