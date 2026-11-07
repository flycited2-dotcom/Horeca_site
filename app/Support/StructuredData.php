<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Pricing\Price;
use App\View\StorefrontShell;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * schema.org markup of the storefront pages (TZ §8.3, §14).
 */
final class StructuredData
{
    /**
     * Product with its Offer: price in rubles, availability by TZ §6.5. A product with the
     * price on request gets no Offer — search engines would take an empty price for zero.
     *
     * @return array<string, mixed>
     */
    public static function product(Product $product, ?Price $price, ?string $image = null): array
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'mpn' => $product->model,
            'image' => $image,
            'description' => $product->description !== null ? mb_substr(trim(strip_tags($product->description)), 0, 500) : null,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => SeoUrl::canonical(route('product', $product)),
                'price' => $price->amount->toDecimal(),
                'priceCurrency' => 'RUB',
                'availability' => $product->availability->schemaOrg(),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function store(StorefrontShell $shell): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            '@id' => SeoUrl::canonical(route('home')).'#store',
            'name' => $shell->siteName,
            'url' => SeoUrl::canonical(route('home')),
            'telephone' => $shell->phone() ? substr($shell->phone()['href'], 4) : null,
            'email' => $shell->email,
            'address' => $shell->address ? ['@type' => 'PostalAddress', 'streetAddress' => $shell->address] : null,
            'openingHours' => self::openingHours($shell->schedule),
            'areaServed' => ['Крым', 'Россия'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function openingHours(?string $schedule): ?string
    {
        $days = ['пн' => 'Mo', 'вт' => 'Tu', 'ср' => 'We', 'чт' => 'Th', 'пт' => 'Fr', 'сб' => 'Sa', 'вс' => 'Su'];
        $dayPattern = implode('|', array_keys($days));

        if (! preg_match("/^($dayPattern)(?:\s*[-–—]\s*($dayPattern))?\s+(\d{1,2}:[0-5]\d)\s*[-–—]\s*(\d{1,2}:[0-5]\d)$/u", mb_strtolower(trim((string) $schedule)), $parts)) {
            return null;
        }

        $range = $days[$parts[1]].($parts[2] === '' ? '' : '-'.$days[$parts[2]]);

        return $range.' '.str_pad($parts[3], 5, '0', STR_PAD_LEFT).'-'.str_pad($parts[4], 5, '0', STR_PAD_LEFT);
    }

    public static function itemList(iterable $products): array
    {
        $items = [];

        foreach ($products as $product) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $product->name,
                'url' => SeoUrl::canonical(route('product', $product)),
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
    }

    public static function faqSection(HtmlString $html): ?array
    {
        $document = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR, 'UTF-8');
        $inFaq = false;
        $current = null;
        $questions = [];

        foreach ($document->body->childNodes as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->localName === 'h2') {
                $inFaq = mb_strtolower(trim($node->textContent)) === 'частые вопросы';
                $current = null;
            } elseif ($inFaq && $node->localName === 'h3') {
                $current = count($questions);
                $questions[] = ['question' => trim($node->textContent), 'answer' => ''];
            } elseif ($current !== null) {
                $questions[$current]['answer'] .= ' '.$node->textContent;
            }
        }

        return self::faqData($questions);
    }

    /**
     * FAQPage (TZ §14) from a page text in Markdown: a heading of the second or third level
     * that ends with «?» is a question, the text under it up to the next heading — the answer.
     * null when the text has no question with an answer.
     *
     * @return array<string, mixed>|null
     */
    public static function faq(?string $markdown): ?array
    {
        $questions = [];
        $current = null;

        foreach (preg_split('/\R/u', (string) $markdown) ?: [] as $line) {
            if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $heading) === 1) {
                $current = preg_match('/^\s{0,3}#{2,3}\s/u', $line) === 1 && str_ends_with($heading[1], '?') ? count($questions) : null;

                if ($current !== null) {
                    $questions[] = ['question' => $heading[1], 'answer' => ''];
                }

                continue;
            }

            if ($current !== null) {
                $questions[$current]['answer'] .= $line."\n";
            }
        }

        return self::faqData(array_map(function (array $item): array {
            $item['answer'] = strip_tags(Str::markdown($item['answer'], ['html_input' => 'strip']));

            return $item;
        }, $questions));
    }

    private static function faqData(array $questions): ?array
    {
        $entities = [];

        foreach ($questions as $item) {
            $answer = trim((string) preg_replace('/\s+/u', ' ', $item['answer']));

            if ($item['question'] !== '' && $answer !== '') {
                $entities[] = [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }

        return $entities === [] ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * JSON for a <script type="application/ld+json">: «</script>» inside a name can not close
     * the tag, Cyrillic stays readable.
     *
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
