<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Services\Supplier\Contracts\SupplierContentSourceInterface;
use App\Services\Supplier\Data\SupplierContentPage;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Product content from the product list of rosholod.org (TZ §6), until the supplier issues its
 * API: photos (the customer, a Rosholod dealer, decided so on 23.09.2026), and the full
 * description, dimensions, weight, warranty and characteristics (decided 24.09.2026). The list
 * gives the product GUID — our external_id. Only photos from the supplier's media folder are
 * taken. The requests are signed with the shop's name.
 */
final class RosholodSiteSource implements SupplierContentSourceInterface
{
    public function page(int $page, ?string $cursor = null): SupplierContentPage
    {
        try {
            $response = $this->client()->retry(3, 2000, throw: false)->get((string) config('suppliers.rosholod.site_content.url'), ['page' => $page]);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.content_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json('results'))) {
            throw new FeedReadException(__('import.errors.content_failed', ['reason' => 'HTTP '.$response->status()]));
        }

        $products = [];
        $details = [];

        foreach ($response->json('results') as $item) {
            $photos = $this->photos($item);

            if ($photos !== null) {
                $products[] = $photos;
                $details[] = $this->details($item);
            }
        }

        return new SupplierContentPage(
            page: (int) ($response->json('current_page') ?? $page),
            lastPage: max(1, (int) $response->json('num_pages')),
            products: $products,
            details: $details,
        );
    }

    public function download(string $url): string
    {
        if (! $this->isSupplierMedia($url)) {
            throw new FeedReadException(__('import.errors.content_failed', ['reason' => 'not a supplier photo: '.$url]));
        }

        // A pause before each photo: the supplier's site is not ours to load.
        Sleep::for((int) config('suppliers.rosholod.site_content.photo_pause_ms'))->milliseconds();

        try {
            $response = $this->client()->retry(2, 1000, throw: false)->get($url);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.content_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if (! $response->successful()) {
            throw new FeedReadException(__('import.errors.content_failed', ['reason' => 'HTTP '.$response->status().' '.$url]));
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

    /**
     * @param  array<string, mixed>  $item  a product of the list with a product_id
     */
    private function details(array $item): SupplierProductDetails
    {
        $country = $item['origin']['name'] ?? null;

        return RosholodDetailsMapper::details(
            (string) $item['product_id'],
            $item['description'] ?? null,
            $item['characteristics'] ?? null,
            is_string($country) ? $country : null,
        );
    }

    private function isSupplierMedia(string $url): bool
    {
        return str_starts_with($url, (string) config('suppliers.rosholod.site_content.media_prefix'));
    }

    private function client(): PendingRequest
    {
        return Http::timeout((int) config('suppliers.rosholod.site_content.timeout'))
            ->withUserAgent((string) config('suppliers.rosholod.site_content.user_agent'))
            ->acceptJson();
    }
}
