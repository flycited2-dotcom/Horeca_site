<?php

namespace App\Filament\Widgets;

use App\Enums\ImportRunStatus;
use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Models\ImportProfile;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The last run of every import profile on the dashboard (TZ §12).
 */
class LatestImports extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('admin.dashboard.latest_imports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ImportProfile::query()->with(['supplier', 'latestRun']))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.import_run.profile')),

                IconColumn::make('is_active')
                    ->label(__('admin.import_profile.is_active'))
                    ->boolean(),

                TextColumn::make('latestRun.status')
                    ->label(__('admin.import_run.status'))
                    ->badge()
                    ->placeholder(__('admin.dashboard.never'))
                    ->color(fn (?ImportRunStatus $state): string => match ($state) {
                        ImportRunStatus::Success => 'success',
                        ImportRunStatus::Failed => 'danger',
                        ImportRunStatus::Running, ImportRunStatus::Queued => 'warning',
                        default => 'gray',
                    })
                    ->url(fn (ImportProfile $record): ?string => $record->latestRun === null
                        ? null
                        : ImportRunResource::getUrl('view', ['record' => $record->latestRun])),

                TextColumn::make('latestRun.started_at')
                    ->label(__('admin.import_run.started_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('latestRun.created')
                    ->label(__('admin.import_run.created'))
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('latestRun.updated')
                    ->label(__('admin.import_run.updated'))
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('latestRun.errors')
                    ->label(__('admin.import_run.errors'))
                    ->numeric()
                    ->placeholder('—'),
            ]);
    }
}
