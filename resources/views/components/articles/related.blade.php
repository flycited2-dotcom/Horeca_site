@props(['articles', 'heading' => __('articles.heading')])

@if ($articles->isNotEmpty())
    <section class="mt-8 rounded-card border border-line bg-surface p-6" aria-labelledby="related-articles">
        <h2 id="related-articles" class="text-xl font-semibold">{{ $heading }}</h2>
        <ul class="mt-3 space-y-2">
            @foreach ($articles as $article)
                <li><a href="{{ \App\Services\Seo\ArticleLibrary::url($article['slug']) }}" class="text-accent-ink underline">{{ $article['h1'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
