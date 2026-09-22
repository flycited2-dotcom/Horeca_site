<?php

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Widgets\SalesOverview;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use App\Support\Typography;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = staffUser();
    $this->actingAs($this->manager);
});

function orderWithItems(array $attributes = []): Order
{
    $order = Order::factory()->create($attributes + ['status' => OrderStatus::New, 'phone' => '+7 978 123-45-67']);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'sku' => '11000019106',
        'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01',
        'unit' => 'шт',
        'qty' => 2,
        'price' => Money::ofRubles(383_995),
        'sum' => Money::ofRubles(767_990),
    ]);

    return $order;
}

it('opens the orders, an order blank and the leads', function () {
    $order = orderWithItems();

    $this->get(OrderResource::getUrl('index'))->assertOk()->assertSee($order->number);
    $this->get(OrderResource::getUrl('view', ['record' => $order]))
        ->assertOk()
        ->assertSee('tel:+79781234567', false)
        ->assertSee('Пароконвектомат ПКА 10-1/1ВП2-01');
    $this->get(LeadResource::getUrl('index'))->assertOk();
});

it('lists the orders in tabs by status with a fixed number of queries', function () {
    orderWithItems(['status' => OrderStatus::New]);
    orderWithItems(['status' => OrderStatus::Completed]);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords(Order::query()->where('status', OrderStatus::New)->get())
        ->assertCanNotSeeTableRecords(Order::query()->where('status', OrderStatus::Completed)->get())
        ->set('activeTab', 'completed')
        ->assertCanSeeTableRecords(Order::query()->where('status', OrderStatus::Completed)->get());

    foreach (range(1, 20) as $index) {
        orderWithItems();
    }

    DB::enableQueryLog();
    Livewire::test(ListOrders::class);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(25);
});

it('changes the status with a line in the history and tells the customer', function () {
    Event::fake([OrderStatusChanged::class]);
    $order = orderWithItems();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('change_status', data: ['status' => OrderStatus::Invoiced->value, 'comment' => 'Счёт отправлен на почту'])
        ->assertHasNoActionErrors();

    $order->refresh();
    $log = $order->statusLogs()->latest('id')->first();

    expect($order->status)->toBe(OrderStatus::Invoiced)
        ->and($order->manager_id)->toBe($this->manager->id)
        ->and($log->from_status)->toBe(OrderStatus::New)
        ->and($log->to_status)->toBe(OrderStatus::Invoiced)
        ->and($log->comment)->toBe('Счёт отправлен на почту');

    Event::assertDispatched(OrderStatusChanged::class);
});

it('does not cancel an order without a reason', function () {
    $order = orderWithItems();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('change_status', data: ['status' => OrderStatus::Canceled->value, 'comment' => ''])
        ->assertHasActionErrors(['comment' => 'required_if']);

    expect($order->refresh()->status)->toBe(OrderStatus::New);
});

it('marks the call and takes a new order into work', function () {
    $order = orderWithItems();

    Livewire::test(ListOrders::class)->callTableAction('called', $order);

    expect($order->refresh()->status)->toBe(OrderStatus::Processing)
        ->and($order->statusLogs()->latest('id')->value('comment'))->toBe('Позвонили клиенту');
});

it('keeps the invoice on the private disk and gives it back to the staff', function () {
    Storage::fake('local');
    $order = orderWithItems();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('attach_invoice', data: ['invoice' => UploadedFile::fake()->create('schet.pdf', 120, 'application/pdf')])
        ->assertHasNoActionErrors();

    $path = $order->refresh()->invoice_path;

    expect($path)->toStartWith('invoices/');
    Storage::disk('local')->assertExists($path);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('download_invoice')
        ->assertFileDownloaded("schet-{$order->number}.pdf");
});

it('lets only an administrator delete an order, and only softly', function () {
    $order = orderWithItems();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('delete');

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('delete');

    expect(Order::query()->find($order->id))->toBeNull()
        ->and(Order::withTrashed()->find($order->id))->not->toBeNull();
});

it('copies the order line by line', function () {
    $order = orderWithItems();

    expect(OrderInfolist::composition($order->load('items')))
        ->toBe("11000019106 · Пароконвектомат ПКА 10-1/1ВП2-01 — 2 шт × 383\u{00A0}995\u{00A0}₽ = 767\u{00A0}990\u{00A0}₽\nИтого: ".Typography::money($order->total));
});

it('changes the status of a lead right in the table', function () {
    $lead = Lead::factory()->create(['type' => LeadType::PriceRequest, 'status' => LeadStatus::New]);

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords([$lead])
        ->call('updateTableColumnState', 'status', (string) $lead->getKey(), LeadStatus::InWork->value);

    expect($lead->refresh()->status)->toBe(LeadStatus::InWork)
        ->and($lead->manager_id)->toBe($this->manager->id);
});

it('counts the new orders and leads on the dashboard', function () {
    orderWithItems();
    orderWithItems(['status' => OrderStatus::Completed]);
    Lead::factory()->count(2)->create(['status' => LeadStatus::New]);

    Livewire::test(SalesOverview::class)
        ->assertSee('Новые заявки')
        ->assertSee('Заявок сегодня')
        ->assertSee('За 7 дней: 2')
        ->assertSee('Новые лиды');
});

it('keeps customers out of the orders', function () {
    $this->actingAs(User::factory()->create());

    $this->get(OrderResource::getUrl('index'))->assertForbidden();
});
