@php
    use App\Services\Seo\ArticleLibrary;
    use App\Support\StructuredData;
@endphp

<x-layouts.app :meta="$meta" og-type="article">
    <div class="mx-auto max-w-3xl">
        <x-catalog.breadcrumbs
            :root="['name' => __('articles.home'), 'url' => route('home')]"
            :links="[['name' => __('articles.heading'), 'url' => ArticleLibrary::url()]]"
            :current="$article['h1']"
        />

        <article class="mt-6 min-w-0 rounded-card border border-line bg-surface p-4 sm:p-8">
            <header class="mb-6">
                <p class="text-sm font-medium text-steel-500">{{ $article['category'] }}</p>
                <h1 class="mt-2 text-balance text-3xl font-semibold md:text-4xl">{{ $article['h1'] }}</h1>
                <p class="mt-3 text-sm text-steel-500">
                    <time datetime="{{ $article['date'] }}">{{ \Illuminate\Support\Carbon::parse($article['date'])->format('d.m.Y') }}</time>
                    <span aria-hidden="true"> · </span>{{ __('articles.read_time', ['count' => $article['read_time']]) }}
                </p>
            </header>
            <div class="rich-text max-w-prose overflow-x-auto break-words">{{ $body }}</div>

            <section class="mt-8" aria-labelledby="article-catalog">
                <h2 id="article-catalog" class="text-xl font-semibold">{{ __('articles.catalog_links') }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($catalogLinks as $url => $label)
                        <li><a href="{{ $url }}" class="text-accent-ink underline">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </section>

            <section class="mt-8 rounded-card border border-line bg-bg p-4 sm:p-6" aria-labelledby="article-cta">
                <h2 id="article-cta" class="text-xl font-semibold">{{ __('articles.contact') }}</h2>
                <p class="mt-2 max-w-prose text-pretty">{{ $article['cta'] }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <x-ui.button type="button" popovertarget="article-contact">{{ __('articles.request') }}</x-ui.button>
                    @foreach ($contacts->phones as $phone)
                        <a href="{{ $phone['href'] }}" class="inline-flex min-h-control items-center text-accent-ink underline">{{ __('articles.call', ['phone' => $phone['label']]) }}</a>
                    @endforeach
                    @if ($contacts->email)
                        <a href="mailto:{{ $contacts->email }}" class="inline-flex min-h-control items-center text-accent-ink underline">{{ __('articles.email') }}</a>
                    @endif
                </div>
                <x-lead.dialog id="article-contact" type="question" />
            </section>
        </article>

        <x-articles.related :articles="$related" :heading="__('articles.related')" />
    </div>
    <script type="application/ld+json">{!! StructuredData::json($schema) !!}</script>
    @if ($faq = StructuredData::faqSection($body))
        <script type="application/ld+json">{!! StructuredData::json($faq) !!}</script>
    @endif
</x-layouts.app>
