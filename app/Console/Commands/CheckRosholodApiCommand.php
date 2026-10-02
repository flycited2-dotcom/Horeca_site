<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Sources\Rosholod\Api\RosholodApiCheck;
use App\Services\Supplier\Sources\Rosholod\Api\RosholodApiClient;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Пробный прогон Dealer API Росхолода (ТЗ §6): только чтение, ничего в каталоге не меняется.
 * Печатает сводку и кладёт её в storage/app/private/rosholod-api-check.txt — токена в ней нет.
 */
class CheckRosholodApiCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'supplier:api-check
        {--prices : Обойти все цены (около 150 запросов)}
        {--stocks : Обойти все остатки (несколько сотен запросов)}';

    /**
     * @var string
     */
    protected $description = 'Проверить Dealer API Росхолода: токен, права, связь товаров с нашими, виды номенклатуры, склады, характеристики';

    public function handle(RosholodApiClient $api, RosholodApiCheck $check): int
    {
        if (! $api->configured()) {
            $this->error(__('import.errors.api_not_configured'));

            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->first();

        if ($supplier === null) {
            $this->error(__('import.content.no_supplier'));

            return self::FAILURE;
        }

        $this->info('Проверяю API Росхолода: только чтение, это займёт около минуты.');

        try {
            $lines = $check->run($supplier, (bool) $this->option('prices'), (bool) $this->option('stocks'));
        } catch (FeedReadException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($lines as $line) {
            $this->line($line);
        }

        Storage::disk('local')->put('rosholod-api-check.txt', now()->format('d.m.Y H:i')."\n".implode("\n", $lines)."\n");

        return self::SUCCESS;
    }
}
