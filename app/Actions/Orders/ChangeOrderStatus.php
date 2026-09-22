<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Статус заявки меняет менеджер (ТЗ §12): каждая смена — строка журнала с автором
 * и комментарием; отмена без комментария не принимается. «Оплачена» ставит дату оплаты.
 * Клиенту о смене пишет слушатель OrderStatusChanged (§13).
 */
final class ChangeOrderStatus
{
    public function handle(Order $order, OrderStatus $status, User $manager, ?string $comment = null): Order
    {
        $comment = filled($comment) ? trim((string) $comment) : null;

        if ($status === OrderStatus::Canceled && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('admin.order.cancel_comment_required')]);
        }

        $previous = $order->status;

        if ($previous === $status) {
            return $order;
        }

        DB::transaction(function () use ($order, $status, $manager, $comment, $previous): void {
            $order->status = $status;
            $order->manager_id ??= $manager->id;

            if ($status === OrderStatus::Paid && $order->paid_at === null) {
                $order->paid_at = now();
            }

            $order->save();

            OrderStatusLog::query()->create([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => $status,
                'user_id' => $manager->id,
                'comment' => $comment,
            ]);
        });

        OrderStatusChanged::dispatch($order, $previous, $comment);

        return $order;
    }
}
