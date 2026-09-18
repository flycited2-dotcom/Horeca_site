<?php

namespace App\Filament\Resources\ImportRuns\Pages;

use App\Enums\ImportRunStatus;
use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Models\ImportRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A run page. While the run is going the page refreshes itself, so the manager sees
 * the progress without pressing anything (TZ §6.4).
 */
class ViewImportRun extends ViewRecord
{
    protected static string $resource = ImportRunResource::class;

    public function getPollingInterval(): ?string
    {
        /** @var ImportRun $run */
        $run = $this->getRecord();

        return in_array($run->status, [ImportRunStatus::Queued, ImportRunStatus::Running], true) ? '5s' : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_log')
                ->label(__('admin.import_run.log_file'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (ImportRun $record): bool => filled($record->log_file))
                ->action(function (ImportRun $record): ?StreamedResponse {
                    $path = storage_path((string) $record->log_file);

                    if (! is_file($path)) {
                        Notification::make()
                            ->title(__('admin.import_run.log_file_missing'))
                            ->warning()
                            ->send();

                        return null;
                    }

                    return response()->streamDownload(
                        function () use ($path): void {
                            readfile($path);
                        },
                        "import-{$record->id}.log",
                    );
                }),
        ];
    }
}
