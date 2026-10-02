<?php

namespace App\Services\Supplier\Sources\Rosholod\Api;

use App\Services\Supplier\Contracts\SupplierContentSourceInterface;
use App\Services\Supplier\Data\SupplierContentPage;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Sources\Rosholod\RosholodDetailsMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Product content from the Dealer API of Rosholod (TZ §6): full cards (`/products/export`, twenty
 * a page) with the description, characteristics, country and the list of photos. It replaces the
 * product list of the supplier's site. A page continues from the cursor the previous page returned;
 * the API does not tell how many pages there are, so while it has more, the last page is the next one.
 *
 * Photos are public files of the supplier: they are downloaded without the token, only from the
 * hosts of the config, with a pause, and kept by us.
 */
final class RosholodApiContentSource implements SupplierContentSourceInterface
{
    public function __construct(private readonly RosholodApiClient $api) {}

    public function page(int $page, ?string $cursor = null): SupplierContentPage
    {
        $response = $this->api->get('/api/v1/dealer/products/export', [
            'limit' => (int) config('suppliers.rosholod.api.export_limit'),
            'cursor' => $cursor,
        ]);

        $products = [];
        $details = [];

        foreach (is_array($response['items'] ?? null) ? $response['items'] : [] as $item) {
            $externalId = $this->externalId($item);

            if ($externalId === null) {
                continue;
            }

            $products[] = new SupplierProductPhotos($externalId, $this->photoUrls($item, $externalId));
            $details[] = $this->details($externalId, $item);
        }

        $next = is_string($response['next_cursor'] ?? null) && $response['next_cursor'] !== '' ? $response['next_cursor'] : null;
        $more = ($response['has_more'] ?? false) === true && $next !== null;

        return new SupplierContentPage(
            page: $page,
            lastPage: $more ? $page + 1 : $page,
            products: $products,
            details: $details,
            nextCursor: $more ? $next : null,
        );
    }

    public function download(string $url): string
    {
        if (! $this->isSupplierMedia($url)) {
            throw new FeedReadException(__('import.errors.api_photo_host', ['url' => $url]));
        }

        Sleep::for((int) config('suppliers.rosholod.api.photo_pause_ms'))->milliseconds();

        try {
            $response = Http::timeout((int) config('suppliers.rosholod.api.timeout'))
                ->withUserAgent((string) config('suppliers.rosholod.api.user_agent'))
                ->retry(2, 1000, throw: false)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.api_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if (! $response->successful()) {
            throw new FeedReadException(__('import.errors.api_failed', ['reason' => 'HTTP '.$response->status().' '.$url]));
        }

        return $response->body();
    }

    /**
     * Our product is found by this identifier: the source id of the accounting system by default,
     * the identifier of the API itself when the config says so.
     *
     * @param  mixed  $item  a card of the page
     */
    private function externalId(mixed $item): ?string
    {
        $field = config('suppliers.rosholod.api.external_id_field') === 'id' ? 'id' : 'source_id';
        $value = is_array($item) ? ($item[$field] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function photoUrls(array $item, string $externalId): array
    {
        $images = is_array($item['images'] ?? null) ? $item['images'] : [];

        // The main image first: the API puts it first already, the sort keeps it so.
        usort($images, fn (mixed $a, mixed $b): int => (int) (is_array($b) && ($b['is_default'] ?? false) === true) <=> (int) (is_array($a) && ($a['is_default'] ?? false) === true));

        $urls = [];

        foreach ($images as $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : null;

            if (! is_string($url) || in_array($url, $urls, true)) {
                continue;
            }

            if ($this->isSupplierMedia($url)) {
                $urls[] = $url;
            } else {
                Log::warning('Фото поставщика не с его адреса пропущено.', ['product' => $externalId, 'url' => $url]);
            }
        }

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function details(string $externalId, array $item): SupplierProductDetails
    {
        $country = is_array($item['country'] ?? null) ? ($item['country']['name'] ?? null) : null;

        return RosholodDetailsMapper::details(
            $externalId,
            $item['description'] ?? null,
            $item['attributes'] ?? null,
            is_string($country) ? $country : null,
        );
    }

    private function isSupplierMedia(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        foreach ((array) config('suppliers.rosholod.api.media_hosts') as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
