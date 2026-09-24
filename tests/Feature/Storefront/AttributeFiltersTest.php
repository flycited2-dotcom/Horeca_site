<?php

use App\Actions\Catalog\MarkRecommendedFilters;
use App\Enums\AttributeType;
use App\Livewire\CategoryListing;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\AttributeFacet;
use App\Services\Catalog\CatalogFilters;
use App\Support\Typography;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Плиты электрические', 'slug' => 'plity', 'is_active' => true]);
    $this->power = Attribute::factory()->create(['name' => 'Мощность', 'unit' => 'Вт', 'slug' => 'moshhnost-vt', 'type' => AttributeType::Number, 'is_filterable' => true, 'sort' => 10]);
    $this->voltage = Attribute::factory()->create(['name' => 'Напряжение', 'unit' => null, 'slug' => 'napriazenie', 'type' => AttributeType::Text, 'is_filterable' => true, 'sort' => 13]);
    $this->burners = Attribute::factory()->create(['name' => 'Количество конфорок', 'unit' => null, 'slug' => 'kolicestvo-konforok', 'type' => AttributeType::Text, 'is_filterable' => true, 'sort' => 15]);

    // Шесть плит: 2 и 4 конфорки, 220В и 380В, мощность от 3 000 до 18 000 Вт.
    $this->stoves = collect([
        ['ЭП-2ЖШ', 3000, '220В', '2'],
        ['ЭП-2Ж', 4000, '220В', '2'],
        ['ЭП-4ЖШ', 12000, '380В', '4'],
        ['ЭП-4Ж', 12000, '380В', '4'],
        ['ЭП-6ЖШ', 18000, '380В', '6'],
        ['ЭП-4П', 8000, '380В', '4'],
    ])->map(function (array $stove): Product {
        [$name, $power, $voltage, $burners] = $stove;
        $product = Product::factory()->create(['name' => "Плита {$name}", 'category_id' => $this->category->id]);
        $product->attributeValues()->attach($this->power->id, ['value_number' => $power, 'raw_value' => (string) $power]);
        $product->attributeValues()->attach($this->voltage->id, ['value_string' => $voltage, 'raw_value' => $voltage]);
        $product->attributeValues()->attach($this->burners->id, ['value_string' => $burners, 'raw_value' => $burners]);

        return $product;
    });
});

function stoveListing(array $query = []): Testable
{
    return Livewire::withQueryParams($query)->test(CategoryListing::class, [
        'category' => test()->category,
        'title' => test()->category->name,
    ]);
}

/**
 * @param  list<AttributeFacet>  $facets
 * @return array<string, array<string, int>>
 */
function facetCounts(array $facets): array
{
    return collect($facets)->reject->isRange()->mapWithKeys(fn (AttributeFacet $facet): array => [
        $facet->slug => collect($facet->options)->mapWithKeys(fn (array $option): array => [(string) $option['value'] => $option['count']])->all(),
    ])->all();
}

it('shows the characteristics that mean something in the section, in the manager order', function () {
    // Twelve products: a characteristic of two of them is under a quarter of the section.
    Product::factory()->count(6)->create(['category_id' => $this->category->id]);

    $rare = Attribute::factory()->create(['name' => 'Цвет', 'unit' => null, 'type' => AttributeType::Text, 'is_filterable' => true]);
    $this->stoves->first()->attributeValues()->attach($rare->id, ['value_string' => 'Белый']);
    $this->stoves->last()->attributeValues()->attach($rare->id, ['value_string' => 'Чёрный']);

    $same = Attribute::factory()->create(['name' => 'Материал', 'unit' => null, 'type' => AttributeType::Text, 'is_filterable' => true]);
    $hidden = Attribute::factory()->create(['name' => 'Хладогент', 'unit' => null, 'type' => AttributeType::Text, 'is_filterable' => false]);

    foreach ($this->stoves as $index => $stove) {
        $stove->attributeValues()->attach([
            $same->id => ['value_string' => 'Нержавеющая сталь'],
            $hidden->id => ['value_string' => $index % 2 === 0 ? 'R290' : '-'],
        ]);
    }

    stoveListing()
        ->assertViewHas('attributeFacets', fn (array $facets): bool => collect($facets)->pluck('slug')->all() === ['moshhnost-vt', 'napriazenie', 'kolicestvo-konforok'])
        ->assertViewHas('attributeFacets', fn (array $facets): bool => $facets[0]->min === '3000.000' && $facets[0]->max === '18000.000')
        ->assertViewHas('attributeFacets', fn (array $facets): bool => facetCounts($facets) === [
            'napriazenie' => ['220В' => 2, '380В' => 4],
            'kolicestvo-konforok' => ['2' => 2, '4' => 3, '6' => 1],
        ])
        ->assertSeeInOrder(['Мощность, Вт', 'Напряжение', 'Количество конфорок'])
        ->assertSee('placeholder="18'.Typography::NBSP.'000"', false)
        ->assertSee('name="attr[napriazenie][values][]"', false)
        ->assertDontSee('Цвет')
        ->assertDontSee('Материал')
        ->assertDontSee('Хладогент');
});

it('narrows the list by ticked values and counts each characteristic under the other filters', function () {
    stoveListing()
        ->call('toggleAttributeValue', 'napriazenie', '380В')
        ->assertSet('attr', ['napriazenie' => ['values' => ['380В']]])
        ->assertSee('Плита ЭП-6ЖШ')
        ->assertDontSee('Плита ЭП-2ЖШ')
        ->assertSee('Напряжение: 380В')
        ->assertViewHas('attributeFacets', fn (array $facets): bool => facetCounts($facets) === [
            'napriazenie' => ['220В' => 2, '380В' => 4],
            'kolicestvo-konforok' => ['4' => 3, '6' => 1],
        ])
        ->call('toggleAttributeValue', 'kolicestvo-konforok', '6')
        ->assertViewHas('attributeFacets', fn (array $facets): bool => facetCounts($facets)['napriazenie'] === ['380В' => 1])
        ->assertSee('Плита ЭП-6ЖШ')
        ->assertDontSee('Плита ЭП-4ЖШ')
        ->call('toggleAttributeValue', 'napriazenie', '380В')
        ->assertSet('attr', ['kolicestvo-konforok' => ['values' => ['6']]])
        ->call('removeFilter', 'attr', 'kolicestvo-konforok')
        ->assertSet('attr', [])
        ->assertSee('Плита ЭП-2ЖШ');
});

it('keeps a ticked value that the other filters leave empty, so it can be unticked', function () {
    stoveListing(['attr' => ['napriazenie' => ['values' => ['220В']]], 'price_to' => '1'])
        ->assertViewHas('attributeFacets', fn (array $facets): bool => collect($facets[1]->options)->firstWhere('value', '220В') === ['value' => '220В', 'count' => 0, 'selected' => true]);
});

it('takes a power range from a shared link, with spaces and a decimal comma', function () {
    stoveListing(['attr' => ['moshhnost-vt' => ['min' => '8 000', 'max' => '12000,5']]])
        ->assertSee('Плита ЭП-4П')
        ->assertSee('Плита ЭП-4ЖШ')
        ->assertDontSee('Плита ЭП-6ЖШ')
        ->assertDontSee('Плита ЭП-2Ж')
        ->assertSee('Мощность: 8'.Typography::NBSP.'000 — 12'.Typography::NBSP.'000,5'.Typography::NBSP.'Вт', false)
        ->set('attr.moshhnost-vt.max', '')
        ->assertSet('attr', ['moshhnost-vt' => ['min' => '8 000']])
        ->assertSee('Плита ЭП-6ЖШ')
        ->call('resetFilters')
        ->assertSet('attr', []);
});

it('works as a plain form without scripts', function () {
    $page = $this->get('/catalog/plity?attr[kolicestvo-konforok][values][]=2&attr[moshhnost-vt][min]=3500')
        ->assertOk()
        ->assertSee('Плита ЭП-2Ж')
        ->assertDontSee('Плита ЭП-2ЖШ')
        ->assertDontSee('Плита ЭП-4П')
        ->assertSee('name="attr[moshhnost-vt][min]"', false);

    expect($page->getContent())->toMatch('/value="2"\s+checked/')
        ->not->toMatch('/value="4"\s+checked/');
});

it('ignores a characteristic that is not a filter, even from the address', function () {
    $this->voltage->update(['is_filterable' => false]);

    stoveListing(['attr' => ['napriazenie' => ['values' => ['220В']]]])
        ->assertSee('Плита ЭП-6ЖШ')
        ->assertDontSee('Напряжение: 220В');
});

it('keeps the listing within the query budget with characteristic filters', function () {
    Product::factory()->count(18)->create(['category_id' => $this->category->id]);

    DB::enableQueryLog();
    $this->get('/catalog/plity?attr[napriazenie][values][]=380В')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(26);
});

it('cleans the bounds and drops one characteristic at a time', function () {
    $filters = CatalogFilters::fromQuery(['attr' => [
        'moshhnost-vt' => ['min' => '1 500,5', 'max' => 'много'],
        'napriazenie' => ['values' => ['380В', '']],
        'Bad Slug' => ['min' => '1'],
    ]]);

    expect($filters->attributes)->toBe(['moshhnost-vt' => ['min' => '1500.5'], 'napriazenie' => ['values' => ['380В']]])
        ->and($filters->without('attr', 'napriazenie')->attributes)->toBe(['moshhnost-vt' => ['min' => '1500.5']])
        ->and($filters->without('attr')->attributes)->toBe([])
        ->and(Typography::decimal('1300.000'))->toBe('1'.Typography::NBSP.'300')
        ->and(Typography::decimal('0.286'))->toBe('0,286')
        ->and(Typography::decimal('-18.500'))->toBe('−18,5');
});

it('turns the recommended characteristics into filters and names the ones not imported yet', function () {
    $power = Attribute::factory()->create(['slug' => 'moshhnost-kvt', 'is_filterable' => false, 'sort' => 100]);

    $this->artisan('catalog:recommended-filters')
        ->expectsOutputToContain('Фильтрами каталога стали характеристики: 4.')
        ->expectsOutputToContain('xladogent')
        ->assertSuccessful();

    expect($power->fresh()->is_filterable)->toBeTrue()
        ->and($power->fresh()->sort)->toBe(MarkRecommendedFilters::RECOMMENDED['moshhnost-kvt'])
        ->and($this->voltage->fresh()->sort)->toBe(13);
});
