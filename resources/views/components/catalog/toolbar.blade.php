@props(['filters', 'sorts', 'total'])

{{--
    Строка над сеткой: сколько нашлось и сортировка. Без JS форма отправляется кнопкой,
    поэтому сортировка работает и с выключенными скриптами (ТЗ §8.2).
--}}
<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-b border-line-soft pb-3') }}>
    <p class="text-base tabular text-steel-500">
        {{ trans_choice('shop.catalog.found', $total, ['count' => number_format($total, 0, ',', "\u{00A0}")]) }}
    </p>

    <form method="get" class="flex items-center gap-2">
        @foreach (Arr::except($filters->toQuery(), ['sort']) as $name => $value)
            <x-catalog.hidden-input :name="$name" :value="$value" />
        @endforeach

        <label for="sort" class="text-sm text-steel-500">{{ __('shop.catalog.sort.label') }}</label>
        <select id="sort" name="sort" onchange="this.form.submit()"
                class="h-control rounded-control border border-line bg-surface px-3 text-base">
            @foreach ($sorts as $sort)
                <option value="{{ $sort->value }}" @selected($filters->sort === $sort)>{{ $sort->label() }}</option>
            @endforeach
        </select>

        <noscript><x-ui.button type="submit" variant="neutral">{{ __('shop.catalog.filters_apply', ['count' => $total]) }}</x-ui.button></noscript>
    </form>
</div>
