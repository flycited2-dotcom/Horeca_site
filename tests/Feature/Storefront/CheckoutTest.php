<?php

use App\Enums\Availability;
use App\Enums\CompanyStatus;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Events\OrderCreated;
use App\Models\Cart;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    Event::fake([OrderCreated::class]);
});

function orderedProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + [
        'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01',
        'sku' => '11000019106',
        'supplier_code' => 'ЦБ-00012345',
        'unit' => 'шт',
        'availability' => Availability::InStock,
        'retail_price' => Money::ofRubles(383_995),
    ]);
}

/**
 * The checkout form as a person sends it: opened ten seconds ago, trap field empty.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkoutForm(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Алексей',
        'phone' => '8 (978) 123-45-67',
        'email' => 'buyer@example.ru',
        'delivery_method' => DeliveryMethod::Pickup->value,
        'payment_method' => PaymentMethod::Invoice->value,
        'comment' => 'Позвоните после 14:00',
        'consent' => '1',
        'idempotency_key' => (string) Str::uuid(),
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        'website' => '',
    ];
}

it('sends a guest from an empty cart back to the cart', function () {
    $this->get(route('checkout'))->assertRedirect(route('cart'))->assertSessionHas('notice.text', 'Корзина пуста — добавьте товары, чтобы оформить заявку.');
});

it('shows one page with contacts, delivery, payment and the summary', function () {
    putInCart(orderedProduct(), 2);

    $this->get(route('checkout'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSeeInOrder(['Контакты', 'Получение', 'Оплата', 'Комментарий', 'Ваша заявка', 'Пароконвектомат ПКА 10-1/1ВП2-01', '× 2', "767\u{00A0}990\u{00A0}₽", 'Отправить заявку'], false)
        ->assertSee('name="idempotency_key"', false)
        ->assertSee('name="website"', false)
        ->assertSee('СДЭК')
        ->assertDontSee('value="online"', false);
});

it('places the order with a snapshot of every line and empties the cart', function () {
    $product = orderedProduct();
    putInCart($product, 2);

    $response = $this->post(route('checkout.store'), checkoutForm(['utm_source' => 'ignored']));

    $order = Order::query()->with('items', 'statusLogs')->sole();
    $response->assertRedirect(route('checkout.success', $order->number));

    expect($order->number)->toMatch('/^HR-\d{6}-0001$/')
        ->and($order->type)->toBe(OrderType::Retail)
        ->and($order->customer_name)->toBe('Алексей')
        ->and($order->phone)->toBe('+7 978 123-45-67')
        ->and($order->delivery_method)->toBe(DeliveryMethod::Pickup)
        ->and($order->total->equals(Money::ofRubles(767_990)))->toBeTrue()
        ->and($order->discount->isZero())->toBeTrue()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items[0]->only(['sku', 'supplier_code', 'name', 'unit', 'availability', 'qty']))->toBe([
            'sku' => '11000019106',
            'supplier_code' => 'ЦБ-00012345',
            'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01',
            'unit' => 'шт',
            'availability' => Availability::InStock,
            'qty' => 2,
        ])
        ->and($order->statusLogs->sole()->to_status)->toBe(OrderStatus::New)
        ->and(Cart::query()->sole()->items()->count())->toBe(0);

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order->is($order));
});

it('names what to fix at each field', function () {
    putInCart(orderedProduct());

    $this->from(route('checkout'))
        ->post(route('checkout.store'), checkoutForm([
            'phone' => '123',
            'consent' => null,
            'is_legal_entity' => '1',
            'inn' => '7707083894',
            'company_name' => '',
            'delivery_method' => DeliveryMethod::TransportCompany->value,
            'delivery_city' => '',
        ]))
        ->assertRedirect(route('checkout'))
        ->assertSessionHasErrors([
            'phone' => 'Нужен российский номер: +7 и десять цифр, например +7 978 123-45-67.',
            'consent' => 'Отметьте согласие на обработку персональных данных.',
            'inn' => 'В ИНН опечатка: контрольная цифра не сходится. Сверьте с реквизитами.',
            'company_name',
            'delivery_city',
        ]);

    expect(Order::query()->count())->toBe(0);
});

it('shows each error at its field and lists them in the summary', function () {
    putInCart(orderedProduct());

    // The session is JSON-serialized: between real requests errors travel as arrays.
    $this->withSession(['errors' => ['default' => ['format' => ':message', 'messages' => [
        'phone' => ['Нужен российский номер: +7 и десять цифр, например +7 978 123-45-67.'],
        'consent' => ['Отметьте согласие на обработку персональных данных.'],
    ]]]])
        ->get(route('checkout'))
        ->assertOk()
        ->assertSee('Осталось исправить 2 поля')
        ->assertSee('href="#phone"', false)
        ->assertSee('id="phone-error"', false)
        ->assertSee('id="consent-error"', false);
});

it('takes a real INN of an organization and of a sole trader', function (string $inn) {
    putInCart(orderedProduct());

    $this->post(route('checkout.store'), checkoutForm(['is_legal_entity' => '1', 'inn' => $inn, 'company_name' => 'ООО «Вкусный дом»']))
        ->assertSessionHasNoErrors();

    expect(Order::query()->sole()->inn)->toBe($inn);
})->with(['organization' => '7707083893', 'sole trader' => '500100732259']);

it('refuses a form sent faster than a person can or with the trap filled', function (array $overrides) {
    putInCart(orderedProduct());

    $this->post(route('checkout.store'), checkoutForm($overrides))->assertSessionHasErrors('form');

    expect(Order::query()->count())->toBe(0);
})->with([
    'too fast' => [fn (): array => ['started' => Crypt::encryptString((string) time())]],
    'forged time' => [fn (): array => ['started' => 'yesterday']],
    'trap' => [fn (): array => ['website' => 'https://spam.example']],
]);

it('leads a second send of the same form to the order already created', function () {
    putInCart(orderedProduct());
    $form = checkoutForm();

    $first = $this->post(route('checkout.store'), $form);
    $second = $this->post(route('checkout.store'), $form);

    $number = Order::query()->sole()->number;
    $first->assertRedirect(route('checkout.success', $number));
    $second->assertRedirect(route('checkout.success', $number));
});

it('shows «Спасибо» only to the session that sent the order', function () {
    putInCart(orderedProduct());
    $this->post(route('checkout.store'), checkoutForm(['email' => 'buyer@example.ru']));
    $number = Order::query()->sole()->number;

    $this->get(route('checkout.success', $number))
        ->assertOk()
        ->assertSee($number)
        ->assertSee('Подтверждение с составом заявки придёт на buyer@example.ru.')
        ->assertSee('Продолжить покупки');

    $this->flushSession();
    $this->get(route('checkout.success', $number))->assertNotFound();
});

it('lets one phone send three orders an hour', function () {
    foreach (range(1, 4) as $attempt) {
        putInCart(orderedProduct());
        $response = $this->post(route('checkout.store'), checkoutForm());
    }

    $response->assertSessionHasErrors(['form' => 'С этого номера или адреса уже отправлено много заявок за час. Позвоните нам — оформим по телефону.']);
    expect(Order::query()->count())->toBe(3);
});

it('marks an order of an approved company as wholesale', function () {
    $company = Company::factory()->create(['status' => CompanyStatus::Approved, 'inn' => '7707083893', 'legal_name' => 'ООО «Вкусный дом»']);
    $user = User::factory()->create(['company_id' => $company->id, 'phone' => '+7 978 000-00-01']);
    $this->actingAs($user);
    putInCart(orderedProduct());

    $this->get(route('checkout'))->assertSee('value="7707083893"', false)->assertSee('ООО «Вкусный дом»');
    $this->post(route('checkout.store'), checkoutForm(['is_legal_entity' => '1', 'inn' => '7707083893', 'company_name' => 'ООО «Вкусный дом»']));

    $order = Order::query()->sole();

    expect($order->type)->toBe(OrderType::Wholesale)
        ->and($order->company_id)->toBe($company->id)
        ->and($order->user_id)->toBe($user->id);
});

it('keeps the UTM marks of the visit in the order', function () {
    $this->get('/?utm_source=yandex&utm_campaign=ovens&utm_medium=');
    putInCart(orderedProduct());

    $this->post(route('checkout.store'), checkoutForm());

    expect(Order::query()->sole()->utm)->toBe(['utm_source' => 'yandex', 'utm_campaign' => 'ovens']);
});

it('keeps the courier address and the carrier only for their own way of delivery', function () {
    putInCart(orderedProduct());

    $this->post(route('checkout.store'), checkoutForm([
        'delivery_method' => DeliveryMethod::TransportCompany->value,
        'delivery_city' => 'Ставрополь',
        'tk_name' => 'ПЭК',
        'delivery_address' => 'Не нужен',
    ]))->assertSessionHasNoErrors();

    expect(Order::query()->sole()->only(['delivery_city', 'tk_name', 'delivery_address']))
        ->toBe(['delivery_city' => 'Ставрополь', 'tk_name' => 'ПЭК', 'delivery_address' => null]);
});
