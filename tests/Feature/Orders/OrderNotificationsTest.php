<?php

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Jobs\SendTelegramMessage;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\OrderTelegramMessage;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');
});

function placedOrder(array $attributes = [], int $items = 1): Order
{
    $order = Order::factory()->create($attributes + [
        'number' => 'HR-260922-0007',
        'customer_name' => 'Алексей',
        'phone' => '+7 978 123-45-67',
        'email' => 'buyer@example.ru',
        'delivery_method' => DeliveryMethod::TransportCompany,
        'delivery_city' => 'Ставрополь',
        'tk_name' => 'ПЭК',
        'total' => Money::ofRubles(767_990),
    ]);

    foreach (range(1, $items) as $index) {
        OrderItem::factory()->create(['order_id' => $order->id, 'name' => "Пароконвектомат {$index}", 'qty' => 2, 'unit' => 'шт']);
    }

    return $order->load('items');
}

it('tells the managers in Telegram and by e-mail and thanks the customer', function () {
    Queue::fake();
    Mail::fake();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'email' => 'manager@horeca.test']);
    User::factory()->create(['role' => UserRole::Customer, 'email' => 'client@horeca.test']);

    OrderCreated::dispatch(placedOrder());

    Queue::assertPushedOn('default', SendTelegramMessage::class);
    Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $mail): bool => $mail->hasTo($manager->email) && ! $mail->hasTo('client@horeca.test'));
    Mail::assertQueued(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->hasTo('buyer@example.ru'));
});

it('writes nothing to a customer who left no e-mail', function () {
    Queue::fake();
    Mail::fake();

    OrderCreated::dispatch(placedOrder(['email' => null]));

    Mail::assertNotQueued(OrderConfirmationMail::class);
});

it('keeps the name and the phone out of Telegram unless the shop switched them on', function () {
    $order = placedOrder();
    $url = 'https://shop.test/manage/orders/'.$order->id;

    $message = OrderTelegramMessage::for($order, $url, false);

    expect($message)
        ->toContain('Новая заявка HR-260922-0007 · розница', "767\u{00A0}990\u{00A0}₽", '1 позиция', '— Пароконвектомат 1 × 2', 'Ставрополь (ПЭК)', $url)
        ->not->toContain('Алексей')
        ->not->toContain('978');

    expect(OrderTelegramMessage::for($order, $url, true))->toContain('Клиент: Алексей, +7 978 123-45-67');
});

it('takes the contacts into Telegram when the setting says so', function () {
    Queue::fake();
    Setting::query()->create(['key' => 'notify.telegram_include_contacts', 'value' => true]);

    OrderCreated::dispatch(placedOrder());

    Queue::assertPushed(SendTelegramMessage::class, 1);
    expect(OrderTelegramMessage::for(placedOrder(['number' => 'HR-260922-0008']), '', true))->toContain('Алексей');
});

it('names ten lines in Telegram and counts the rest', function () {
    $message = OrderTelegramMessage::for(placedOrder(items: 12), '', false);

    expect($message)->toContain('— Пароконвектомат 10 × 2', '…и ещё 2 позиции')->not->toContain('Пароконвектомат 11');
});

it('writes to the customer only about the statuses that concern them', function () {
    Mail::fake();
    $order = placedOrder();
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $change = app(ChangeOrderStatus::class);

    $change->handle($order, OrderStatus::Processing, $manager);
    Mail::assertNotQueued(OrderStatusMail::class);

    $change->handle($order, OrderStatus::Canceled, $manager, 'Клиент передумал');
    Mail::assertQueued(OrderStatusMail::class, fn (OrderStatusMail $mail): bool => $mail->hasTo('buyer@example.ru') && $mail->comment === 'Клиент передумал');
});

it('lays the letters out with the whole order', function () {
    $order = placedOrder();

    expect((new OrderPlacedMail($order))->render())
        ->toContain('Новая заявка HR-260922-0007', 'tel:+79781234567', 'Пароконвектомат 1', 'Ставрополь', '/manage/orders/'.$order->id);

    expect((new OrderConfirmationMail($order))->render())
        ->toContain('Заявка HR-260922-0007 принята', 'Алексей, спасибо!', 'Пароконвектомат 1', "767\u{00A0}990\u{00A0}₽");

    $order->status = OrderStatus::Canceled;

    expect((new OrderStatusMail($order, 'Клиент передумал'))->render())
        ->toContain('Заявка отменена.', 'Клиент передумал');
});
