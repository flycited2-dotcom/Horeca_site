@props(['query', 'popular' => []])

{{--
    Поиск ничего не нашёл (ТЗ §8.4, макет — экран 8): не пустой экран, а объяснение и куда
    идти дальше — самые большие разделы каталога и весь каталог — и форма «Найдём за вас»
    (лид not_found) с запросом в сообщении.
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

        <section class="mt-2 flex max-w-xl flex-col gap-2 border-t border-line-soft pt-5" aria-labelledby="not-found-lead">
            <h2 id="not-found-lead" class="text-lg font-semibold">{{ __('shop.leads.titles.not_found') }}</h2>
            <p class="text-base text-steel-500">{{ __('shop.leads.texts.not_found') }}</p>
            <x-lead.form id="not-found-lead-form" type="not_found" :message="__('shop.leads.looking_for', ['query' => $query])" :message-label="__('shop.leads.fields.what')" class="mt-2" />
        </section>
    </div>
</div>
