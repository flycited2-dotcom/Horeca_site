<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\CompareItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;

function customerAccount(array $attributes = []): User
{
    return User::factory()->create($attributes + [
        'name' => 'Ирина Соколова',
        'email' => 'irina@kafe.ru',
        'phone' => '+7 978 123-45-67',
        'password' => 'Kofe-Kruassan-2026',
    ]);
}

it('shows the form to guests and sends a signed-in customer home', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Вход в кабинет')
        ->assertSee('name="login"', false)
        ->assertSee('name="remember"', false)
        ->assertSee(route('password.request'), false)
        ->assertSee(route('register'), false);

    $this->actingAs(customerAccount())->get(route('login'))->assertRedirect(route('account'));
});

it('signs in by e-mail or by a phone written any way', function () {
    $user = customerAccount();

    $this->post(route('login.store'), ['login' => 'IRINA@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])
        ->assertRedirect(route('account'))
        ->assertSessionHas('notice.text', 'Вы вошли как Ирина Соколова.');
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();

    $this->post(route('login.store'), ['login' => '8 (978) 123 45 67', 'password' => 'Kofe-Kruassan-2026'])->assertRedirect(route('account'));
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('does not guess the account behind a phone shared by two', function () {
    customerAccount();
    customerAccount(['email' => 'bar@kafe.ru']);

    $this->post(route('login.store'), ['login' => '+79781234567', 'password' => 'Kofe-Kruassan-2026'])->assertSessionHasErrors('password');
    $this->assertGuest();

    $this->post(route('login.store'), ['login' => 'bar@kafe.ru', 'password' => 'Kofe-Kruassan-2026']);
    $this->assertAuthenticated();
});

it('says how many attempts are left and stops after five', function () {
    customerAccount();

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'wrong'])
        ->assertSessionHasErrors(['password' => 'Неверная почта, телефон или пароль. Осталось 4 попытки.']);

    foreach (range(1, 3) as $attempt) {
        $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'wrong'])->assertSessionHasErrors('login');

    // The right password waits for the minute to pass too.
    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])->assertSessionHasErrors('login');
    $this->assertGuest();
    expect(session('errors')->first('login'))->toStartWith('Слишком много попыток входа.');
});

it('keeps a closed account out even with the right password', function () {
    $user = customerAccount();
    $user->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])
        ->assertSessionHasErrors(['login' => 'Вход в этот кабинет закрыт. Позвоните менеджеру — разберёмся.']);
    $this->assertGuest();
});

it('remembers the customer on this device when asked', function () {
    customerAccount();

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026', 'remember' => '1'])
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

it('keeps the guest cart and comparison after signing in', function () {
    $user = customerAccount();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['category_id' => $category->id, 'retail_price' => Money::ofRubles(51_794)]);

    putInCart($product, 2);
    $this->post(route('compare.add', $product->id));

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])->assertRedirect(route('account'));

    $cart = Cart::query()->where('user_id', $user->id)->sole();

    expect($cart->items()->sole()->qty)->toBe(2)
        ->and(CompareItem::query()->where('user_id', $user->id)->pluck('product_id')->all())->toBe([$product->id]);
});

it('goes back to the page the customer was headed to', function () {
    customerAccount();

    $this->withSession(['url.intended' => route('cart')])
        ->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])
        ->assertRedirect(route('cart'));
});

it('shows «Войти» to a guest and the name with «Выйти» to a customer', function () {
    $this->get(route('home'))->assertSee('href="'.route('login').'"', false);

    $this->actingAs(customerAccount())
        ->get(route('home'))
        ->assertSee('aria-label="Кабинет: Ирина Соколова"', false)
        ->assertSee('irina@kafe.ru')
        ->assertSee('href="'.route('account').'"', false)
        ->assertSee('Личный кабинет')
        ->assertSee('action="'.route('logout').'"', false);
});
