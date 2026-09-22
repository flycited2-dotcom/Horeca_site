<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Счёт к заявке (ТЗ §12, §15): PDF лежит на приватном диске вне public — локальном при
 * разработке, в закрытой части S3 на сервере (`filesystems.invoices`) — и отдаётся только
 * через проверку прав. Новый счёт заменяет прежний — старый файл удаляется.
 */
final class AttachInvoice
{
    public const string DIRECTORY = 'invoices';

    public static function disk(): string
    {
        return (string) config('filesystems.invoices');
    }

    public function handle(Order $order, string $path): Order
    {
        $previous = $order->invoice_path;

        $order->invoice_path = $path;
        $order->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::disk())->delete($previous);
        }

        return $order;
    }
}
