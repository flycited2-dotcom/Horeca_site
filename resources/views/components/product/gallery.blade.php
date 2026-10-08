@props(['product'])

{{--
    Галерея карточки товара (ТЗ §8.3, макет — экран 3). Есть фото — главное фото 4:3 листается
    свайпом вправо и влево (прокрутка с привязкой, работает и без скриптов), рядом миниатюры.
    Нажатие открывает фото на весь экран: оно вписано в экран целиком (копия «full», не
    исходник в несколько тысяч пикселей), листается так же, закрывается крестиком, Esc и кнопкой
    «Назад». Без скриптов нажатие открывает исходное фото. Нет фото — широкая заглушка с типом
    оборудования и честной строкой «Фото уточняется у производителя» (случай 4b).
--}}
@php
    $media = $product->getMedia(\App\Models\Product::IMAGES);
    $icon = $product->category?->icon;
    $type = $icon !== null && \Illuminate\Support\Facades\Lang::has("shop.equipment.{$icon}") ? __("shop.equipment.{$icon}") : null;
    $total = $media->count();
    $counter = fn (int $current): string => __('shop.product.photo_counter', ['current' => $current, 'total' => $total]);
    $format = __('shop.product.photo_counter', ['current' => '{current}', 'total' => '{total}']);
    $arrow = 'hidden size-control items-center justify-center rounded-full border border-line bg-surface text-ink shadow-raised transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink md:flex';
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
    <div data-gallery {{ $attributes->class(['grid gap-3', 'md:grid-cols-[88px_minmax(0,1fr)]' => $total > 1]) }}>
        <div class="relative min-w-0 md:order-last">
            <ul data-gallery-track class="flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain rounded-card border border-line bg-stage [scrollbar-width:none]">
                @foreach ($media as $image)
                    <li class="aspect-[4/3] w-full shrink-0 snap-center snap-always">
                        <a
                            href="{{ $image->getUrl() }}"
                            data-gallery-open="{{ $loop->index }}"
                            class="flex size-full items-center justify-center"
                            aria-label="{{ __('shop.product.photo_open') }}"
                        >
                            <img
                                src="{{ $image->getUrl('full') }}"
                                alt="{{ $product->name }}@if ($total > 1), {{ __('shop.product.photo', ['number' => $loop->iteration]) }}@endif"
                                width="1200"
                                height="900"
                                @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                                class="size-full object-contain"
                            >
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($total > 1)
                <p data-gallery-counter data-format="{{ $format }}" data-total="{{ $total }}" class="pointer-events-none absolute right-3 bottom-3 rounded-full bg-night px-2.5 py-1 text-xs leading-none font-medium text-white tabular" aria-live="polite">{{ $counter(1) }}</p>
            @endif
        </div>

        @if ($total > 1)
            <ul class="flex gap-2 overflow-x-auto md:flex-col md:overflow-visible">
                @foreach ($media as $image)
                    <li class="shrink-0">
                        <a
                            href="{{ $image->getUrl() }}"
                            data-gallery-thumb="{{ $loop->index }}"
                            @if ($loop->first) aria-current="true" @endif
                            class="block size-18 overflow-hidden rounded-card border border-line bg-stage aria-[current=true]:border-2 aria-[current=true]:border-accent md:size-22"
                        >
                            <img src="{{ $image->getUrl('thumb') }}" alt="{{ __('shop.product.photo', ['number' => $loop->iteration]) }}" loading="lazy" class="size-full object-contain">
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Просмотр на весь экран: открывает скрипт витрины, без него нажатие ведёт на исходное фото. --}}
        <dialog data-gallery-viewer aria-label="{{ $product->name }}" class="fixed inset-0 m-0 size-full max-h-none max-w-none border-0 bg-night p-0 text-white open:flex open:flex-col">
            <div class="flex shrink-0 items-center justify-between gap-3 px-3 py-2 md:px-6">
                <p data-viewer-counter data-format="{{ $format }}" data-total="{{ $total }}" class="text-base tabular">{{ $counter(1) }}</p>
                <button type="button" data-viewer-close class="flex size-control items-center justify-center rounded-control border border-white/40 transition-colors duration-150 ease-out hover:border-white" aria-label="{{ __('shop.product.photo_close') }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>

            <div class="relative min-h-0 flex-1">
                <ul data-viewer-track class="flex size-full snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none]">
                    @foreach ($media as $image)
                        <li class="flex size-full shrink-0 snap-center snap-always items-center justify-center p-3 md:p-6">
                            <img
                                src="{{ $image->getUrl('full') }}"
                                alt="{{ $product->name }}@if ($total > 1), {{ __('shop.product.photo', ['number' => $loop->iteration]) }}@endif"
                                loading="lazy"
                                decoding="async"
                                class="max-h-full max-w-full object-contain"
                            >
                        </li>
                    @endforeach
                </ul>

                @if ($total > 1)
                    <button type="button" data-viewer-step="-1" class="{{ $arrow }} absolute top-1/2 left-4 -translate-y-1/2" aria-label="{{ __('shop.product.photo_prev') }}">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                    </button>
                    <button type="button" data-viewer-step="1" class="{{ $arrow }} absolute top-1/2 right-4 -translate-y-1/2" aria-label="{{ __('shop.product.photo_next') }}">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                @endif
            </div>
        </dialog>
    </div>
@endif
