<?php

namespace App\Filament\Resources\Companies;

use App\Actions\Companies\ApproveCompany;
use App\Actions\Companies\ChangeCompanyStatus;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PriceTier;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Модерация заявок на опт (ТЗ §11, §12) — одни и те же действия в таблице и в карточке
 * компании. Правила — в App\Actions\Companies.
 */
final class CompanyActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(fn (Company $record): string => $record->status === CompanyStatus::Approved ? __('admin.company.change_tier') : __('admin.company.approve'))
            ->icon(fn (Company $record): Heroicon => $record->status === CompanyStatus::Approved ? Heroicon::OutlinedTag : Heroicon::OutlinedCheckBadge)
            ->color(fn (Company $record): string => $record->status === CompanyStatus::Approved ? 'gray' : 'success')
            ->modalHeading(fn (Company $record): string => $record->status === CompanyStatus::Approved ? __('admin.company.change_tier') : __('admin.company.approve_heading'))
            ->modalDescription(fn (Company $record): ?string => $record->status === CompanyStatus::Approved ? null : __('admin.company.approve_hint'))
            ->schema([
                Select::make('price_tier_id')
                    ->label(__('admin.company.price_tier'))
                    ->options(fn (): array => PriceTier::query()->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                    ->native(false)
                    ->required(),

                Textarea::make('comment')
                    ->label(__('admin.company.manager_comment'))
                    ->rows(3),
            ])
            ->fillForm(fn (Company $record): array => [
                'price_tier_id' => $record->price_tier_id ?? PriceTier::query()->where('is_default', true)->value('id'),
            ])
            ->action(function (Company $record, array $data, ApproveCompany $approve): void {
                $wasApproved = $record->status === CompanyStatus::Approved;

                $approve->handle($record, PriceTier::query()->findOrFail($data['price_tier_id']), self::manager(), $data['comment'] ?? null);

                Notification::make()
                    ->title($wasApproved ? __('admin.company.tier_changed') : __('admin.company.approved_done'))
                    ->success()
                    ->send();
            });
    }

    public static function reject(): Action
    {
        return self::changeStatus('reject', CompanyStatus::Rejected, Heroicon::OutlinedXCircle, 'gray');
    }

    public static function block(): Action
    {
        return self::changeStatus('block', CompanyStatus::Blocked, Heroicon::OutlinedNoSymbol, 'danger');
    }

    public static function toPending(): Action
    {
        return self::changeStatus('to_pending', CompanyStatus::Pending, Heroicon::OutlinedArrowUturnLeft, 'gray');
    }

    private static function changeStatus(string $name, CompanyStatus $status, Heroicon $icon, string $color): Action
    {
        $withReason = $status !== CompanyStatus::Pending;

        return Action::make($name)
            ->label(__('admin.company.'.$name))
            ->icon($icon)
            ->color($color)
            ->visible(fn (Company $record): bool => $record->status !== $status)
            ->requiresConfirmation(! $withReason)
            ->schema($withReason ? [
                Textarea::make('reason')
                    ->label(__('admin.company.reason'))
                    ->helperText(__('admin.company.reason_hint'))
                    ->required()
                    ->rows(3),
            ] : [])
            ->action(function (Company $record, array $data, ChangeCompanyStatus $change) use ($status): void {
                $change->handle($record, $status, self::manager(), $data['reason'] ?? null);

                Notification::make()->title(__('admin.company.status_changed', ['status' => $status->getLabel()]))->success()->send();
            });
    }

    private static function manager(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
