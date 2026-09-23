<?php

use App\Enums\CompanySegment;
use App\Enums\CompanyStatus;
use App\Http\Requests\WholesaleApplicationRequest;
use App\Jobs\SendTelegramMessage;
use App\Mail\WholesaleApplicationMail;
use App\Mail\WholesaleReceivedMail;
use App\Models\Company;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Telegram is configured and the queue is faked: nothing leaves the test.
    Queue::fake();
    Mail::fake();
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function wholesaleForm(array $overrides = []): array
{
    return $overrides + [
        'inn' => '7714 365 426',
        'legal_name' => 'ООО «Вкусный дом»',
        'segment' => CompanySegment::Cafe->value,
        'city' => 'Симферополь',
        'contact_person' => 'Крылова Ирина',
        'email' => 'Zakupki@VkusnyDom.ru',
        'phone' => '8 917 220 44 21',
        'password' => 'Kofe-Kruassan-2026',
        'password_confirmation' => 'Kofe-Kruassan-2026',
        'comment' => 'Шесть точек, чаще всего — холодильные шкафы.',
        'consent' => '1',
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        WholesaleApplicationRequest::HONEYPOT => '',
    ];
}

it('shows a guest how to join and the application with a password', function () {
    $this->get(route('wholesale'))
        ->assertOk()
        ->assertSee('Оптовым клиентам')
        ->assertSee('Как подключиться')
        ->assertSee('1 · Организация')
        ->assertSee('name="inn"', false)
        ->assertSee('name="segment"', false)
        ->assertSee('name="password"', false)
        ->assertSee('Что будет после отправки')
        ->assertSee('href="'.route('login').'"', false);
});

it('links the wholesale page from every page and takes the benefits the manager wrote', function () {
    $this->get(route('home'))->assertSee('href="'.route('wholesale').'"', false);

    Page::factory()->create(['slug' => 'optovikam', 'title' => 'Оптовикам', 'content' => '**Отсрочка 14 дней** после первой отгрузки.']);

    $this->get(route('wholesale'))->assertSee('<strong>Отсрочка 14 дней</strong>', false);
    $this->get('/optovikam')->assertRedirect(route('wholesale'))->assertStatus(301);
});

it('turns a guest into a customer with a company on moderation', function () {
    $this->post(route('wholesale.store'), wholesaleForm())
        ->assertRedirect(route('wholesale'))
        ->assertSessionHas('notice.text', 'Заявка отправлена. Менеджер проверит организацию и напишет на zakupki@vkusnydom.ru.');

    $user = User::query()->sole();
    $company = Company::query()->sole();

    expect($user->name)->toBe('Крылова Ирина')
        ->and($user->email)->toBe('zakupki@vkusnydom.ru')
        ->and($user->phone)->toBe('+7 917 220-44-21')
        ->and($user->company_id)->toBe($company->id)
        ->and($company->inn)->toBe('7714365426')
        ->and($company->legal_name)->toBe('ООО «Вкусный дом»')
        ->and($company->segment)->toBe(CompanySegment::Cafe)
        ->and($company->status)->toBe(CompanyStatus::Pending)
        ->and($company->comment)->toBe('Шесть точек, чаще всего — холодильные шкафы.');
    $this->assertAuthenticatedAs($user);

    $this->get(route('wholesale'))
        ->assertOk()
        ->assertSee('<span class="font-mono">№ '.$company->id.'</span> на проверке', false)
        ->assertSee('Проверка реквизитов')
        ->assertSee('Откроется после проверки')
        ->assertDontSee('name="inn"', false);
});

it('tells the managers without the contacts in Telegram and confirms to the customer', function () {
    $manager = staffUser();

    $this->post(route('wholesale.store'), wholesaleForm());

    $company = Company::query()->sole();

    Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job) use ($company): bool {
        $message = (fn () => $this->message)->call($job);

        return str_contains($message, 'Заявка на опт: ООО «Вкусный дом»')
            && str_contains($message, 'ИНН 7714365426 · Кафе, Симферополь')
            && str_contains($message, '/manage/companies/'.$company->id)
            && ! str_contains($message, 'Крылова')
            && ! str_contains($message, '917');
    });

    Mail::assertQueued(WholesaleApplicationMail::class, fn (WholesaleApplicationMail $mail): bool => $mail->hasTo($manager->email)
        && str_contains($mail->adminUrl, '/manage/companies/'.$company->id));
    Mail::assertQueued(WholesaleReceivedMail::class, fn (WholesaleReceivedMail $mail): bool => $mail->hasTo('zakupki@vkusnydom.ru'));
});

it('writes the letters to the managers and to the customer', function () {
    $company = Company::factory()->make([
        'legal_name' => 'ООО «Вкусный дом»',
        'inn' => '7714365426',
        'segment' => CompanySegment::Cafe,
        'contact_person' => 'Крылова Ирина',
        'comment' => 'Шесть точек',
    ]);

    (new WholesaleApplicationMail($company, 'https://test.gastrosnab.ru/manage/companies/7'))
        ->assertHasSubject('Заявка на опт: ООО «Вкусный дом»')
        ->assertSeeInHtml('7714365426')
        ->assertSeeInHtml('Шесть точек')
        ->assertSeeInHtml('https://test.gastrosnab.ru/manage/companies/7', false);

    (new WholesaleReceivedMail($company))
        ->assertHasSubject('Заявка на опт принята')
        ->assertSeeInHtml('Здравствуйте, Крылова Ирина!');
});

it('attaches the company to the signed-in customer without asking for a password', function () {
    $user = User::factory()->create(['name' => 'Ирина', 'email' => 'irina@kafe.ru', 'phone' => '+7 978 123-45-67']);
    $this->actingAs($user);

    $this->get(route('wholesale'))
        ->assertOk()
        ->assertSee('Заявка привяжется к вашему кабинету irina@kafe.ru.')
        ->assertSee('value="irina@kafe.ru"', false)
        ->assertDontSee('name="password"', false);

    $form = wholesaleForm(['email' => 'irina@kafe.ru', 'phone' => '+7 978 123-45-67']);
    unset($form['password'], $form['password_confirmation']);

    $this->post(route('wholesale.store'), $form)->assertRedirect(route('wholesale'))->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(1)
        ->and($user->refresh()->company?->legal_name)->toBe('ООО «Вкусный дом»');
});

it('explains what is wrong with the application', function () {
    User::factory()->create(['email' => 'zakupki@vkusnydom.ru']);

    $this->post(route('wholesale.store'), wholesaleForm(['inn' => '7714365420', 'segment' => '', 'consent' => null]))
        ->assertSessionHasErrors([
            'inn',
            'segment',
            'email' => 'Эта почта уже зарегистрирована — войдите, и заявка привяжется к кабинету.',
            'consent' => 'Отметьте согласие на обработку персональных данных.',
        ]);

    $this->post(route('wholesale.store'), wholesaleForm([WholesaleApplicationRequest::HONEYPOT => 'spam', 'email' => 'new@vkusnydom.ru']))
        ->assertSessionHasErrors('form');

    expect(Company::query()->count())->toBe(0);
});

it('takes one application from an account', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->company()->associate($company)->save();

    $this->actingAs($user)
        ->post(route('wholesale.store'), wholesaleForm(['email' => $user->email]))
        ->assertSessionHasErrors(['form' => 'От вашего кабинета заявка уже подана — её статус на этой странице.']);

    expect(Company::query()->count())->toBe(1);
});

it('tells the customer what the decision means', function (CompanyStatus $status, string $heading) {
    $company = Company::factory()->create(['status' => $status, 'legal_name' => 'ООО «Вкусный дом»']);
    $user = User::factory()->create();
    $user->company()->associate($company)->save();

    $this->actingAs($user)
        ->get(route('wholesale'))
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('href="'.route('catalog').'"', false);
})->with([
    'approved' => [CompanyStatus::Approved, 'Оптовые цены открыты'],
    'rejected' => [CompanyStatus::Rejected, 'Заявка не одобрена'],
    'blocked' => [CompanyStatus::Blocked, 'Оптовые цены приостановлены'],
]);
