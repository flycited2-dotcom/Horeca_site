<?php

use App\Actions\Catalog\ContentSyncResult;
use App\Actions\Catalog\SaveProductByManager;
use App\Actions\Catalog\SyncSupplierDetails;
use App\Enums\AttributeType;
use App\Enums\AttributeValueSource;
use App\Jobs\SyncSupplierContentPage;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Supplier\Data\SupplierAttribute;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Import\AttributeValueParser;
use App\Services\Supplier\Sources\Rosholod\RosholodCatalogXmlSource;
use App\Services\Supplier\Sources\Rosholod\RosholodSiteSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

const DETAILS_GUID = '66fd585a-b760-11ed-9dd0-00155d0a5704';

beforeEach(function () {
    Sleep::fake();
    $this->supplier = Supplier::factory()->create(['slug' => 'rosholod']);
    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'external_id' => DETAILS_GUID,
        'description' => 'Стол холодильный СХС-2-60 — обрезанное описание из выгрузки',
    ]);
});

/**
 * A product of the supplier's list as rosholod.org gives it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function siteProduct(array $overrides = []): array
{
    return $overrides + [
        'product_id' => DETAILS_GUID,
        'title' => 'Стол холодильный СХС-2-60',
        'images' => [],
        'characteristics' => [
            'Бренд' => 'Аркто',
            'Длина, мм' => '1 350',
            'Глубина, мм' => '600',
            'Высота, мм' => '880',
            'Вес, кг' => '80',
            'Вес в упаковке, кг' => '125',
            'Гарантия (месяцев)' => '12',
            'Хладогент' => 'R290',
            'Внутренний объём, л' => '0,286',
            'Напряжение' => '220В',
            'Потребляемая мощность, Вт' => '1 300',
        ],
        'origin' => ['name' => 'РОССИЯ'],
        'description' => 'ОписаниеСтол холодильный СХС-2-60 - используется для хранения продукции.<br>Стол имеет глухую дверь.',
    ];
}

function detailsOf(Product $product): array
{
    return DB::table('attribute_product')
        ->join('attributes', 'attributes.id', '=', 'attribute_product.attribute_id')
        ->where('product_id', $product->id)
        ->orderBy('attributes.name')
        ->get(['attributes.name', 'attributes.unit', 'attribute_product.value_number', 'attribute_product.value_string', 'attribute_product.source'])
        ->mapWithKeys(fn ($row): array => [$row->name => $row->value_number !== null ? rtrim(rtrim((string) $row->value_number, '0'), '.') : $row->value_string])
        ->all();
}

it('parses the name, the unit and the number of a characteristic', function () {
    $parser = new AttributeValueParser;

    expect($parser->parse(new SupplierAttribute('Потребляемая мощность, Вт', '1 300')))
        ->toBe(['name' => 'Потребляемая мощность', 'unit' => 'Вт', 'slug' => 'potrebliaemaia-moshhnost-vt', 'number' => '1300', 'text' => '1 300'])
        ->and($parser->parse(new SupplierAttribute('Внутренний объём, л', '0,286'))['number'])->toBe('0.286')
        ->and($parser->parse(new SupplierAttribute('Напряжение', '220В')))->toMatchArray(['name' => 'Напряжение', 'unit' => null, 'number' => null, 'text' => '220В'])
        ->and($parser->parse(new SupplierAttribute('Количество ящиков, шт', 'нет'))['number'])->toBeNull()
        ->and(AttributeValueParser::integer('1 620'))->toBe(1620)
        ->and(AttributeValueParser::integer('3,4'))->toBeNull();
});

it('reads the description, dimensions and characteristics from the supplier list', function () {
    Http::preventStrayRequests();
    Http::fake(['rosholod.org/api/v1/prices/*' => Http::response(['num_pages' => 1, 'current_page' => 1, 'results' => [siteProduct()]])]);

    $details = app(RosholodSiteSource::class)->page(1)->details[0];

    expect($details->externalId)->toBe(DETAILS_GUID)
        ->and($details->description)->toBe("Стол холодильный СХС-2-60 - используется для хранения продукции.\nСтол имеет глухую дверь.")
        ->and([$details->lengthMm, $details->widthMm, $details->heightMm, $details->weightKg, $details->warrantyMonths])->toBe([1350, 600, 880, '80', 12])
        ->and(collect($details->attributes)->pluck('key')->all())->toBe([
            'Вес в упаковке, кг', 'Хладогент', 'Внутренний объём, л', 'Напряжение', 'Потребляемая мощность, Вт', 'Страна производства',
        ])
        ->and(collect($details->attributes)->last()->rawValue)->toBe('Россия');
});

it('writes the details into the product and creates characteristics that do not filter yet', function () {
    Queue::fake();
    Http::fake(['rosholod.org/api/v1/prices/*' => Http::response(['num_pages' => 1, 'current_page' => 1, 'results' => [siteProduct()]])]);

    app()->call([new SyncSupplierContentPage($this->supplier->id, 1), 'handle']);

    $product = $this->product->refresh();

    expect($product->description)->toStartWith('Стол холодильный СХС-2-60 - используется')
        ->and([$product->length_mm, $product->width_mm, $product->height_mm, $product->weight_kg, $product->warranty_months])->toBe([1350, 600, 880, '80.000', 12])
        ->and(detailsOf($product))->toBe([
            'Вес в упаковке' => '125',
            'Внутренний объём' => '0.286',
            'Напряжение' => '220В',
            'Потребляемая мощность' => '1300',
            'Страна производства' => 'Россия',
            'Хладогент' => 'R290',
        ]);

    $power = Attribute::query()->where('name', 'Потребляемая мощность')->sole();

    expect($power->type)->toBe(AttributeType::Number)
        ->and($power->unit)->toBe('Вт')
        ->and($power->is_filterable)->toBeFalse()
        ->and(Attribute::query()->where('name', 'Напряжение')->sole()->type)->toBe(AttributeType::Text);

    $this->get(route('product', $product->fresh()))
        ->assertSee("1350×600×880\u{00A0}мм")
        ->assertSee('R290');
});

it('keeps what the manager changed and what the supplier left out, drops what the supplier removed', function () {
    $sync = app(SyncSupplierDetails::class);
    $details = fn (array $attributes, ?string $description = 'Полное описание', ?int $height = 880): SupplierProductDetails => new SupplierProductDetails(
        externalId: DETAILS_GUID,
        description: $description,
        attributes: array_map(fn (string $key, string $value): SupplierAttribute => new SupplierAttribute($key, $value), array_keys($attributes), $attributes),
        lengthMm: 1350,
        widthMm: 600,
        heightMm: $height,
        weightKg: '80.5',
    );

    expect($sync->handle($this->supplier, $details(['Хладогент' => 'R290', 'Напряжение' => '220В'])))->toBe(ContentSyncResult::Updated)
        ->and($sync->handle($this->supplier, $details(['Хладогент' => 'R290', 'Напряжение' => '220В'])))->toBe(ContentSyncResult::Unchanged);

    app(SaveProductByManager::class)->handle($this->product->refresh(), ['description' => 'Описание менеджера', 'height_mm' => 900]);
    $voltage = Attribute::query()->where('name', 'Напряжение')->sole();
    DB::table('attribute_product')->where('product_id', $this->product->id)->where('attribute_id', $voltage->id)
        ->update(['value_string' => '380В', 'source' => AttributeValueSource::Manual->value]);

    $sync->handle($this->supplier, $details(['Напряжение' => '220В'], description: null, height: 1000));

    $product = $this->product->refresh();

    expect($product->description)->toBe('Описание менеджера')
        ->and($product->height_mm)->toBe(900)
        ->and($product->weight_kg)->toBe('80.500')
        ->and(detailsOf($product))->toBe(['Напряжение' => '380В'])
        ->and($sync->handle($this->supplier, new SupplierProductDetails('no-such-guid')))->toBe(ContentSyncResult::NoProduct);
});

it('lets the catalog XML fill the description of a new product only', function () {
    $capabilities = app(RosholodCatalogXmlSource::class)->capabilities();

    expect($capabilities->owns('description'))->toBeFalse()
        ->and($capabilities->seeds('description'))->toBeTrue()
        ->and(array_intersect($capabilities->ownedProductFields, SyncSupplierDetails::OWNED_FIELDS))->toBe([]);
});
