<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Services\Supplier\Contracts\SupplierContentSourceInterface;
use App\Services\Supplier\Data\SupplierAttribute;
use App\Services\Supplier\Data\SupplierContentPage;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Import\AttributeValueParser;
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
    /**
     * Characteristics that are columns of a product, not characteristics.
     */
    private const array COLUMNS = [
        'Длина, мм' => 'length',
        'Ширина, мм' => 'width',
        'Глубина, мм' => 'depth',
        'Высота, мм' => 'height',
        'Вес, кг' => 'weight',
        'Гарантия (месяцев)' => 'warranty',
    ];

    /**
     * The brand comes with the price list already.
     */
    private const array SKIPPED = ['Бренд'];

    public const string COUNTRY = 'Страна производства';

    public function page(int $page): SupplierContentPage
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
        $columns = [];
        $attributes = [];

        foreach (is_array($item['characteristics'] ?? null) ? $item['characteristics'] : [] as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || trim((string) $value) === '' || in_array(trim($key), self::SKIPPED, true)) {
                continue;
            }

            $key = trim($key);

            if (isset(self::COLUMNS[$key])) {
                $columns[self::COLUMNS[$key]] = trim((string) $value);
            } else {
                $attributes[] = new SupplierAttribute($key, trim((string) $value));
            }
        }

        // «Ширина» is the width; without it «Глубина» is. When both come, the depth stays a characteristic.
        $width = AttributeValueParser::integer($columns['width'] ?? null);

        if ($width === null) {
            $width = AttributeValueParser::integer($columns['depth'] ?? null);
        } elseif (isset($columns['depth'])) {
            $attributes[] = new SupplierAttribute('Глубина, мм', $columns['depth']);
        }

        $country = $item['origin']['name'] ?? null;

        if (is_string($country) && trim($country) !== '') {
            $attributes[] = new SupplierAttribute(self::COUNTRY, mb_convert_case(trim($country), MB_CASE_TITLE));
        }

        $weight = AttributeValueParser::number($columns['weight'] ?? null);

        return new SupplierProductDetails(
            externalId: (string) $item['product_id'],
            description: $this->description($item['description'] ?? null),
            attributes: $attributes,
            lengthMm: AttributeValueParser::integer($columns['length'] ?? null),
            widthMm: $width,
            heightMm: AttributeValueParser::integer($columns['height'] ?? null),
            weightKg: $weight !== null && $weight !== '0' && ! str_starts_with($weight, '-') ? $weight : null,
            warrantyMonths: AttributeValueParser::integer($columns['warranty'] ?? null),
        );
    }

    /**
     * The description as plain text: no markup, and without the heading «Описание» the site
     * sometimes glues to the first word («ОписаниеСтол холодильный…»).
     */
    private function description(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $value));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/^Описание(?=\p{Lu})/u', '', trim($text));
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);

        return trim($text) === '' ? null : trim($text);
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
