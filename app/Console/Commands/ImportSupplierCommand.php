<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use App\Services\Supplier\Exceptions\ImportAlreadyRunningException;
use App\Services\Supplier\Import\ImportRunner;
use Illuminate\Console\Command;

/**
 * Runs an import profile from the console (TZ §6.4).
 *
 * --dry-run reads the feed, checks the thresholds and reports what would change,
 * without leaving a trace in the catalog.
 */
final class ImportSupplierCommand extends Command
{
    protected $signature = 'supplier:import
        {profile : Номер профиля импорта или его источник, например rosholod.catalog_xml}
        {--dry-run : Пробный прогон: показать, что изменится, и ничего не менять}
        {--force : Скачать выгрузку, даже если она не изменилась}';

    protected $description = 'Запустить импорт каталога или остатков поставщика';

    public function handle(ImportRunner $runner): int
    {
        $profile = $this->profile();

        if ($profile === null) {
            $this->error(__('import.command.profile_not_found', ['id' => $this->argument('profile')]));

            return self::FAILURE;
        }

        try {
            $run = $runner->run(
                $profile,
                ImportTrigger::Cli,
                force: (bool) $this->option('force'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (ImportAlreadyRunningException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($run->refresh());

        return $run->status === ImportRunStatus::Failed ? self::FAILURE : self::SUCCESS;
    }

    private function profile(): ?ImportProfile
    {
        $value = (string) $this->argument('profile');

        return ImportProfile::query()
            ->with('supplier')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('source', $value),
            )
            ->first();
    }

    private function report(ImportRun $run): void
    {
        $this->line(__('import.command.finished', ['run' => $run->id, 'status' => $run->status->getLabel()]));

        if ($run->error_message !== null) {
            $this->error($run->error_message);
        }

        if ($run->status !== ImportRunStatus::Success) {
            return;
        }

        $this->table([
            __('import.report.rows'),
            __('import.report.created'),
            __('import.report.updated'),
            __('import.report.unchanged'),
            __('import.report.discontinued'),
            __('import.report.errors'),
        ], [[
            $run->rows_total,
            $run->created,
            $run->updated,
            $run->unchanged,
            $run->discontinued,
            $run->errors,
        ]]);

        foreach (array_slice($run->log ?? [], 0, 20) as $message) {
            $this->warn($message);
        }
    }
}
