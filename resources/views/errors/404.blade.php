{{--
    404 (ТЗ §14): страница в стиле витрины — поиск по артикулу или названию и разделы
    каталога, чтобы из тупика был ход дальше; кода ошибки на экране нет (макет, экран 11).
    Старые адреса сюда не попадают: их уводит
    таблица redirects (App\Actions\Storefront\FollowRedirect).
--}}
@inject('catalog', 'App\Services\Catalog\CatalogQuery')

@php($sections = $catalog->navigationCategories())

<x-layouts.app :title="__('shop.errors.404.title')" noindex>
    <section class="flex max-w-3xl flex-col gap-5 rounded-card border border-line bg-surface p-4 md:p-8" aria-labelledby="not-found-heading">
        <div class="flex flex-col gap-2">
            <h1 id="not-found-heading" class="text-2xl font-bold md:text-3xl">{{ __('shop.errors.404.heading') }}</h1>
            <p class="max-w-prose text-base text-steel-500">{{ __('shop.errors.404.text') }}</p>
        </div>

        <x-layout.search-form id="not-found-search" variant="page" :placeholder="__('shop.layout.search_placeholder')" class="max-w-xl" />

        @if ($sections !== [])
            <nav aria-labelledby="not-found-sections" class="flex flex-col gap-3 border-t border-line-soft pt-5">
                <h2 id="not-found-sections" class="text-lg font-semibold">{{ __('shop.errors.404.sections') }}</h2>

                <ul class="flex flex-wrap gap-2">
                    @foreach ($sections as $category)
                        <li>
                            <a
                                href="{{ route('category', $category['slug']) }}"
                                class="inline-flex h-control items-center gap-2 rounded-control border border-line bg-surface px-3 text-base transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink"
                            >
                                {{ $category['name'] }}
                                <span class="text-sm text-steel-500 tabular">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <x-ui.button variant="neutral" :href="route('home')" class="self-start">{{ __('shop.errors.home') }}</x-ui.button>
    </section>
</x-layouts.app>
