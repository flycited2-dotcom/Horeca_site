<?php

use App\Enums\ImportTrigger;
use App\Enums\SupplierRefEntity;
use App\Enums\UserRole;
use App\Filament\Resources\ImportProfiles\ImportProfileResource;
use App\Filament\Resources\ImportProfiles\Pages\ListImportProfiles;
use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Filament\Resources\ImportRuns\Pages\ListImportRuns;
use App\Filament\Resources\SupplierRefs\Pages\ListSupplierRefs;
use App\Filament\Resources\SupplierRefs\SupplierRefResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Jobs\RunSupplierImport;
use App\Models\Brand;
use App\Models\ImportRun;
use App\Models\SupplierRef;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Supplier\Import\OwnedFieldsGuard;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->supplier = rosholodSupplier();
    $this->profile = rosholodProfile('rosholod.catalog_xml');

    $this->staff = User::factory()->create([
        'role' => UserRole::Admin,
        'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
    ]);

    $this->actingAs($this->staff);
});

it('opens every import screen', function (string $resource) {
    $this->get($resource::getUrl('index'))->assertOk();
})->with([
    SupplierResource::class,
    ImportProfileResource::class,
    ImportRunResource::class,
    SupplierRefResource::class,
    WarehouseResource::class,
]);

it('shows a run with its problems and lets the log be downloaded', function () {
    $run = ImportRun::factory()->create([
        'import_profile_id' => $this->profile->id,
        'trigger' => ImportTrigger::Manual,
        'rows_total' => 380,
        'created' => 56,
        'log' => ['Категория «Прилавок» повторяется.'],
    ]);

    $this->get(ImportRunResource::getUrl('view', ['record' => $run]))
        ->assertOk()
        ->assertSee('Категория «Прилавок» повторяется.');
});

it('queues an import when the manager presses the button', function () {
    Queue::fake();

    Livewire::test(ListImportProfiles::class)
        ->callTableAction('run', $this->profile)
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(RunSupplierImport::class);
});

it('refuses to switch on a profile that fights another one for the same fields', function () {
    $this->profile->forceFill(['is_active' => true])->save();

    $other = $this->supplier->importProfiles()->create([
        'name' => 'Второй каталог',
        'source' => 'rosholod.catalog_xml',
        'url' => 'https://rosholod.org/price-lists/Catalog.xml',
        'is_active' => false,
    ]);

    expect(app(OwnedFieldsGuard::class)->conflict($other))
        ->toContain($this->profile->name);
});

it('allows a profile that owns other fields', function () {
    $this->profile->forceFill(['is_active' => true])->save();

    expect(app(OwnedFieldsGuard::class)->conflict(rosholodProfile('rosholod.stock_xml')))->toBeNull();
});

it('groups the supplier matches by entity', function () {
    SupplierRef::query()->create([
        'supplier_id' => $this->supplier->id,
        'entity' => SupplierRefEntity::Brand,
        'external_key' => 'abat',
        'name' => 'Abat',
        'local_id' => Brand::factory()->create(['name' => 'Abat'])->id,
    ]);

    Livewire::test(ListSupplierRefs::class)
        ->assertSee('Бренды')
        ->assertSee('Abat');
});

it('lets the manager hide a warehouse from the storefront', function () {
    $warehouse = Warehouse::factory()->create(['supplier_id' => $this->supplier->id, 'is_visible' => true]);

    Livewire::test(EditWarehouse::class, ['record' => $warehouse->id])
        ->fillForm(['is_visible' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($warehouse->refresh()->is_visible)->toBeFalse();
});

it('does not offer to create a run by hand', function () {
    Livewire::test(ListImportRuns::class)->assertOk();

    expect(ImportRunResource::canCreate())->toBeFalse();
});
