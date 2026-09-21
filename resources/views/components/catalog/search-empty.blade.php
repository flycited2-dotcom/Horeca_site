@props(['query', 'popular' => []])

{{--
    Поиск ничего не нашёл (ТЗ §8.4, макет — экран 8): не пустой экран, а объяснение и куда
    идти дальше — самые большие разделы каталога и весь каталог. Форма «Найдём за вас»
    появится вместе с лидами (спринт 4).
--}}
<div {{ $attributes->class('flex flex-col gap-6 rounded-card border border-line bg-surface p-6 md:flex-row md:gap-7 md:p-8') }}>
    <span class="flex size-14 shrink-0 items-center justify-center rounded-card border border-line bg-bg" aria-hidden="true">
        <svg class="size-7 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M16 16l4 4M8.5 11h5"/>
        </svg>
    </span>

    <div class="flex min-w-0 flex-col gap-3.5">
        <h1 class="text-xl font-semibold">{{ __('shop.search.empty_heading', ['query' => $query]) }}</h1>
        <p class="max-w-[64ch] text-md">{{ __('shop.search.empty_hint') }}</p>

        <div class="flex flex-wrap gap-2">
            @foreach ($popular as $category)
                <x-ui.button variant="neutral" :href="route('category', $category['slug'])">
                    {{ $category['name'] }} · {{ \App\Support\Typography::number($category['products_count']) }}
                </x-ui.button>
            @endforeach

            <x-ui.button variant="secondary" :href="route('catalog')">{{ __('shop.layout.all_categories') }}</x-ui.button>
        </div>
    </div>
</div>
