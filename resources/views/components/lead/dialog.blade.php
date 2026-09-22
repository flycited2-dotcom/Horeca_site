@props([
    'id',
    'type',
    'product' => null,
    'shared' => false,
])

{{--
    Окно короткой заявки (ТЗ §6.5, §8.3): «Запросить цену», «Уточнить срок», «Подобрать
    аналог», «Купить в 1 клик». Нативный popover — открывается кнопкой с popovertarget
    и закрывается по Esc без скриптов; на телефоне выезжает снизу. $shared — общее окно
    листинга: товар и его название подставляет нажатая кнопка (скрипт витрины).
--}}
@php
    use App\Enums\LeadType;

    $type = $type instanceof LeadType ? $type : LeadType::from($type);
@endphp

<div id="{{ $id }}" popover class="dialog-sheet" aria-labelledby="{{ $id }}-title" data-lead-dialog>
    <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ __('shop.leads.titles.'.$type->value) }}</h2>
            @if ($product || $shared)
                <p data-lead-product-name @if (! $product) hidden @endif class="text-sm text-steel-500">{{ $product?->name }}</p>
            @endif
        </div>
        <button
            type="button"
            popovertarget="{{ $id }}"
            popovertargetaction="hide"
            class="flex size-control shrink-0 items-center justify-center rounded-control border border-line transition-colors duration-150 ease-out hover:border-accent-ink"
        >
            <span class="sr-only">{{ __('shop.leads.close') }}</span>
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>

    <p class="mt-2 text-base text-steel-500">{{ __('shop.leads.texts.'.$type->value) }}</p>

    <x-lead.form :id="$id.'-form'" :type="$type" :product="$product" class="mt-4" />
</div>
