<?php

namespace App\Filament\Resources\ImportProfiles\Tables;

use App\Enums\ImportTrigger;
use App\Jobs\RunSupplierImport;
use App\Models\ImportProfile;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Import profiles with the two run buttons of TZ §6.4.
 *
 * A run is queued, never performed in the request: reading 15 000 products takes minutes.
 */
class ImportProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('admin.import_profile.supplier'))
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('admin.import_profile.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule')
                    ->label(__('admin.import_profile.schedule'))
                    ->placeholder('—')
                    ->fontFamily('mono'),

                IconColumn::make('is_active')
                    ->label(__('admin.import_profile.is_active'))
                    ->boolean(),

                TextColumn::make('runs_max_started_at')
                    ->label(__('admin.import_profile.last_run'))
                    ->max('runs', 'started_at')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                ActionGroup::make([
                    self::run('run', __('admin.import_profile.run'), force: false),
                    self::run('run_force', __('admin.import_profile.run_force'), force: true),
                    EditAction::make(),
                ]),
            ]);
    }

    private static function run(string $name, string $label, bool $force): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($force ? Heroicon::OutlinedArrowPath : Heroicon::OutlinedPlay)
            ->requiresConfirmation()
            ->action(function (ImportProfile $record) use ($force): void {
                // The lock is only probed here, so the manager is told at once that the
                // supplier is busy; the run takes it again inside the queue.
                $lock = Cache::lock('import:supplier:'.$record->supplier_id, 5);

                if (! $lock->get()) {
                    Notification::make()
                        ->title(__('admin.import_profile.run_blocked_title'))
                        ->body(__('import.errors.already_running', ['supplier' => $record->supplier->name]))
                        ->warning()
                        ->send();

                    return;
                }

                $lock->release();

                RunSupplierImport::dispatch($record->id, ImportTrigger::Manual, $force, auth()->id());

                Notification::make()
                    ->title(__('admin.import_profile.run_queued_title'))
                    ->body(__('admin.import_profile.run_queued', ['profile' => $record->name]))
                    ->success()
                    ->send();
            });
    }
}
