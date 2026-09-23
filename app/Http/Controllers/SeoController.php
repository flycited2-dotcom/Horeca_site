<?php

namespace App\Http\Controllers;

use App\Actions\Seo\GenerateSitemap;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * robots.txt и sitemap.xml (ТЗ §14). Индексировать разрешено только боевой сайт: тестовый
 * и компьютер разработки закрыты целиком, чтобы копия каталога не попала в поиск.
 */
final class SeoController extends Controller
{
    /**
     * Pages that are the customer's own and never belong in a search engine.
     */
    public const array PRIVATE_PATHS = ['/account', '/cart', '/checkout', '/search', '/favorites'];

    /**
     * Parameters that change nothing a search engine cares about (Yandex «Clean-param»).
     */
    public const string CLEAN_PARAM = 'sort&view&utm_source&utm_medium&utm_campaign&utm_content&utm_term';

    public function robots(): Response
    {
        if (! app()->environment('production')) {
            return $this->text("User-agent: *\nDisallow: /\n");
        }

        $disallow = implode("\n", array_map(fn (string $path): string => "Disallow: {$path}", self::PRIVATE_PATHS));

        return $this->text(implode("\n\n", [
            "User-agent: *\n{$disallow}",
            "User-agent: Yandex\n{$disallow}\nClean-param: ".self::CLEAN_PARAM,
            'Sitemap: '.route('sitemap'),
        ])."\n");
    }

    /**
     * The map built at 04:00; before the first night it is built on the first request.
     */
    public function sitemap(GenerateSitemap $sitemap): BinaryFileResponse
    {
        if (! is_file(GenerateSitemap::path())) {
            $sitemap->handle();
        }

        return response()->file(GenerateSitemap::path(), ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
