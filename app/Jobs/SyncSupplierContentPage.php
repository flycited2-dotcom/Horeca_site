<?php

namespace App\Jobs;

use App\Actions\Catalog\ContentSyncResult;
use App\Actions\Catalog\SyncSupplierDetails;
use App\Actions\Catalog\SyncSupplierPhotos;
use App\Models\Supplier;
use App\Services\Supplier\Contracts\SupplierContentSourceInterface;
use App\Services\Supplier\Exceptions\FeedReadException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Content of one page of supplier products (TZ §6) on the "imports" queue — details first
 * (description, dimensions, characteristics), then photos — and the next page a second later:
 * short jobs that can be restarted from any page, and the supplier's site gets one page a
 * second. The smaller copies of photos are made right here, not on the "default" queue —
 * thousands of photos must not hold up order letters.
 *
 * The chain marks its run in the cache: the nightly start does not begin a second chain
 * over one still going, and a job of any other run than the marked one stops — so a chain
 * started anew with --force ends the old one.
 */
final class SyncSupplierContentPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    /**
     * How long a run stays marked after its last page: longer than a page with both retries.
     */
    public const int RUN_TTL_MINUTES = 30;

    /**
     * The run the job belongs to. A plain property with a default: jobs queued before runs
     * were marked come out of the queue without it.
     */
    private ?string $run = null;

    public function __construct(
        private readonly int $supplierId,
        private readonly int $page = 1,
        ?string $run = null,
    ) {
        $this->run = $run;
        $this->onConnection(config('import.queue_connection'));
        $this->onQueue(config('import.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * The run going on for the supplier: its id and the page it has reached, or null.
     *
     * @return array{run: string, page: int}|null
     */
    public static function running(int $supplierId): ?array
    {
        $value = Cache::get(self::runKey($supplierId));

        return is_array($value) ? $value : null;
    }

    public static function markRun(int $supplierId, string $run, int $page): void
    {
        Cache::put(self::runKey($supplierId), ['run' => $run, 'page' => $page], now()->addMinutes(self::RUN_TTL_MINUTES));
    }

    public function handle(SupplierContentSourceInterface $source, SyncSupplierPhotos $photos, SyncSupplierDetails $details): void
    {
        $supplier = Supplier::query()->find($this->supplierId);
        $current = self::running($this->supplierId);

        // Старую цепочку сменил новый запуск — она останавливается.
        if ($supplier === null || ($current !== null && $current['run'] !== $this->run)) {
            return;
        }

        config()->set('media-library.queue_conversions_by_default', false);

        $page = $source->page($this->page);
        $counts = ['details_updated' => 0, 'updated' => 0, 'unchanged' => 0, 'no_product' => 0, 'failed' => 0];

        foreach ($page->details as $product) {
            if ($details->handle($supplier, $product) === ContentSyncResult::Updated) {
                $counts['details_updated']++;
            }
        }

        foreach ($page->products as $product) {
            try {
                $result = $photos->handle($supplier, $product);
                $counts[match ($result) {
                    ContentSyncResult::Updated => 'updated',
                    ContentSyncResult::Unchanged => 'unchanged',
                    ContentSyncResult::NoProduct => 'no_product',
                }]++;
            } catch (FeedReadException $exception) {
                $counts['failed']++;
                Log::warning($exception->getMessage(), ['supplier' => $supplier->id, 'product' => $product->externalId]);
            }
        }

        Log::info(__('import.content.page_done', ['page' => $page->page, 'last' => $page->lastPage]), $counts);

        if ($page->isLast()) {
            $this->forgetRun();

            return;
        }

        if ($this->run !== null) {
            self::markRun($this->supplierId, $this->run, $page->page + 1);
        }

        self::dispatch($this->supplierId, $page->page + 1, $this->run)
            ->delay(now()->addMilliseconds((int) config('suppliers.rosholod.site_content.page_pause_ms')));
    }

    /**
     * A page that failed all its tries ends the run: the next start begins without --force.
     */
    public function failed(): void
    {
        $this->forgetRun();
    }

    private function forgetRun(): void
    {
        if ($this->run !== null && (self::running($this->supplierId)['run'] ?? null) === $this->run) {
            Cache::forget(self::runKey($this->supplierId));
        }
    }

    private static function runKey(int $supplierId): string
    {
        return 'supplier-content-run:'.$supplierId;
    }
}
