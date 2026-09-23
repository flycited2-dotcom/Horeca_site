<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Supplier\Contracts\SupplierPhotoSourceInterface;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos of a supplier product (TZ §5, §6): taken from the source and marked source = supplier
 * with source_url — the same photos are not downloaded twice, and the supplier API will later
 * replace them. Manual photos of the manager are never touched (§6.6) and stay first. New
 * photos are downloaded before the old ones go: a failed download leaves the product as it was.
 */
final class SyncSupplierPhotos
{
    public const string SOURCE = 'supplier';

    public function __construct(private readonly SupplierPhotoSourceInterface $source) {}

    /**
     * @throws FeedReadException when a photo could not be downloaded
     */
    public function handle(Supplier $supplier, SupplierProductPhotos $photos): PhotoSyncResult
    {
        $product = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('external_id', $photos->externalId)
            ->first();

        if ($product === null) {
            return PhotoSyncResult::NoProduct;
        }

        $current = $product->getMedia(Product::IMAGES)
            ->filter(fn (Media $media): bool => $media->getCustomProperty('source') === self::SOURCE)
            ->values();

        if ($current->map(fn (Media $media): mixed => $media->getCustomProperty('source_url'))->all() === $photos->urls) {
            return PhotoSyncResult::Unchanged;
        }

        $files = [];

        try {
            foreach ($photos->urls as $url) {
                $path = (string) tempnam(sys_get_temp_dir(), 'supplier-photo-');
                file_put_contents($path, $this->source->download($url));
                $files[$url] = $path;
            }

            foreach ($files as $url => $path) {
                try {
                    $product->addMedia($path)
                        ->usingFileName($this->fileName($url))
                        ->withCustomProperties(['source' => self::SOURCE, 'source_url' => $url])
                        ->toMediaCollection(Product::IMAGES);
                } catch (FileCannotBeAdded $exception) {
                    Log::warning('Фото поставщика не подошло и пропущено.', ['product' => $product->id, 'url' => $url, 'reason' => $exception->getMessage()]);
                }
            }
        } finally {
            foreach ($files as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        $current->each(fn (Media $media): ?bool => $media->delete());

        return PhotoSyncResult::Updated;
    }

    /**
     * A readable Latin file name with the extension of the supplier's file.
     */
    private function fileName(string $url): string
    {
        $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg';
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'photo';

        return Str::limit($base, 80, '').'.'.$extension;
    }
}
