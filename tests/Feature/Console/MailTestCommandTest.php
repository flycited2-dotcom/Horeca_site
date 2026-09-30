<?php

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['mail.from.address' => 'shop@gastrosnab.ru']);
});

it('sends the test letter at once and says which mail server took it', function () {
    Mail::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '172.22.0.1']);

    $this->artisan('mail:test', ['email' => 'owner@example.com'])
        ->expectsOutputToContain('Письмо отправлено на owner@example.com от shop@gastrosnab.ru (способ отправки: smtp, сервер 172.22.0.1)')
        ->assertSuccessful();

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('owner@example.com') && $mail->transport === 'smtp');
});

it('does not pretend that a letter written to the log has gone out', function () {
    Mail::fake();
    config(['mail.default' => 'log']);

    $this->artisan('mail:test', ['email' => 'owner@example.com'])
        ->expectsOutputToContain('записано в журнал')
        ->assertSuccessful();
});

it('refuses an address that is not an address', function () {
    Mail::fake();

    $this->artisan('mail:test', ['email' => 'not-an-email'])->expectsOutputToContain('не похож на почтовый')->assertFailed();

    Mail::assertNothingSent();
});

it('shows the reason when the mail server does not answer', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));

    $this->artisan('mail:test', ['email' => 'owner@example.com'])->expectsOutputToContain('Connection refused')->assertFailed();
});

it('renders the letter in the frame of the shop', function () {
    $html = (new TestMail('Гастроснаб', 'shop@gastrosnab.ru', 'smtp'))->render();

    expect($html)->toContain('Проверка почты магазина «Гастроснаб»', 'shop@gastrosnab.ru', 'способ отправки — smtp');
});
