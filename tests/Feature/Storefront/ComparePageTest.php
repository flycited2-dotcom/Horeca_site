<?php

use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompareItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->ovens = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty']);
    $this->abat = Brand::factory()->create(['name' => 'Abat']);
});

function oven(array $attributes = []): Product
{
    return Product::factory()->create($attributes + [
        'category_id' => test()->ovens->id,
        'brand_id' => test()->abat->id,
        'model' => null,
        'warranty_months' => null,
        'length_mm' => null,
        'width_mm' => null,
        'height_mm' => null,
        'weight_kg' => null,
    ]);
}

function compareTable(TestResponse $response): string
{
    preg_match('/<table.*<\/table>/s', $response->getContent(), $table);

    return $table[0] ?? '';
}

/**
 * Whether the «Сравнить» form of a product is the hidden one: the product is compared.
 * The form that takes it out has the same address and comes second.
 */
function addFormHidden(TestResponse $response, Product $product): bool
{
    $action = preg_quote('action="'.route('compare.add', $product->id).'"', '/');
    preg_match('/'.$action.'\s+data-compare-form(\s+hidden)?\s*>/', $response->getContent(), $form);

    return isset($form[1]) && $form[1] !== '';
}

it('puts a model into the comparison and gives the script the new state', function () {
    $product = oven(['name' => 'Пароконвектомат ПКА 6']);

    $this->postJson(route('compare.add', $product->id))
        ->assertOk()
        ->assertJson([
            'product' => $product->id,
            'compared' => true,
            'count' => 1,
            'notice' => ['text' => '«Пароконвектомат ПКА 6» — в сравнении, 1 из 4.', 'href' => route('compare'), 'link' => 'Открыть сравнение'],
        ])
        ->assertSessionHas('compare', [$product->id]);
});

it('keeps at most four models', function () {
    $products = collect(range(1, 5))->map(fn () => oven());
    $four = $products->take(4)->map->id->all();

    $this->withSession(['compare' => $four])
        ->postJson(route('compare.add', $products[4]->id))
        ->assertJson(['compared' => false, 'count' => 4, 'notice' => ['text' => 'В сравнении уже 4 модели. Уберите одну, чтобы добавить эту.']])
        ->assertSessionHas('compare', $four);
});

it('goes back with a notice when scripts are off', function () {
    $product = oven(['name' => 'Пароконвектомат ПКА 10']);

    $this->from(route('product', $product))
        ->post(route('compare.add', $product->id))
        ->assertRedirect(route('product', $product))
        ->assertSessionHas('notice.text', '«Пароконвектомат ПКА 10» — в сравнении, 1 из 4.');

    $this->withSession(['compare' => [$product->id], 'notice' => ['text' => 'Готово.', 'href' => route('compare'), 'link' => 'Открыть сравнение']])
        ->get(route('product', $product))
        ->assertSee('Готово.')
        ->assertSee('Убрать из сравнения');
});

it('takes a model out of the comparison', function () {
    $first = oven(['name' => 'Первая модель']);
    $second = oven();

    $this->withSession(['compare' => [$first->id, $second->id]])
        ->deleteJson(route('compare.remove', $first->id))
        ->assertJson(['compared' => false, 'count' => 1, 'notice' => ['text' => '«Первая модель» убран из сравнения.']])
        ->assertSessionHas('compare', [$second->id]);
});

it('does not compare a model the manager has hidden', function () {
    $this->postJson(route('compare.add', oven(['is_visible' => false])->id))->assertNotFound();
});

it('shows only the differences, installation first, and folds the rest into a note', function () {
    $power = Attribute::factory()->create(['name' => 'Мощность', 'unit' => 'кВт', 'type' => AttributeType::Number, 'is_main' => true]);
    $voltage = Attribute::factory()->create(['name' => 'Напряжение', 'unit' => 'В', 'type' => AttributeType::Number]);
    $levels = Attribute::factory()->create(['name' => 'Уровней GN', 'unit' => null, 'type' => AttributeType::Text]);

    $small = oven(['name' => 'ПКА 6-1/1ВП2', 'length_mm' => 840, 'width_mm' => 800, 'height_mm' => 840, 'weight_kg' => '98.5']);
    $big = oven(['name' => 'ПКА 10-1/1ВП2', 'length_mm' => 840, 'width_mm' => 800, 'height_mm' => 1120, 'weight_kg' => '128']);
    $small->attributeValues()->attach([$power->id => ['value_number' => '11.2'], $voltage->id => ['value_number' => '380']]);
    $small->attributeValues()->attach($levels->id, ['value_string' => '6 × GN 1/1']);
    $big->attributeValues()->attach([$power->id => ['value_number' => '18.9'], $voltage->id => ['value_number' => '380']]);
    $big->attributeValues()->attach($levels->id, ['value_string' => '10 × GN 1/1']);

    $response = $this->withSession(['compare' => [$small->id, $big->id]])->get('/compare')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Сравнение: Пароконвектоматы')
        ->assertSee('2 модели · различаются по 4 параметрам из 6')
        ->assertSee("Совпадают у всех: бренд Abat, напряжение 380\u{00A0}В.", false)
        ->assertSee('Показать все 6 строк');

    expect(compareTable($response))
        ->toContain('<th scope="col"', '<th scope="row"', '<th scope="colgroup"')
        ->not->toContain('Напряжение')
        ->and(strpos(compareTable($response), 'Критично для монтажа'))->toBeLessThan(strpos(compareTable($response), 'Характеристики'));

    $response->assertSeeInOrder([
        'Критично для монтажа',
        "840×800×840\u{00A0}мм", "840×800×1120\u{00A0}мм",
        "98,5\u{00A0}кг", "128\u{00A0}кг",
        'Мощность', "11,2\u{00A0}кВт", "18,9\u{00A0}кВт",
        'Характеристики',
        'Уровней GN', '6 × GN 1/1', '10 × GN 1/1',
    ]);

    $all = $this->withSession(['compare' => [$small->id, $big->id]])->get('/compare?all=1');

    expect(compareTable($all))->toContain('Напряжение', 'Бренд');
});

it('keeps a discontinued model with its status and drops a hidden one', function () {
    $gone = oven(['name' => 'Снятая модель', 'availability' => Availability::Discontinued]);
    $hidden = oven(['name' => 'Скрытая модель', 'is_visible' => false]);

    $this->withSession(['compare' => [$gone->id, $hidden->id]])
        ->get('/compare')
        ->assertSee('Снятая модель')
        ->assertSee('Подобрать аналог')
        ->assertDontSee('Скрытая модель')
        ->assertSessionHas('compare', [$gone->id]);
});

it('explains an empty comparison', function () {
    $this->get('/compare')
        ->assertOk()
        ->assertSee('В сравнении пока нет моделей')
        ->assertSee(route('catalog'));
});

it('clears the comparison', function () {
    $this->withSession(['compare' => [oven()->id]])
        ->delete(route('compare.clear'))
        ->assertRedirect(route('compare'))
        ->assertSessionMissing('compare')
        ->assertSessionHas('notice.text', 'Сравнение очищено.');
});

it('takes the guest models along on sign-in and keeps them after signing out', function () {
    $user = User::factory()->create();
    $mine = oven(['name' => 'Своя модель']);
    $guest = oven(['name' => 'Гостевая модель']);
    CompareItem::query()->create(['user_id' => $user->id, 'product_id' => $mine->id]);

    session(['compare' => [$guest->id, $mine->id]]);
    Auth::login($user);

    expect(CompareItem::query()->where('user_id', $user->id)->orderBy('id')->pluck('product_id')->all())->toBe([$mine->id, $guest->id])
        ->and(session('compare'))->toBeNull();

    Auth::logout();

    $this->actingAs($user)->get('/compare')->assertSee('Своя модель')->assertSee('Гостевая модель');
});

it('marks the compared models on the cards and counts them in the header', function () {
    $compared = oven(['name' => 'Модель в сравнении']);
    $other = oven(['name' => 'Модель не в сравнении']);

    $response = $this->withSession(['compare' => [$compared->id]])->get(route('category', $this->ovens))->assertOk();

    expect(addFormHidden($response, $compared))->toBeTrue()
        ->and(addFormHidden($response, $other))->toBeFalse()
        ->and(preg_match('/data-compare-link\s+class/', $response->getContent()))->toBe(1)
        ->and(preg_match('/data-compare-count[^>]*>1</', $response->getContent()))->toBe(1);

    $this->flushSession();

    expect(preg_match('/data-compare-link\s+hidden/', $this->get(route('category', $this->ovens))->getContent()))->toBe(1);
});

it('offers «К сравнению» on the product page', function () {
    $this->get(route('product', oven()))->assertSee('К сравнению');
});

it('renders the comparison of four models with a fixed number of queries', function () {
    $attribute = Attribute::factory()->create(['name' => 'Мощность']);
    $ids = collect(range(1, 4))->map(function () use ($attribute) {
        $product = oven();
        $product->attributeValues()->attach($attribute->id, ['value_number' => '5']);

        return $product->id;
    })->all();

    DB::enableQueryLog();
    $this->withSession(['compare' => $ids])->get('/compare')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(15);
});
