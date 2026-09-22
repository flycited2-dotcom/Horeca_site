<?php

namespace App\Filament\Resources\Orders;

use App\Actions\Orders\AttachInvoice;
use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\MarkOrderCalled;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Действия менеджера с заявкой (ТЗ §12) — одни и те же в таблице и на бланке.
 * Правила — в действиях App\Actions\Orders.
 */
final class OrderActions
{
    public static function changeStatus(): Action
    {
        return Action::make('change_status')
            ->label(__('admin.order.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->schema([
                Select::make('status')
                    ->label(__('admin.order.status'))
                    ->options(OrderStatus::class)
                    ->native(false)
                    ->required(),

                Textarea::make('comment')
                    ->label(__('admin.order.comment'))
                    ->helperText(__('admin.order.comment_hint'))
                    ->requiredIf('status', OrderStatus::Canceled->value)
                    ->validationMessages(['required_if' => __('admin.order.cancel_comment_required')])
                    ->rows(3),
            ])
            ->fillForm(fn (Order $record): array => ['status' => $record->status])
            ->action(function (Order $record, array $data, ChangeOrderStatus $change): void {
                $status = $data['status'] instanceof OrderStatus ? $data['status'] : OrderStatus::from((string) $data['status']);
                $change->handle($record, $status, self::manager(), $data['comment'] ?? null);

                Notification::make()->title(__('admin.order.status_changed', ['status' => $status->getLabel()]))->success()->send();
            });
    }

    public static function called(): Action
    {
        return Action::make('called')
            ->label(__('admin.order.called'))
            ->icon(Heroicon::OutlinedPhone)
            ->color('gray')
            ->action(function (Order $record, MarkOrderCalled $called): void {
                $called->handle($record, self::manager());

                Notification::make()->title(__('admin.order.called_done'))->success()->send();
            });
    }

    public static function attachInvoice(): Action
    {
        return Action::make('attach_invoice')
            ->label(fn (Order $record): string => $record->invoice_path ? __('admin.order.replace_invoice') : __('admin.order.attach_invoice'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->schema([
                FileUpload::make('invoice')
                    ->label(__('admin.order.invoice'))
                    ->disk(AttachInvoice::DISK)
                    ->directory(AttachInvoice::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10 * 1024)
                    ->required(),
            ])
            ->action(function (Order $record, array $data, AttachInvoice $attach): void {
                $attach->handle($record, (string) $data['invoice']);

                Notification::make()->title(__('admin.order.invoice_attached'))->success()->send();
            });
    }

    public static function downloadInvoice(): Action
    {
        return Action::make('download_invoice')
            ->label(__('admin.order.download_invoice'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Order $record): bool => filled($record->invoice_path))
            ->action(function (Order $record): ?StreamedResponse {
                $disk = Storage::disk(AttachInvoice::DISK);

                if (! $disk->exists((string) $record->invoice_path)) {
                    Notification::make()->title(__('admin.order.invoice_missing'))->warning()->send();

                    return null;
                }

                return $disk->download((string) $record->invoice_path, "schet-{$record->number}.pdf");
            });
    }

    private static function manager(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
