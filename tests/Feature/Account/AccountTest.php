<?php

use App\Actions\Orders\AttachInvoice;
use App\Enums\CompanyStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\WholesaleApplicationMail;
use App\Mail\WholesaleReceivedMail;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
});

/**
 * An order of the customer with the given number of items.
 *
 * @param  array<string, mixed>  $attributes
 */
function accountOrder(User $user, array $attributes = [], int $items = 1): Order
{
    $order = Order::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id] + $attributes);
    OrderItem::factory()->count($items)->create(['order_id' => $order->id]);

    return $order;
}

/**
 * The company form as the page sends it: the current requisites with the given changes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function companyForm(Company $company, array $overrides = []): array
{
    return $overrides + [
        'legal_name' => $company->legal_name,
        'brand_name' => $company->brand_name,
        'inn' => $company->inn,
        'kpp' => $company->kpp,
        'ogrn' => $company->ogrn,
        'segment' => $company->segment->value,
        'city' => $company->city,
        'legal_address' => $company->legal_address,
        'delivery_address' => $company->delivery_address,
        'bank_name' => $company->bank_name,
        'bik' => $company->bik,
        'account' => $company->account,
        'corr_account' => $company->corr_account,
        'contact_person' => $company->contact_person,
        'phone' => $company->phone,
        'email' => $company->email,
    ];
}

function companyMember(CompanyStatus $status = CompanyStatus::Approved): User
{
    $user = wholesaleCustomer(PriceTier::factory()->create(['name' => 'Опт-1']), $status);
    $user->company->forceFill(['inn' => '7714365426', 'legal_name' => 'ООО «Вкусный дом»', 'city' => 'Симферополь'])->save();

    return $user->refresh();
}

it('lets only a signed-in customer in and brings them back to the account page they asked for', function () {
    $this->get(route('account'))->assertRedirect(route('login'));
    $this->get(route('account.orders'))->assertRedirect(route('login'));
    $this->get(route('account.company'))->assertRedirect(route('login'));

    $user = User::factory()->create(['email' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026']);

    $this->post(route('login.store'), ['login' => 'irina@kafe.ru', 'password' => 'Kofe-Kruassan-2026'])
        ->assertRedirect(route('account.company'));
    $this->assertAuthenticatedAs($user);
});

it('shows the summary: prices, manager contacts, quick actions and the latest five orders', function () {
    $user = User::factory()->create(['name' => 'Ирина Соколова']);
    setting('contacts.phones', '+7 978 000-11-22');
    setting('contacts.email', 'sales@gastrosnab.ru');
    setting('contacts.schedule', 'Пн–Пт 9:00–18:00');

    $orders = collect(range(1, 6))->map(fn (int $n) => accountOrder($user, ['number' => "HR-230926-000{$n}"]));
    $foreign = accountOrder(User::factory()->create(), ['number' => 'HR-230926-0099']);

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Ирина Соколова')
        ->assertSee('Для юрлиц и ИП')
        ->assertSee('href="'.route('wholesale').'"', false)
        ->assertSee('+7 978 000-11-22')
        ->assertSee('sales@gastrosnab.ru')
        ->assertSee('Пн–Пт 9:00–18:00')
        ->assertSee('Повторить заказ HR-230926-0006')
        ->assertSee('HR-230926-0002')
        ->assertDontSee('HR-230926-0001')
        ->assertDontSee($foreign->number)
        ->assertSee('Все заявки <span class="font-mono text-sm text-steel-500 tabular">6</span>', false)
        ->assertDontSee('href="'.route('account.company').'"', false);

    expect($orders)->toHaveCount(6);
});

it('tells an approved company that its prices are open and names the tier only when allowed', function () {
    $user = companyMember();

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('ООО «Вкусный дом»')
        ->assertSee('ИНН 7714365426')
        ->assertSee('Открыты')
        ->assertSee('href="'.route('account.company').'"', false)
        ->assertDontSee('Ценовая группа: Опт-1');

    setting('pricing.show_tier_name', true);
    app()->forgetScopedInstances();

    $this->actingAs($user)->get(route('account'))->assertSee('Ценовая группа: Опт-1');
});

it('shows a company on moderation its status and where to follow the application', function () {
    $user = companyMember(CompanyStatus::Pending);

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('Заявка на опт на проверке')
        ->assertSee('Откроются после проверки')
        ->assertSee('Статус заявки');
});

it('lists orders with payment and shipment as two columns', function () {
    $user = User::factory()->create();
    $paid = accountOrder($user, ['number' => 'HR-230926-0001', 'status' => OrderStatus::Shipped, 'payment_method' => PaymentMethod::Invoice], 3);
    $paid->forceFill(['paid_at' => '2026-09-21 12:00:00'])->save();
    accountOrder($user, ['number' => 'HR-230926-0002', 'status' => OrderStatus::Invoiced, 'payment_method' => PaymentMethod::Invoice]);
    accountOrder($user, ['number' => 'HR-230926-0003', 'status' => OrderStatus::New, 'payment_method' => PaymentMethod::Cash]);

    $first = $paid->items()->orderBy('id')->first();

    $this->actingAs($user)->get(route('account.orders'))
        ->assertOk()
        ->assertSeeInOrder(['HR-230926-0003', 'HR-230926-0002', 'HR-230926-0001'])
        ->assertSee('href="'.route('account.order', $paid).'"', false)
        ->assertSee('/account/orders/HR-230926-0001', false)
        ->assertSeeInOrder([$first->name, 'и ещё 2 позиции'])
        ->assertSeeInOrder(['HR-230926-0002', 'Ждёт оплаты', 'Счёт выставлен', 'Готовим к отгрузке'])
        ->assertSeeInOrder(['HR-230926-0001', 'Оплачен', '21.09.2026', 'Отгружен'])
        ->assertSeeInOrder(['HR-230926-0003', 'При получении', 'Заявка принята'])
        ->assertSee('Показано 1–3 из 3');
});

it('lists orders with the same number of queries however many there are', function () {
    $user = User::factory()->create();
    accountOrder($user, [], 2);

    $count = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('account.orders'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    // The first request after new categories rebuilds the navigation cache: count the second.
    $count();
    $few = $count();

    foreach (range(1, 8) as $n) {
        accountOrder($user, [], 3);
    }

    $count();
    expect($count())->toBe($few);
});

it('opens an order with its items, totals and the statuses the customer is told about', function () {
    $user = User::factory()->create();
    $manager = User::factory()->manager()->create();
    $order = accountOrder($user, [
        'status' => OrderStatus::Invoiced,
        'subtotal' => Money::ofRubles(60_000),
        'discount' => Money::ofRubles(6_000),
        'total' => Money::ofRubles(54_000),
        'comment' => 'Позвоните до обеда',
    ], 0);
    $order->forceFill(['invoice_path' => 'invoices/schet.pdf'])->save();
    $product = Product::factory()->create(['category_id' => Category::factory()->create(['is_active' => true])->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Шкаф холодильный ШХс-1,4', 'sku' => '11000019106', 'qty' => 2, 'price' => Money::ofRubles(30_000), 'sum' => Money::ofRubles(60_000)]);

    foreach ([
        [OrderStatus::New, OrderStatus::Processing, 'Позвонили клиенту'],
        [OrderStatus::Processing, OrderStatus::Processing, 'Отметка о звонке'],
        [OrderStatus::Processing, OrderStatus::Confirmed, 'Наличие подтвердили'],
        [OrderStatus::Confirmed, OrderStatus::Invoiced, null],
    ] as [$from, $to, $comment]) {
        OrderStatusLog::query()->create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $manager->id, 'comment' => $comment]);
    }

    $this->actingAs($user)->get(route('account.order', $order))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('href="'.route('product', $product).'"', false)
        ->assertSee('Шкаф холодильный ШХс-1,4')
        ->assertSee('11000019106')
        ->assertSee("2\u{00A0}шт × 30\u{00A0}000\u{00A0}₽")
        ->assertSee("−6\u{00A0}000\u{00A0}₽")
        ->assertSee("54\u{00A0}000\u{00A0}₽")
        ->assertSee('Позвоните до обеда')
        ->assertSee('href="'.route('account.order.invoice', $order).'"', false)
        ->assertSee('action="'.route('account.order.repeat', $order).'"', false)
        ->assertSeeInOrder(['Заявка отправлена', 'Подтверждена', 'Наличие подтвердили', 'Выставлен счёт'])
        ->assertDontSee('Позвонили клиенту')
        ->assertDontSee('Отметка о звонке');
});

it('keeps other customers\' and deleted orders closed', function () {
    $user = User::factory()->create();
    $foreign = accountOrder(User::factory()->create());
    $deleted = accountOrder($user);
    $deleted->delete();

    $this->actingAs($user)->get(route('account.order', $foreign))->assertForbidden();
    $this->actingAs($user)->post(route('account.order.repeat', $foreign))->assertForbidden();
    $this->actingAs($user)->get(route('account.order.invoice', $foreign))->assertForbidden();
    $this->actingAs($user)->get('/account/orders/'.$deleted->number)->assertNotFound();
});

it('repeats an order at today\'s prices and lists what can not be bought', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $available = Product::factory()->create(['category_id' => $category->id, 'retail_price' => Money::ofRubles(48_000)]);
    $discontinued = Product::factory()->discontinued()->create(['category_id' => $category->id, 'name' => 'Плита снятая']);
    $onRequest = Product::factory()->priceOnRequest()->create(['category_id' => $category->id, 'name' => 'Печь по запросу']);
    $removed = Product::factory()->create(['category_id' => $category->id]);

    $order = accountOrder($user, ['number' => 'HR-230926-0007'], 0);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $available->id, 'qty' => 3, 'price' => Money::ofRubles(45_000), 'sum' => Money::ofRubles(135_000)]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $discontinued->id, 'name' => 'Плита снятая']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $onRequest->id, 'name' => 'Печь по запросу']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $removed->id, 'name' => 'Стол удалённый', 'sku' => '22000011111']);
    $removed->delete();

    $this->actingAs($user)->post(route('account.order.repeat', $order))
        ->assertRedirect(route('cart'))
        ->assertSessionHas('notice.text', 'Позиции заявки HR-230926-0007 в корзине: 1.');

    $item = Cart::query()->where('user_id', $user->id)->sole()->items()->sole();

    expect($item->product_id)->toBe($available->id)
        ->and($item->qty)->toBe(3)
        ->and($item->price->equals(Money::ofRubles(48_000)))->toBeTrue();

    $this->actingAs($user)->get(route('cart'))
        ->assertOk()
        ->assertSee('Не добавлены из заявки HR-230926-0007')
        ->assertSeeInOrder(['Плита снятая', 'Снят с производства'])
        ->assertSeeInOrder(['Печь по запросу', 'Цена по запросу'])
        ->assertSeeInOrder(['Стол удалённый', '22000011111', 'Удалён из каталога'])
        ->assertSee('href="'.route('product', $discontinued).'"', false);
});

it('says so when nothing from the order can be bought again', function () {
    $user = User::factory()->create();
    $order = accountOrder($user, ['number' => 'HR-230926-0008'], 0);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => Product::factory()->priceOnRequest()->create()->id]);

    $this->actingAs($user)->post(route('account.order.repeat', $order))
        ->assertRedirect(route('cart'))
        ->assertSessionHas('notice.text', 'Ни одну позицию заявки HR-230926-0008 сейчас купить нельзя — список ниже.');
});

it('gives the invoice only to the customer of the order', function () {
    Storage::fake(AttachInvoice::disk());
    Storage::disk(AttachInvoice::disk())->put('invoices/HR-1.pdf', '%PDF-1.4 счёт');

    $user = User::factory()->create();
    $order = accountOrder($user, ['number' => 'HR-230926-0009']);
    $withoutInvoice = accountOrder($user);

    $this->actingAs($user)->get(route('account.order.invoice', $order))->assertNotFound();

    $order->forceFill(['invoice_path' => 'invoices/HR-1.pdf'])->save();

    $this->actingAs($user)->get(route('account.order.invoice', $order))
        ->assertOk()
        ->assertDownload('schet-HR-230926-0009.pdf');

    $this->actingAs($user)->get(route('account.order.invoice', $withoutInvoice))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('account.order.invoice', $order))->assertForbidden();
});

it('sends a customer without a company to the wholesale application', function () {
    $this->actingAs(User::factory()->create())->get(route('account.company'))->assertRedirect(route('wholesale'));
    $this->actingAs(User::factory()->create())->put(route('account.company.update'), [])->assertForbidden();
});

it('saves requisites and keeps an approved company approved while INN and name stay', function () {
    $user = companyMember();
    $company = $user->company;

    $this->actingAs($user)->get(route('account.company'))
        ->assertOk()
        ->assertSee('Смена ИНН или юр. названия отправит компанию на повторную проверку')
        ->assertSee('name="bik"', false)
        ->assertSee('value="7714365426"', false);

    $this->actingAs($user)->put(route('account.company.update'), companyForm($company, [
        'kpp' => '771401 001',
        'bik' => '044525225',
        'account' => '4070 2810 1000 0000 0001',
        'corr_account' => '30101810400000000225',
        'delivery_address' => 'Симферополь, ул. Киевская, 1',
        'phone' => '8 978 123 45 67',
        'email' => 'Buh@VkusnyDom.ru',
    ]))
        ->assertRedirect(route('account.company'))
        ->assertSessionHas('notice.text', 'Реквизиты сохранены.');

    $company->refresh();

    expect($company->status)->toBe(CompanyStatus::Approved)
        ->and($company->kpp)->toBe('771401001')
        ->and($company->account)->toBe('40702810100000000001')
        ->and($company->phone)->toBe('+7 978 123-45-67')
        ->and($company->email)->toBe('buh@vkusnydom.ru')
        ->and($company->delivery_address)->toBe('Симферополь, ул. Киевская, 1');

    Mail::assertNothingQueued();
});

it('sends a company back to moderation when its INN changes and tells the managers', function () {
    User::factory()->manager()->create(['email' => 'manager@gastrosnab.ru']);
    $user = companyMember();

    $this->actingAs($user)->put(route('account.company.update'), companyForm($user->company, ['inn' => '7707083893']))
        ->assertRedirect(route('account.company'))
        ->assertSessionHas('notice.text', 'Реквизиты сохранены. ИНН или название изменились — компания снова на проверке, до неё цены розничные.');

    $company = $user->company->refresh();

    expect($company->status)->toBe(CompanyStatus::Pending)
        ->and($company->inn)->toBe('7707083893')
        ->and($user->refresh()->hasApprovedCompany())->toBeFalse();

    Mail::assertQueued(WholesaleApplicationMail::class, fn (WholesaleApplicationMail $mail): bool => $mail->recheck
        && $mail->hasTo('manager@gastrosnab.ru')
        && $mail->envelope()->subject === 'Повторная проверка: ООО «Вкусный дом»');
    Mail::assertNotQueued(WholesaleReceivedMail::class);
});

it('does not let a blocked company change its INN or name', function () {
    $user = companyMember(CompanyStatus::Blocked);

    $this->actingAs($user)->get(route('account.company'))
        ->assertOk()
        ->assertSee('ИНН и название меняет менеджер');

    $this->actingAs($user)->from(route('account.company'))
        ->put(route('account.company.update'), companyForm($user->company, ['legal_name' => 'ООО «Другой дом»']))
        ->assertRedirect(route('account.company'))
        ->assertSessionHasErrors('inn');

    expect($user->company->refresh()->legal_name)->toBe('ООО «Вкусный дом»')
        ->and($user->company->status)->toBe(CompanyStatus::Blocked);
});

it('checks the numeric requisites', function () {
    $user = companyMember();

    $this->actingAs($user)->from(route('account.company'))
        ->put(route('account.company.update'), companyForm($user->company, ['inn' => '7714365427', 'kpp' => '12345', 'ogrn' => '123', 'bik' => 'abc']))
        ->assertSessionHasErrors(['inn', 'kpp', 'ogrn', 'bik']);

    expect(session('errors')->first('kpp'))->toBe('В поле «КПП» должно быть 9 цифр.')
        ->and(session('errors')->first('ogrn'))->toBe('ОГРН — 13 цифр у организации или 15 у ИП.');
});
