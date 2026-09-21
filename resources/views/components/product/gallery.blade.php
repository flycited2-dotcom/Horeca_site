@props(['product'])

{{--
    Галерея карточки товара (ТЗ §8.3, макет — экран 3). Есть фото — миниатюры колонкой
    и главное фото 4:3, по клику открывается целиком; скрипт витрины меняет главное фото
    по миниатюре без перехода. Нет фото — широкая заглушка с типом оборудования и честной
    строкой «Фото уточняется у производителя» (случай 4b), а не пустой прямоугольник.
--}}
@php
    $media = $product->getMedia(\App\Models\Product::IMAGES);
    $icon = $product->category?->icon;
    $type = $icon !== null && \Illuminate\Support\Facades\Lang::has("shop.equipment.{$icon}") ? __("shop.equipment.{$icon}") : null;
@endphp

@if ($media->isEmpty())
    <div {{ $attributes->class('relative flex aspect-[16/9] flex-col items-center justify-center gap-2.5 rounded-card border border-line bg-bg p-6 text-center md:aspect-[16/7]') }}>
        <x-ui.equipment-icon :icon="$icon" class="size-14 text-steel-400 md:size-18" />
        <p class="text-md font-medium">{{ __('shop.product.photo_pending') }}</p>

        @if ($type)
            <span class="absolute bottom-3 left-3 rounded-xs bg-line-soft px-1.75 py-1 text-xs leading-[1.3] font-medium">{{ $type }}</span>
        @endif
    </div>
@else
    @php($first = $media->first())
    <div data-gallery {{ $attributes->class(['grid gap-3', 'md:grid-cols-[88px_minmax(0,1fr)]' => $media->count() > 1]) }}>
        <a
            href="{{ $first->getUrl() }}"
            data-gallery-main
            class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-card border border-line bg-surface md:order-last"
            aria-label="{{ __('shop.product.photo_open') }}"
        >
            <img
                src="{{ $first->getUrl('full') }}"
                alt="{{ $product->name }}"
                width="1200"
                height="900"
                fetchpriority="high"
                class="h-full w-full object-contain"
            >
        </a>

        @if ($media->count() > 1)
            <ul class="flex gap-2 overflow-x-auto md:flex-col md:overflow-visible">
                @foreach ($media as $image)
                    <li class="shrink-0">
                        <a
                            href="{{ $image->getUrl() }}"
                            data-gallery-thumb
                            data-full="{{ $image->getUrl('full') }}"
                            @if ($loop->first) aria-current="true" @endif
                            class="block size-18 overflow-hidden rounded-card border border-line bg-surface aria-[current=true]:border-2 aria-[current=true]:border-accent md:size-22"
                        >
                            <img src="{{ $image->getUrl('thumb') }}" alt="{{ __('shop.product.photo', ['number' => $loop->iteration]) }}" loading="lazy" class="h-full w-full object-contain">
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
