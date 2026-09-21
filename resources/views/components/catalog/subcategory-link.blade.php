@props(['category'])

{{--
    Подраздел в ленте над листингом: название и число товаров моноширинным. На телефоне
    чип компактнее (36 px), цель нажатия всё равно 44.
--}}
<a
    href="{{ route('category', $category['slug']) }}"
    class="tap-target inline-flex h-9 items-center gap-2 rounded-full border border-line bg-surface px-3 text-sm whitespace-nowrap transition-colors duration-150 ease-out hover:border-accent-ink md:h-control md:px-4 md:text-base"
>
    {{ $category['name'] }}
    <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($category['products_count']) }}</span>
</a>
