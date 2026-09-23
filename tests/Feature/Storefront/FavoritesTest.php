<?php

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->section = Category::factory()->create(['name' => 'Холодильные шкафы', 'slug' => 'holodilnye-shkafy', 'is_active' => true]);
});

function favoriteProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + ['category_id' => test()->section->id]);
}

/**
 * Whether the «В избранное» form of a product is the hidden one: the product is a favorite.
 */
function favoriteAddHidden(TestResponse $response, Product $product): bool
{
    $action = preg_quote('action="'.route('favorites.add', $product->id).'"', '/');
    preg_match('/'.$action.'\s+data-favorite-form(\s+hidden)?\s*>/', $response->getContent(), $form);

    return isset($form[1]) && $form[1] !== '';
}

it('keeps a guest\'s favorites for the session and gives the script the new state', function () {
    $product = favoriteProduct(['name' => 'Шкаф холодильный ШХс-0,7']);

    $this->postJson(route('favorites.add', $product->id))
        ->assertOk()
        ->assertJson([
            'product' => $product->id,
            'favorite' => true,
            'count' => 1,
            'notice' => ['text' => '«Шкаф холодильный ШХс-0,7» — в избранном.', 'href' => route('favorites')],
        ]);

    $row = Favorite::query()->sole();

    expect($row->user_id)->toBeNull()
        ->and($row->session_id)->toHaveLength(40)
        ->and($row->session_id)->not->toBe(session()->getId());

    $page = $this->get(route('favorites'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Шкаф холодильный ШХс-0,7')
        ->assertSee('Избранное гостя хранится, пока открыт сайт.')
        ->assertSee('<span data-favorite-count class', false);

    expect(favoriteAddHidden($page, $product))->toBeTrue()
        ->and($page->getContent())->not->toMatch('/data-favorite-link\s+hidden/');

    $this->deleteJson(route('favorites.remove', $product->id))
        ->assertOk()
        ->assertJson(['favorite' => false, 'count' => 0, 'notice' => ['text' => '«Шкаф холодильный ШХс-0,7» убран из избранного.']]);

    $empty = $this->get(route('favorites'))
        ->assertOk()
        ->assertSee('В избранном пока пусто.');

    expect($empty->getContent())->toMatch('/data-favorite-link\s+hidden/');
});

it('works without scripts: the form returns to the page with a notice', function () {
    $product = favoriteProduct(['name' => 'Шкаф холодильный ШХс-1,4']);

    $this->from(route('category', $this->section))
        ->post(route('favorites.add', $product->id))
        ->assertRedirect(route('category', $this->section))
        ->assertSessionHas('notice.text', '«Шкаф холодильный ШХс-1,4» — в избранном.')
        ->assertSessionHas('notice.link', 'Открыть избранное');

    // Adding twice keeps one row.
    $this->post(route('favorites.add', $product->id));

    expect(Favorite::query()->count())->toBe(1);
});

it('does not take a product hidden by the manager', function () {
    $this->post(route('favorites.add', favoriteProduct(['is_visible' => false])->id))->assertNotFound();

    expect(Favorite::query()->count())->toBe(0);
});

it('shows the heart on listing cards and a button next to «К сравнению» on the product page', function () {
    $product = favoriteProduct();

    $this->get(route('category', $this->section))
        ->assertOk()
        ->assertSee('action="'.route('favorites.add', $product->id).'"', false)
        ->assertSee('action="'.route('favorites.remove', $product->id).'"', false);

    $this->get(route('product', $product))
        ->assertOk()
        ->assertSeeInOrder(['В избранное', 'К сравнению']);
});

it('keeps a customer\'s favorites in the account', function () {
    $user = User::factory()->create(['name' => 'Ирина Соколова']);
    $product = favoriteProduct(['name' => 'Льдогенератор Brema CB 184']);

    $this->actingAs($user)->post(route('favorites.add', $product->id));

    expect(Favorite::query()->sole()->user_id)->toBe($user->id);

    $page = $this->actingAs($user)->get(route('favorites'))
        ->assertOk()
        ->assertSee('Льдогенератор Brema CB 184')
        ->assertSee('aria-current="page"', false)
        ->assertSee('href="'.route('account.orders').'"', false)
        ->assertDontSee('Избранное гостя хранится');

    expect(favoriteAddHidden($page, $product))->toBeTrue();

    $this->actingAs($user)->get(route('home'))->assertSee('href="'.route('favorites').'"', false);
});

it('moves the guest\'s favorites to the customer on sign-in without doubles', function () {
    $user = User::factory()->create(['email' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026']);
    [$both, $guestOnly, $ownOnly] = [favoriteProduct(), favoriteProduct(), favoriteProduct()];
    Favorite::query()->create(['user_id' => $user->id, 'product_id' => $both->id]);
    Favorite::query()->create(['user_id' => $user->id, 'product_id' => $ownOnly->id]);

    $this->post(route('favorites.add', $both->id));
    $this->post(route('favorites.add', $guestOnly->id));

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])->assertRedirect();

    expect(Favorite::query()->where('user_id', $user->id)->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$both->id, $guestOnly->id, $ownOnly->id])->sort()->values()->all())
        ->and(Favorite::query()->whereNull('user_id')->count())->toBe(0);
});

it('drops hidden and removed products so the header count matches the page', function () {
    $user = User::factory()->create();
    $kept = favoriteProduct(['name' => 'Шкаф остался']);
    $discontinued = favoriteProduct(['name' => 'Шкаф снятый', 'availability' => Availability::Discontinued]);
    $hidden = favoriteProduct();
    $removed = favoriteProduct();

    foreach ([$kept, $discontinued, $hidden, $removed] as $product) {
        Favorite::query()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    $hidden->forceFill(['is_visible' => false])->save();
    $removed->delete();

    $this->actingAs($user)->get(route('favorites'))
        ->assertOk()
        ->assertSee('Шкаф остался')
        ->assertSee('Шкаф снятый')
        ->assertSee('2 модели');

    expect(Favorite::query()->where('user_id', $user->id)->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $discontinued->id])->sort()->values()->all());
});

it('shows favorites with the same number of queries however many there are', function () {
    $user = User::factory()->create();

    $count = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('favorites'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    Favorite::query()->create(['user_id' => $user->id, 'product_id' => favoriteProduct()->id]);
    $count();
    $few = $count();

    foreach (range(1, 6) as $n) {
        Favorite::query()->create(['user_id' => $user->id, 'product_id' => favoriteProduct()->id]);
    }

    $count();
    expect($count())->toBe($few);
});

it('prunes the favorites of guests long gone and keeps customers\' ones', function () {
    $product = favoriteProduct();
    $old = Favorite::query()->create(['session_id' => str_repeat('a', 40), 'product_id' => $product->id]);
    $fresh = Favorite::query()->create(['session_id' => str_repeat('b', 40), 'product_id' => $product->id]);
    $customer = Favorite::query()->create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id]);
    Favorite::query()->whereKey([$old->id, $customer->id])->update(['updated_at' => now()->subDays(3)]);

    $this->artisan('model:prune', ['--model' => [Favorite::class]])->assertSuccessful();

    expect(Favorite::query()->pluck('id')->sort()->values()->all())->toBe([$fresh->id, $customer->id]);
});
