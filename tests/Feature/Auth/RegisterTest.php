<?php

use App\Enums\UserRole;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // The leak check asks the pwned passwords service: an empty answer means «never leaked».
    // Stubs pile up in Http::fake(), so the one stub reads the answer a test has set.
    $this->leaks = '';
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->leaks)]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationForm(array $overrides = []): array
{
    return $overrides + [
        'name' => '  Ирина Соколова ',
        'email' => 'Irina@Kafe.ru',
        'phone' => '8 (978) 123-45-67',
        'password' => 'Kofe-Kruassan-2026',
        'password_confirmation' => 'Kofe-Kruassan-2026',
        'consent' => '1',
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        RegisterRequest::HONEYPOT => '',
    ];
}

it('shows the form with the phone mask and the consent', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Регистрация')
        ->assertSee('data-phone-mask', false)
        ->assertSee('name="consent"', false)
        ->assertSee('soglasie-na-obrabotku-personalnyh-dannyh', false)
        ->assertSee('politika-konfidencialnosti', false);
});

it('creates a customer and signs them in', function () {
    $this->post(route('register.store'), registrationForm())
        ->assertRedirect(route('home'))
        ->assertSessionHas('notice.text', 'Кабинет создан. Вы вошли как Ирина Соколова.');

    $user = User::query()->sole();

    expect($user->name)->toBe('Ирина Соколова')
        ->and($user->email)->toBe('irina@kafe.ru')
        ->and($user->phone)->toBe('+7 978 123-45-67')
        ->and($user->role)->toBe(UserRole::Customer)
        ->and($user->company_id)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('explains what is wrong with the form', function () {
    User::factory()->create(['email' => 'irina@kafe.ru', 'phone' => '+7 978 123-45-67']);

    $this->post(route('register.store'), registrationForm(['password' => 'short', 'password_confirmation' => 'other', 'consent' => null]))
        ->assertSessionHasErrors([
            'email' => 'Эта почта уже зарегистрирована — войдите или восстановите пароль.',
            'phone' => 'Этот телефон уже привязан к кабинету — войдите или восстановите пароль.',
            'password',
            'consent' => 'Отметьте согласие на обработку персональных данных.',
        ]);

    expect(User::query()->count())->toBe(1);
    $this->assertGuest();
});

it('refuses a password from known leaks', function () {
    $this->leaks = substr(strtoupper(sha1('Kofe-Kruassan-2026')), 5).':128';

    $this->post(route('register.store'), registrationForm())
        ->assertSessionHasErrors(['password' => 'Такой «пароль» встречался в утечках данных. Придумайте другой.']);

    expect(User::query()->count())->toBe(0);
});

it('refuses a robot', function () {
    $this->post(route('register.store'), registrationForm([RegisterRequest::HONEYPOT => 'spam']))->assertSessionHasErrors('form');
    $this->post(route('register.store'), registrationForm(['started' => Crypt::encryptString((string) time())]))->assertSessionHasErrors('form');

    expect(User::query()->count())->toBe(0);
});

it('waits no more than five seconds for the leak check', function () {
    $verifier = app(UncompromisedVerifier::class);

    expect((fn () => $this->timeout)->call($verifier))->toBe(5);
});
