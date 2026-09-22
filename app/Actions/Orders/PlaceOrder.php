<?php

namespace App\Actions\Orders;

use App\Actions\Cart\ChangeCart;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use App\Services\Cart\CartLine;
use App\Services\Cart\CartNotReady;
use App\Services\Cart\CartReview;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Заявка из корзины (ТЗ §10.3). Одной транзакцией: заказ с номером HR-ДДММГГ-NNNN по
 * московской дате, позиции со снимком артикула, кода 1С, названия, единицы, наличия
 * и цены, запись в журнал статусов, очистка корзины. Повтор с тем же idempotency_key —
 * даже одновременный — возвращает уже созданный заказ. Уведомления — по событию
 * OrderCreated после фиксации транзакции, ответ клиенту их не ждёт.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CartReview $review,
        private readonly ChangeCart $cart,
        private readonly GenerateOrderNumber $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated checkout form
     * @param  array{ip?: ?string, user_agent?: ?string, utm?: ?array<string, string>}  $meta
     */
    public function handle(array $data, ?User $user, array $meta = []): Order
    {
        $existing = $this->existing((string) $data['idempotency_key']);

        if ($existing !== null) {
            return $existing;
        }

        $summary = $this->review->review($user);

        if (! $summary->canCheckout()) {
            throw new CartNotReady($summary->isEmpty());
        }

        try {
            $order = DB::transaction(function () use ($data, $user, $meta, $summary): Order {
                $retail = Money::zero();

                foreach ($summary->lines as $line) {
                    $retail = $retail->add(($line->price?->retail ?? $line->unitPrice())->multiply($line->quantity()));
                }

                $total = $summary->total();
                $wholesale = $user?->hasApprovedCompany() === true;
                $delivery = DeliveryMethod::from((string) $data['delivery_method']);
                $legal = (bool) ($data['is_legal_entity'] ?? false);

                $order = Order::query()->create([
                    'number' => $this->numbers->handle(),
                    'idempotency_key' => $data['idempotency_key'],
                    'user_id' => $user?->id,
                    'company_id' => $wholesale ? $user->company_id : null,
                    'type' => $wholesale ? OrderType::Wholesale : OrderType::Retail,
                    'customer_name' => $data['name'],
                    'phone' => Phone::normalize((string) $data['phone']),
                    'email' => $data['email'] ?? null,
                    'is_legal_entity' => $legal,
                    'inn' => $legal ? $data['inn'] : null,
                    'company_name' => $legal ? $data['company_name'] : null,
                    'delivery_method' => $delivery,
                    'delivery_city' => $delivery === DeliveryMethod::TransportCompany ? $data['delivery_city'] : null,
                    'delivery_address' => $delivery === DeliveryMethod::CourierCity ? $data['delivery_address'] : null,
                    'tk_name' => $delivery === DeliveryMethod::TransportCompany ? $data['tk_name'] : null,
                    'payment_method' => PaymentMethod::from((string) $data['payment_method']),
                    'comment' => filled($data['comment'] ?? null) ? $data['comment'] : null,
                    'subtotal' => $retail,
                    'discount' => $retail->subtract($total),
                    'total' => $total,
                    'utm' => $meta['utm'] ?? null,
                    'ip' => $meta['ip'] ?? null,
                    'user_agent' => isset($meta['user_agent']) ? mb_substr((string) $meta['user_agent'], 0, 500) : null,
                ]);

                $order->items()->createMany(array_map(fn (CartLine $line): array => [
                    'product_id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'supplier_code' => $line->product->supplier_code,
                    'name' => $line->product->name,
                    'unit' => $line->product->unit,
                    'availability' => $line->product->availability->value,
                    'qty' => $line->quantity(),
                    'price' => $line->unitPrice(),
                    'sum' => $line->sum(),
                ], $summary->lines));

                OrderStatusLog::query()->create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => OrderStatus::New,
                    'user_id' => $user?->id,
                ]);

                $this->cart->clear($user);

                return $order;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The same form sent twice at once: the other request has created the order.
            return $this->existing((string) $data['idempotency_key']) ?? throw $exception;
        }

        OrderCreated::dispatch($order);

        return $order;
    }

    private function existing(string $key): ?Order
    {
        // A deleted order still owns its key: the same form must not create it again.
        return Order::withTrashed()->where('idempotency_key', $key)->first();
    }
}
