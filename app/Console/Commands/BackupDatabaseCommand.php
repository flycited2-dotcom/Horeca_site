<?php

namespace App\Console\Commands;

use App\Actions\Backup\BackupFailed;
use App\Actions\Backup\CreateDatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Копия базы (ТЗ §17.5): ежедневно в 03:30. Сбой — критическая запись в журнале, а она
 * уходит в Telegram (§15.9): молчаливо не сделанная копия хуже сообщения о ней.
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Сделать копию базы: 14 копий на сервере и копия вне сервера (ТЗ §17.5)';

    public function handle(CreateDatabaseBackup $backup): int
    {
        try {
            $result = $backup->handle();
        } catch (BackupFailed $exception) {
            Log::critical(__('backup.failed', ['reason' => $exception->getMessage()]));
            $this->error(__('backup.failed', ['reason' => $exception->getMessage()]));

            return self::FAILURE;
        }

        $this->info(__('backup.done', [
            'file' => basename($result->path),
            'size' => number_format($result->bytes / 1_048_576, 1, ',', "\u{00A0}"),
            'removed' => $result->removedLocal,
        ]));

        if ($result->offsite) {
            $this->info(__('backup.offsite_done', ['removed' => $result->removedOffsite]));
        } else {
            $this->warn(__('backup.no_offsite'));
        }

        return self::SUCCESS;
    }
}
