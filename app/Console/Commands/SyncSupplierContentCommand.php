<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierContentPage;
use App\Models\Supplier;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;

/**
 * Запускает загрузку фото поставщика в очередь imports (ТЗ §6): страница за страницей, с любой
 * страницы — чтобы продолжить с места, где остановились.
 */
class SyncSupplierContentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'supplier:content {--page=1 : С какой страницы списка поставщика начать}';

    /**
     * @var string
     */
    protected $description = 'Загрузить с сайта поставщика фото, описания, габариты и характеристики товаров (фоновой очередью)';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->first();

        if ($supplier === null) {
            $this->error(__('import.content.no_supplier'));

            return self::FAILURE;
        }

        $page = max(1, (int) $this->option('page'));

        SyncSupplierContentPage::dispatch($supplier->id, $page);

        $this->info(__('import.content.queued', ['page' => $page]));

        return self::SUCCESS;
    }
}
