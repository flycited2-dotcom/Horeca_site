<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogQuery;
use App\Services\Seo\ArticleLibrary;
use App\Services\Seo\Meta;
use App\Services\Seo\MetaBuilder;
use App\Services\Settings\Settings;
use App\Support\SeoUrl;
use App\View\RichText;
use App\View\StoreContacts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class ArticleController extends Controller
{
    private const int PER_PAGE = 12;

    public function index(Request $request, ArticleLibrary $library, MetaBuilder $meta): View|RedirectResponse
    {
        if ($redirect = $this->trailingSlash($request, ArticleLibrary::url())) {
            return $redirect;
        }

        $page = max(1, $request->integer('page', 1));
        $articles = $library->all();
        abort_if($page > max(1, (int) ceil($articles->count() / self::PER_PAGE)), 404);

        return view('articles.index', [
            'articles' => (new LengthAwarePaginator(
                $articles->forPage($page, self::PER_PAGE), $articles->count(), self::PER_PAGE, $page,
            ))->withPath(ArticleLibrary::url()),
            'meta' => new Meta(
                title: __('articles.heading').' | '.$meta->site(),
                description: __('articles.description'),
                canonical: ArticleLibrary::url().($page > 1 ? '?page='.$page : ''),
                robots: $articles->isEmpty() ? 'noindex, follow' : null,
            ),
        ]);
    }

    public function show(Request $request, string $slug, ArticleLibrary $library, CatalogQuery $catalog, MetaBuilder $meta, Settings $settings): View|RedirectResponse
    {
        $articles = $library->all();
        $article = $articles->get($slug);
        abort_if($article === null, 404);

        $canonical = ArticleLibrary::url($slug);
        if ($redirect = $this->trailingSlash($request, $canonical)) {
            return $redirect;
        }

        $organization = [
            '@type' => 'Organization',
            '@id' => SeoUrl::canonical(route('home')).'#store',
            'name' => $meta->site(),
            'url' => SeoUrl::canonical(route('home')),
        ];

        return view('articles.show', [
            'article' => $article,
            'body' => RichText::html($article['body'], $articles->map(fn (array $item): string => '/articles/'.$item['slug'].'/')->all()),
            'related' => $library->related($article),
            'catalogLinks' => $library->catalogLinks($article, $catalog),
            'contacts' => StoreContacts::from($settings),
            'meta' => new Meta(title: $article['title'], description: $article['description'], canonical: $canonical),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article['h1'],
                'description' => $article['description'],
                'mainEntityOfPage' => $canonical,
                'datePublished' => $article['date'],
                'dateModified' => $article['modified'],
                'author' => $organization,
                'publisher' => $organization,
            ],
        ]);
    }

    private function trailingSlash(Request $request, string $url): ?RedirectResponse
    {
        [$path, $query] = array_pad(explode('?', $request->getRequestUri(), 2), 2, null);
        if (str_ends_with($path, '/')) {
            return null;
        }

        return redirect()->away($url.($query === null ? '' : '?'.$query), 301);
    }
}
