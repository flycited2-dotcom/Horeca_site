<?php

/*
 * Сценарии 3–5 из ТЗ §2 — DoD спринта 5: регистрация оптовика и одобрение менеджером,
 * заказ списком, повтор заказа. И отдельно — компания без одобрения оптовых цен не видит.
 * Скидка группы открыта настройкой pricing.max_discount_without_purchase: пока она 0,
 * оптовая цена равна розничной (§7).
 */

use App\Enums\Availability;
use App\Enums\CompanySegment;
use App\Enums\CompanyStatus;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Http\Requests\WholesaleApplicationRequest;
use App\Mail\WholesaleApprovedMail;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\Percent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    setting('pricing.max_discount_without_purchase', 20);
    $this->tier = PriceTier::factory()->create(['name' => 'Опт-1', 'discount_percent' => Percent::fromDecimal('10'), 'is_default' => true]);
    $this->section = Category::factory()->create(['name' => 'Шкафы холодильные', 'is_active' => true]);
    $this->cabinet = Product::factory()->create([
        'category_id' => $this->section->id,
        'name' => 'Шкаф холодильный ШХс-0,7',
        'sku' => '11000018820',
        'retail_price' => Money::ofRubles(100_000),
    ]);
});

function rubles(int $amount): string
{
    return number_format($amount, 0, '', "\u{00A0}")."\u{00A0}₽";
}

it('registers a wholesale customer, lets the manager approve and opens wholesale prices (scenario 3)', function () {
    $this->get(route('wholesale'))->assertOk()->assertSee('name="inn"', false);

    $this->post(route('wholesale.store'), [
        'inn' => '7714365426',
        'legal_name' => 'ООО «Вкусный дом»',
        'segment' => CompanySegment::Cafe->value,
        'city' => 'Симферополь',
        'contact_person' => 'Крылова Ирина',
        'email' => 'zakupki@vkusnydom.ru',
        'phone' => '+7 917 220-44-21',
        'password' => 'Kofe-Kruassan-2026',
        'password_confirmation' => 'Kofe-Kruassan-2026',
        'consent' => '1',
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        WholesaleApplicationRequest::HONEYPOT => '',
    ])->assertRedirect(route('wholesale'));

    $customer = User::query()->where('email', 'zakupki@vkusnydom.ru')->sole();
    $company = Company::query()->sole();

    $this->get(route('wholesale'))->assertOk()->assertSee('Проверка реквизитов');
    $this->get(route('account'))->assertOk()->assertSee('Заявка на опт на проверке');
    $this->get(route('product', $this->cabinet))
        ->assertOk()
        ->assertSee(rubles(100_000))
        ->assertSee('Оптовые цены откроются после проверки заявки.')
        ->assertDontSee('Ваша цена');

    $this->actingAs(staffUser());
    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('approve', data: ['price_tier_id' => $this->tier->id])
        ->assertHasNoActionErrors();

    Mail::assertQueued(WholesaleApprovedMail::class, fn (WholesaleApprovedMail $mail): bool => $mail->hasTo('zakupki@vkusnydom.ru'));

    $this->actingAs($customer->refresh());

    $this->get(route('product', $this->cabinet))
        ->assertOk()
        ->assertSee(rubles(90_000))
        ->assertSee('Ваша цена')
        ->assertDontSee('Оптовые цены откроются после проверки заявки.');
    $this->get(route('account'))->assertOk()->assertSee('Открыты')->assertSee(route('account.bulk-order'));
});

it('orders from a list of articles (scenario 4)', function () {
    $customer = wholesaleCustomer($this->tier);
    $pan = Product::factory()->create(['category_id' => $this->section->id, 'sku' => 'SM-25', 'name' => 'Сковорода SM-25 Prima', 'retail_price' => Money::ofRubles(10_000)]);
    Product::factory()->create(['category_id' => $this->section->id, 'sku' => 'SM25', 'name' => 'Сковорода SM25 Abat', 'retail_price' => Money::ofRubles(12_000)]);
    Product::factory()->create(['category_id' => $this->section->id, 'sku' => 'КИП-27Н', 'name' => 'Плита КИП-27Н', 'retail_price' => null]);

    $this->actingAs($customer);
    $this->get(route('account'))->assertOk()->assertSee(route('account.bulk-order'));
    $this->get(route('account.bulk-order'))->assertOk();

    $this->post(route('account.bulk-order.check'), ['list' => "11000018820;2\nsm 25;1\nКИП-27Н;1\n404404;5"])
        ->assertRedirect(route('account.bulk-order'));

    $this->get(route('account.bulk-order'))
        ->assertOk()
        ->assertSeeInOrder(['11000018820', 'Найден', 'Шкаф холодильный ШХс-0,7', rubles(90_000)])
        ->assertSeeInOrder(['sm 25', 'Несколько совпадений', 'Сковорода SM-25 Prima', 'Сковорода SM25 Abat'])
        ->assertSeeInOrder(['КИП-27Н', 'Цена по запросу'])
        ->assertSeeInOrder(['404404', 'Не найден']);

    $this->post(route('account.bulk-order.cart'), ['choice' => [2 => $pan->id]])->assertRedirect(route('cart'));

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Шкаф холодильный ШХс-0,7')
        ->assertSee('Сковорода SM-25 Prima')
        ->assertDontSee('Плита КИП-27Н')
        ->assertSee(rubles(2 * 90_000 + 9_000));
});

it('repeats a past order at today\'s prices and lists what can not be bought (scenario 5)', function () {
    $customer = wholesaleCustomer($this->tier);
    $gone = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Плита снятая', 'availability' => Availability::Discontinued]);
    $order = Order::factory()->create(['user_id' => $customer->id, 'company_id' => $customer->company_id, 'number' => 'HR-010926-0001']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->cabinet->id, 'name' => $this->cabinet->name, 'qty' => 2, 'price' => Money::ofRubles(95_000), 'sum' => Money::ofRubles(190_000)]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $gone->id, 'name' => 'Плита снятая', 'qty' => 1]);

    $this->actingAs($customer);
    $this->get(route('account'))->assertOk()->assertSee('HR-010926-0001');
    $this->get(route('account.orders'))->assertOk()->assertSee(route('account.order', $order));
    $this->get(route('account.order', $order))->assertOk()->assertSee('Повторить заказ');

    $this->post(route('account.order.repeat', $order))->assertRedirect(route('cart'));

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Шкаф холодильный ШХс-0,7')
        ->assertSee(rubles(2 * 90_000))
        ->assertSee('Не добавлены из заявки HR-010926-0001')
        ->assertSeeInOrder(['Плита снятая', 'Снят с производства']);
});

it('keeps wholesale prices and the list order closed to a company that is not approved', function (CompanyStatus $status) {
    $customer = wholesaleCustomer($this->tier, $status);

    $this->actingAs($customer);

    $this->get(route('product', $this->cabinet))
        ->assertOk()
        ->assertSee(rubles(100_000))
        ->assertDontSee(rubles(90_000))
        ->assertDontSee('Ваша цена');

    $this->post(route('cart.add', $this->cabinet->id), ['quantity' => 1]);
    $this->get(route('cart'))->assertOk()->assertSee(rubles(100_000))->assertDontSee(rubles(90_000));

    $this->get(route('account.bulk-order'))->assertRedirect(route('wholesale'));
    $this->get(route('account'))->assertOk()->assertDontSee(route('account.bulk-order'));
})->with([
    'на проверке' => [CompanyStatus::Pending],
    'отклонена' => [CompanyStatus::Rejected],
    'заблокирована' => [CompanyStatus::Blocked],
]);
