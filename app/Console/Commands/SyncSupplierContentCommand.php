<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierContentPage;
use App\Models\Supplier;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Запускает загрузку фото, описаний и характеристик поставщика в очередь imports (ТЗ §6):
 * страница за страницей, с любой страницы — чтобы продолжить с места, где остановились. Пока
 * идёт прежняя загрузка, новая не начинается; --force начинает заново и останавливает прежнюю.
 */
class SyncSupplierContentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'supplier:content
        {--page=1 : С какой страницы списка поставщика начать}
        {--force : Начать, даже если загрузка уже идёт, и остановить прежнюю}';

    /**
     * @var string
     */
    protected $description = 'Загрузить у поставщика (с его сайта или из API) фото, описания, габариты и характеристики товаров (фоновой очередью)';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->first();

        if ($supplier === null) {
            $this->error(__('import.content.no_supplier'));

            return self::FAILURE;
        }

        $running = SyncSupplierContentPage::running($supplier->id);

        if ($running !== null && ! $this->option('force')) {
            $this->warn(__('import.content.running', ['page' => $running['page']]));

            return self::SUCCESS;
        }

        $page = max(1, (int) $this->option('page'));

        // The API is walked by the cursor of the previous page: there is no way into the middle.
        if ($page > 1 && config('suppliers.rosholod.content_source') === 'api') {
            $this->warn(__('import.content.api_from_start'));
            $page = 1;
        }

        $run = (string) Str::uuid();

        SyncSupplierContentPage::markRun($supplier->id, $run, $page);
        SyncSupplierContentPage::dispatch($supplier->id, $page, $run);

        $this->info(__('import.content.queued', ['page' => $page]));

        return self::SUCCESS;
    }
}
