<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    Mail::fake();

    $this->user = User::factory()->create(['name' => 'Ирина', 'email' => 'irina@kafe.ru', 'password' => 'Old-Password-2025']);
});

it('sends the link to a known address and answers the same for an unknown one', function () {
    $answer = 'Если на эту почту есть кабинет, письмо со ссылкой уже в пути. Ссылка действует 60 минут.';

    $this->post(route('password.email'), ['email' => 'IRINA@kafe.ru'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', $answer);

    Mail::assertQueued(PasswordResetMail::class, fn (PasswordResetMail $mail): bool => $mail->hasTo('irina@kafe.ru')
        && str_starts_with($mail->url, url('/reset-password/'))
        && str_contains($mail->url, 'email=irina%40kafe.ru')
        && $mail->minutes === 60);

    $this->post(route('password.email'), ['email' => 'nobody@kafe.ru'])->assertSessionHas('status', $answer);

    Mail::assertQueuedCount(1);
});

it('writes the letter in Russian with a button and the expiry', function () {
    $mail = new PasswordResetMail('Ирина', 'https://test.gastrosnab.ru/reset-password/abc?email=irina%40kafe.ru', 60);

    $mail->assertHasSubject('Новый пароль для кабинета')
        ->assertSeeInHtml('Задать новый пароль')
        ->assertSeeInHtml('Здравствуйте, Ирина!')
        ->assertSeeInHtml('Ссылка действует 60 минут и работает один раз.')
        ->assertSeeInHtml('https://test.gastrosnab.ru/reset-password/abc?email=irina%40kafe.ru', false);
});

it('sets a new password by the link and sends the customer to sign in', function () {
    $token = Password::broker()->createToken($this->user);
    $remember = $this->user->remember_token;

    $this->get(route('password.reset', ['token' => $token, 'email' => 'irina@kafe.ru']))
        ->assertOk()
        ->assertSee('value="irina@kafe.ru"', false);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'irina@kafe.ru',
        'password' => 'New-Password-2026',
        'password_confirmation' => 'New-Password-2026',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('notice.text', 'Пароль изменён — войдите с новым паролем.');

    $this->user->refresh();

    expect(Hash::check('New-Password-2026', $this->user->password))->toBeTrue()
        ->and($this->user->remember_token)->not->toBe($remember);
    $this->assertGuest();

    // The link works once.
    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'irina@kafe.ru',
        'password' => 'Third-Password-2026',
        'password_confirmation' => 'Third-Password-2026',
    ])->assertSessionHasErrors(['email' => 'Ссылка устарела или уже использована. Запросите новую.']);
});
