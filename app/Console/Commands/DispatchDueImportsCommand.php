<?php

namespace App\Console\Commands;

use App\Enums\ImportTrigger;
use App\Jobs\RunSupplierImport;
use App\Models\ImportProfile;
use Cron\CronExpression;
use DateTimeInterface;
use Illuminate\Console\Command;

/**
 * Queues the import profiles whose schedule is due right now (TZ §6.4).
 *
 * The schedule lives in the profile, not in the code, so the manager changes how often
 * the catalog and the stocks are read without a deploy. This command is what the Laravel
 * scheduler calls every minute.
 */
final class DispatchDueImportsCommand extends Command
{
    protected $signature = 'supplier:dispatch-due';

    protected $description = 'Поставить в очередь профили импорта, которым пора запускаться';

    public function handle(): int
    {
        $now = now();

        $profiles = ImportProfile::query()
            ->with('supplier')
            ->where('is_active', true)
            ->whereNotNull('schedule')
            ->whereRelation('supplier', 'is_active', true)
            ->get();

        foreach ($profiles as $profile) {
            if (! $this->isDue($profile, $now)) {
                continue;
            }

            RunSupplierImport::dispatch($profile->id, ImportTrigger::Schedule);

            $this->line(__('import.command.dispatched', ['profile' => $profile->name]));
        }

        return self::SUCCESS;
    }

    private function isDue(ImportProfile $profile, DateTimeInterface $now): bool
    {
        if (! CronExpression::isValidExpression((string) $profile->schedule)) {
            $this->warn(__('import.command.invalid_schedule', ['profile' => $profile->name]));

            return false;
        }

        return (new CronExpression((string) $profile->schedule))->isDue($now);
    }
}
