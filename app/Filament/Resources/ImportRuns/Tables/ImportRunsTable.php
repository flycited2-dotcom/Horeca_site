<?php

namespace App\Filament\Resources\ImportRuns\Tables;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ImportRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),

                TextColumn::make('profile.name')
                    ->label(__('admin.import_run.profile'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.import_run.status'))
                    ->badge()
                    ->color(fn (ImportRunStatus $state): string => match ($state) {
                        ImportRunStatus::Success => 'success',
                        ImportRunStatus::Failed => 'danger',
                        ImportRunStatus::Running, ImportRunStatus::Queued => 'warning',
                        ImportRunStatus::Skipped => 'gray',
                    }),

                TextColumn::make('trigger')
                    ->label(__('admin.import_run.trigger'))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_dry_run')
                    ->label(__('admin.import_run.is_dry_run'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rows_total')
                    ->label(__('admin.import_run.rows_total'))
                    ->numeric(),

                TextColumn::make('created')
                    ->label(__('admin.import_run.created'))
                    ->numeric(),

                TextColumn::make('updated')
                    ->label(__('admin.import_run.updated'))
                    ->numeric(),

                TextColumn::make('unchanged')
                    ->label(__('admin.import_run.unchanged'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('discontinued')
                    ->label(__('admin.import_run.discontinued'))
                    ->numeric(),

                TextColumn::make('errors')
                    ->label(__('admin.import_run.errors'))
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('started_at')
                    ->label(__('admin.import_run.started_at'))
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('duration')
                    ->label(__('admin.import_run.duration'))
                    ->state(fn (ImportRun $record): string => self::duration($record)),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('import_profile_id')
                    ->label(__('admin.import_run.profile'))
                    ->relationship('profile', 'name'),

                SelectFilter::make('status')
                    ->label(__('admin.import_run.status'))
                    ->options(ImportRunStatus::class),

                TernaryFilter::make('is_dry_run')
                    ->label(__('admin.import_run.is_dry_run')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function duration(ImportRun $run): string
    {
        if ($run->started_at === null || $run->finished_at === null) {
            return '—';
        }

        $seconds = $run->finished_at->diffInSeconds($run->started_at, absolute: true);

        return $seconds < 60
            ? "{$seconds} с"
            : intdiv($seconds, 60).' мин '.($seconds % 60).' с';
    }
}
