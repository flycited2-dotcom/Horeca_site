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
use Illuminate\Support\Facades\Log;

/**
 * Content of one page of supplier products (TZ §6) on the "imports" queue — details first
 * (description, dimensions, characteristics), then photos — and the next page a second later:
 * short jobs that can be restarted from any page, and the supplier's site gets one page a
 * second. The smaller copies of photos are made right here, not on the "default" queue —
 * thousands of photos must not hold up order letters.
 */
final class SyncSupplierContentPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(
        private readonly int $supplierId,
        private readonly int $page = 1,
    ) {
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

    public function handle(SupplierContentSourceInterface $source, SyncSupplierPhotos $photos, SyncSupplierDetails $details): void
    {
        $supplier = Supplier::query()->find($this->supplierId);

        if ($supplier === null) {
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

        if (! $page->isLast()) {
            self::dispatch($this->supplierId, $page->page + 1)
                ->delay(now()->addMilliseconds((int) config('suppliers.rosholod.site_content.page_pause_ms')));
        }
    }
}
