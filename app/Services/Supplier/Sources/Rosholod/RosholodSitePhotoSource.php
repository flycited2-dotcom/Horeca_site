<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Services\Supplier\Contracts\SupplierPhotoSourceInterface;
use App\Services\Supplier\Data\SupplierPhotoPage;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Photos from the product list of rosholod.org (TZ §6), until the supplier issues its API:
 * the customer, a Rosholod dealer, decided so on 23.09.2026. The list gives the product GUID —
 * our external_id — and the photo addresses in the supplier's order. Only photos from the
 * supplier's media folder are taken. The requests are signed with the shop's name.
 */
final class RosholodSitePhotoSource implements SupplierPhotoSourceInterface
{
    public function page(int $page): SupplierPhotoPage
    {
        try {
            $response = $this->client()->retry(3, 2000, throw: false)->get((string) config('suppliers.rosholod.site_photos.url'), ['page' => $page]);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.photos_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json('results'))) {
            throw new FeedReadException(__('import.errors.photos_failed', ['reason' => 'HTTP '.$response->status()]));
        }

        $products = [];

        foreach ($response->json('results') as $item) {
            $photos = $this->photos($item);

            if ($photos !== null) {
                $products[] = $photos;
            }
        }

        return new SupplierPhotoPage(
            page: (int) ($response->json('current_page') ?? $page),
            lastPage: max(1, (int) $response->json('num_pages')),
            products: $products,
        );
    }

    public function download(string $url): string
    {
        if (! $this->isSupplierMedia($url)) {
            throw new FeedReadException(__('import.errors.photos_failed', ['reason' => 'not a supplier photo: '.$url]));
        }

        // A pause before each photo: the supplier's site is not ours to load.
        Sleep::for((int) config('suppliers.rosholod.site_photos.photo_pause_ms'))->milliseconds();

        try {
            $response = $this->client()->retry(2, 1000, throw: false)->get($url);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.photos_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if (! $response->successful()) {
            throw new FeedReadException(__('import.errors.photos_failed', ['reason' => 'HTTP '.$response->status().' '.$url]));
        }

        return $response->body();
    }

    /**
     * @param  mixed  $item  a product of the list
     */
    private function photos(mixed $item): ?SupplierProductPhotos
    {
        if (! is_array($item) || ! is_string($item['product_id'] ?? null) || $item['product_id'] === '') {
            return null;
        }

        $urls = [];

        foreach (is_array($item['images'] ?? null) ? $item['images'] : [] as $url) {
            if (is_string($url) && $this->isSupplierMedia($url) && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            } elseif (is_string($url)) {
                Log::warning('Фото поставщика не из его папки пропущено.', ['product' => $item['product_id'], 'url' => $url]);
            }
        }

        return new SupplierProductPhotos(externalId: $item['product_id'], urls: $urls);
    }

    private function isSupplierMedia(string $url): bool
    {
        return str_starts_with($url, (string) config('suppliers.rosholod.site_photos.media_prefix'));
    }

    private function client(): PendingRequest
    {
        return Http::timeout((int) config('suppliers.rosholod.site_photos.timeout'))
            ->withUserAgent((string) config('suppliers.rosholod.site_photos.user_agent'))
            ->acceptJson();
    }
}
