<?php

namespace App\Filament\Resources\ImportRuns\Schemas;

use App\Enums\ImportRunStatus;
use App\Filament\Resources\ImportRuns\Tables\ImportRunsTable;
use App\Models\ImportRun;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One run in detail: what it did and what went wrong (TZ §6.4).
 */
class ImportRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.import_run.summary'))
                    // While the run is going the page refreshes itself (TZ §6.4).
                    ->poll(fn (ImportRun $record): ?string => in_array(
                        $record->status,
                        [ImportRunStatus::Queued, ImportRunStatus::Running],
                        true,
                    ) ? '5s' : null)
                    ->columns(4)
                    ->schema([
                        TextEntry::make('profile.name')
                            ->label(__('admin.import_run.profile')),

                        TextEntry::make('status')
                            ->label(__('admin.import_run.status'))
                            ->badge()
                            ->color(fn (ImportRunStatus $state): string => match ($state) {
                                ImportRunStatus::Success => 'success',
                                ImportRunStatus::Failed => 'danger',
                                ImportRunStatus::Running, ImportRunStatus::Queued => 'warning',
                                ImportRunStatus::Skipped => 'gray',
                            }),

                        TextEntry::make('trigger')
                            ->label(__('admin.import_run.trigger'))
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('user.name')
                            ->label(__('admin.import_run.user'))
                            ->placeholder('—'),

                        TextEntry::make('rows_total')
                            ->label(__('admin.import_run.rows_total'))
                            ->numeric(),

                        TextEntry::make('created')
                            ->label(__('admin.import_run.created'))
                            ->numeric(),

                        TextEntry::make('updated')
                            ->label(__('admin.import_run.updated'))
                            ->numeric(),

                        TextEntry::make('unchanged')
                            ->label(__('admin.import_run.unchanged'))
                            ->numeric(),

                        TextEntry::make('discontinued')
                            ->label(__('admin.import_run.discontinued'))
                            ->numeric(),

                        TextEntry::make('errors')
                            ->label(__('admin.import_run.errors'))
                            ->numeric(),

                        IconEntry::make('is_dry_run')
                            ->label(__('admin.import_run.is_dry_run'))
                            ->boolean(),

                        TextEntry::make('duration')
                            ->label(__('admin.import_run.duration'))
                            ->state(fn (ImportRun $record): string => ImportRunsTable::duration($record)),

                        TextEntry::make('started_at')
                            ->label(__('admin.import_run.started_at'))
                            ->dateTime('d.m.Y H:i:s'),

                        TextEntry::make('finished_at')
                            ->label(__('admin.import_run.finished_at'))
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.import_run.source'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('file_path')
                            ->label(__('admin.import_run.file_path'))
                            ->placeholder('—'),

                        TextEntry::make('source_version')
                            ->label(__('admin.import_run.source_version'))
                            ->placeholder('—'),

                        TextEntry::make('error_message')
                            ->label(__('admin.import_run.error_message'))
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (ImportRun $record): bool => filled($record->error_message)),
                    ]),

                Section::make(__('admin.import_run.log'))
                    ->schema([
                        TextEntry::make('log')
                            ->hiddenLabel()
                            ->placeholder(__('admin.import_run.log_empty'))
                            ->listWithLineBreaks()
                            ->bulleted(),
                    ]),
            ]);
    }
}
