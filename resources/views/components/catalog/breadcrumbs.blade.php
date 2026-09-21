@props(['category' => null, 'product' => null, 'brand' => null, 'current' => null])

{{--
    Хлебные крошки с разметкой BreadcrumbList (ТЗ §8.2): каталог и разделы или бренды.
    $current — последняя крошка-текст служебной страницы: «Каталог / Пароконвектоматы / Сравнение».
--}}
@php
    $trail = $brand === null
        ? [['name' => __('shop.layout.catalog'), 'url' => route('catalog')]]
        : [['name' => __('shop.brands.title'), 'url' => route('brands')], ['name' => $brand->name, 'url' => null]];

    $branch = collect();
    for ($node = $product?->category ?? $category; $node !== null; $node = $node->parent) {
        $branch->prepend($node);
    }

    foreach ($branch as $node) {
        $trail[] = ['name' => $node->name, 'url' => $node->is_active ? route('category', $node) : null];
    }

    if ($product !== null) {
        $trail[] = ['name' => $product->name, 'url' => null];
    }

    if ($current !== null) {
        $trail[] = ['name' => $current, 'url' => null];
    }
@endphp

<nav {{ $attributes->merge(['aria-label' => __('shop.layout.catalog')]) }}>
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-steel-500"
        itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach ($trail as $position => $crumb)
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="flex items-center gap-2">
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" itemprop="item" class="transition-colors duration-150 ease-out hover:text-accent-ink">
                        <span itemprop="name">{{ $crumb['name'] }}</span>
                    </a>
                @else
                    <span itemprop="name" class="text-ink">{{ $crumb['name'] }}</span>
                @endif

                <meta itemprop="position" content="{{ $position + 1 }}">
                @unless ($loop->last)<span aria-hidden="true" class="text-steel-400">/</span>@endunless
            </li>
        @endforeach
    </ol>
</nav>
