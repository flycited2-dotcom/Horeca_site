<?php

namespace App\View;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;

/**
 * Две колонки кабинета вместо одного статуса (макет, экран 7): «Оплата» и «Отгрузка» —
 * у снабженца это разные вопросы, заказ бывает оплачен и не отгружен. В MVP у заявки один
 * статус (ТЗ §5.4), поэтому обе колонки выводятся из него, даты оплаты и счёта.
 * Тон задаёт маркер: ok — зелёная точка, wait — ромб, neutral — серый квадрат.
 */
final readonly class OrderProgress
{
    public const string OK = 'ok';

    public const string WAIT = 'wait';

    public const string NEUTRAL = 'neutral';

    public function __construct(
        public string $payment,
        public string $paymentTone,
        public ?string $paymentNote,
        public string $shipment,
        public string $shipmentTone,
    ) {}

    public static function of(Order $order): self
    {
        [$shipment, $shipmentTone] = self::shipment($order->status);
        [$payment, $paymentTone, $paymentNote] = self::payment($order);

        return new self(
            payment: __('shop.account.progress.payment.'.$payment),
            paymentTone: $paymentTone,
            paymentNote: $paymentNote,
            shipment: __('shop.account.progress.shipment.'.$shipment),
            shipmentTone: $shipmentTone,
        );
    }

    /**
     * @return array{string, string, ?string}
     */
    private static function payment(Order $order): array
    {
        return match (true) {
            $order->status === OrderStatus::Canceled => ['canceled', self::NEUTRAL, null],
            $order->paid_at !== null => ['paid', self::OK, $order->paid_at->timezone('Europe/Moscow')->format('d.m.Y')],
            $order->status === OrderStatus::Completed => ['paid', self::OK, null],
            filled($order->invoice_path) || $order->status === OrderStatus::Invoiced => ['awaiting', self::WAIT, __('shop.account.progress.payment.invoiced')],
            $order->payment_method === PaymentMethod::Invoice => ['preparing_invoice', self::NEUTRAL, null],
            $order->payment_method === PaymentMethod::Online => ['unpaid', self::NEUTRAL, null],
            default => ['on_delivery', self::NEUTRAL, null],
        };
    }

    /**
     * @return array{string, string}
     */
    private static function shipment(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::New => ['received', self::NEUTRAL],
            OrderStatus::Processing => ['checking', self::NEUTRAL],
            OrderStatus::Confirmed, OrderStatus::Invoiced, OrderStatus::Paid => ['preparing', self::WAIT],
            OrderStatus::Shipped => ['shipped', self::OK],
            OrderStatus::Completed => ['completed', self::OK],
            OrderStatus::Canceled => ['canceled', self::NEUTRAL],
        };
    }
}
