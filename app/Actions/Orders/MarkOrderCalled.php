<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * «Позвонили» (ТЗ §12) — быстрая отметка: менеджер связался с клиентом. Новая заявка
 * переходит в работу и закрепляется за менеджером; в журнале остаётся отметка о звонке.
 */
final class MarkOrderCalled
{
    public function __construct(private readonly ChangeOrderStatus $status) {}

    public function handle(Order $order, User $manager): Order
    {
        if ($order->status === OrderStatus::New) {
            return $this->status->handle($order, OrderStatus::Processing, $manager, __('admin.order.called_note'));
        }

        DB::transaction(function () use ($order, $manager): void {
            $order->manager_id ??= $manager->id;
            $order->save();

            OrderStatusLog::query()->create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'user_id' => $manager->id,
                'comment' => __('admin.order.called_note'),
            ]);
        });

        return $order;
    }
}
