<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierPhotosPage;
use App\Models\Supplier;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;

/**
 * Запускает загрузку фото поставщика в очередь imports (ТЗ §6): страница за страницей, с любой
 * страницы — чтобы продолжить с места, где остановились.
 */
class SyncSupplierPhotosCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'supplier:photos {--page=1 : С какой страницы списка поставщика начать}';

    /**
     * @var string
     */
    protected $description = 'Загрузить фото товаров поставщика в хранилище (фоновой очередью)';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->first();

        if ($supplier === null) {
            $this->error(__('import.photos.no_supplier'));

            return self::FAILURE;
        }

        $page = max(1, (int) $this->option('page'));

        SyncSupplierPhotosPage::dispatch($supplier->id, $page);

        $this->info(__('import.photos.queued', ['page' => $page]));

        return self::SUCCESS;
    }
}
