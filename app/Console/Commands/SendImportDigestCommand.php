<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The daily import summary in Telegram (TZ §13).
 *
 * Successful runs are not announced one by one — the stocks are refreshed up to 35 times
 * a day — so managers get one message in the evening instead.
 */
final class SendImportDigestCommand extends Command
{
    protected $signature = 'imports:digest {--date= : Дата в формате ГГГГ-ММ-ДД, по умолчанию сегодня}';

    protected $description = 'Отправить итоги импорта за день в Telegram';

    public function handle(TelegramNotifier $telegram): int
    {
        $date = $this->option('date') === null ? now()->startOfDay() : now()->parse($this->option('date'))->startOfDay();

        $runs = ImportRun::query()
            ->with('profile')
            ->where('is_dry_run', false)
            ->whereBetween('started_at', [$date, $date->copy()->endOfDay()])
            ->get();

        $telegram->send($this->message($runs, $date->format('d.m.Y')));

        $this->info(__('import.command.digest_sent'));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ImportRun>  $runs
     */
    private function message(Collection $runs, string $date): string
    {
        $lines = [__('import.telegram.digest_title', ['date' => $date])];

        if ($runs->isEmpty()) {
            $lines[] = __('import.telegram.digest_empty');

            return implode("\n", $lines);
        }

        foreach ($runs->groupBy('import_profile_id') as $profileRuns) {
            $profile = $profileRuns->first()->profile;

            $lines[] = __('import.telegram.digest_line', [
                'profile' => $profile instanceof ImportProfile ? $profile->name : '—',
                'runs' => $profileRuns->count(),
                'success' => $profileRuns->where('status', ImportRunStatus::Success)->count(),
                'skipped' => $profileRuns->where('status', ImportRunStatus::Skipped)->count(),
                'failed' => $profileRuns->where('status', ImportRunStatus::Failed)->count(),
                'created' => $profileRuns->sum('created'),
                'updated' => $profileRuns->sum('updated'),
                'discontinued' => $profileRuns->sum('discontinued'),
                'errors' => $profileRuns->sum('errors'),
            ]);
        }

        return implode("\n", $lines);
    }
}
