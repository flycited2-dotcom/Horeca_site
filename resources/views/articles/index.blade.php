@php
    use App\Services\Seo\ArticleLibrary;
@endphp

<x-layouts.app :meta="$meta">
    <div class="mx-auto max-w-4xl">
        <x-catalog.breadcrumbs :root="['name' => __('articles.home'), 'url' => route('home')]" :current="__('articles.heading')" />
        <header class="mt-6 max-w-prose">
            <h1 class="text-balance text-3xl font-semibold md:text-4xl">{{ __('articles.heading') }}</h1>
            <p class="mt-3 text-pretty text-base text-steel-500">{{ __('articles.description') }}</p>
        </header>

        @if ($articles->isEmpty())
            <p class="mt-6 rounded-card border border-line bg-surface p-6">{{ __('articles.empty') }}</p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($articles as $article)
                    <li class="rounded-card border border-line bg-surface p-6">
                        <p class="text-sm text-steel-500">{{ $article['category'] }}</p>
                        <h2 class="mt-2 text-balance text-xl font-semibold">
                            <a href="{{ ArticleLibrary::url($article['slug']) }}" class="hover:text-accent-ink hover:underline">{{ $article['h1'] }}</a>
                        </h2>
                        <p class="mt-2 text-pretty text-base">{{ $article['description'] }}</p>
                        <p class="mt-3 text-sm text-steel-500">
                            <time datetime="{{ $article['date'] }}">{{ \Illuminate\Support\Carbon::parse($article['date'])->format('d.m.Y') }}</time>
                            <span aria-hidden="true"> · </span>{{ __('articles.read_time', ['count' => $article['read_time']]) }}
                        </p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $articles->links() }}</div>
        @endif
    </div>
</x-layouts.app>
