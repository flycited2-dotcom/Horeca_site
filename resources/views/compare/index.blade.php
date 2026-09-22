{{--
    Сравнение моделей (ТЗ §8.5, макет — экран 13): настоящая таблица, колонка параметров
    закреплена слева, в шапке колонки — фото или заглушка, бренд и артикул, название, цена,
    статус и покупка. Сначала «Критично для монтажа», затем характеристики; по умолчанию —
    только различия, совпадающее — сноской под таблицей. Ниже 1024 px таблица листается
    вбок, колонка параметров остаётся на месте.
--}}
@php
    use App\Enums\Availability;

    $count = $products->count();
    $groups = $table->groups($all);
    $summary = $table->sameSummary();
    $sameCount = $table->total() - $table->differences();
    $rowsLink = fn (int $rows): string => trans_choice('shop.compare.all_rows', $rows, ['count' => $rows]);
    $columns = match ($count) {
        1 => 'min-w-[364px] md:min-w-[412px]',
        2 => 'min-w-[596px] md:min-w-[644px]',
        3 => 'min-w-[828px] md:min-w-[876px]',
        default => 'min-w-[1060px] md:min-w-[1108px]',
    };
    $param = 'sticky left-0 z-10 w-[132px] border-r border-line-soft bg-bg md:w-[180px]';
    $verdict = match (true) {
        $count < 2 => __('shop.compare.one_model'),
        $table->differences() === 0 => __('shop.compare.no_differences'),
        default => trans_choice('shop.compare.differs', $table->differences(), ['count' => $table->differences(), 'total' => $table->total()]),
    };
@endphp

<x-layouts.app :title="__('shop.compare.title')" noindex>
    <x-catalog.breadcrumbs :category="$category" :current="__('shop.compare.header')" />

    @if ($count === 0)
        <h1 class="mt-3 text-xl font-bold md:text-2xl">{{ __('shop.compare.title') }}</h1>

        <div class="mt-5 flex max-w-prose flex-col items-start gap-4 rounded-card border border-line bg-surface p-6">
            <p class="text-base text-steel-500">{{ __('shop.compare.empty') }}</p>
            <x-ui.button :href="route('catalog')">{{ __('shop.compare.to_catalog') }}</x-ui.button>
        </div>
    @else
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <h1 class="text-xl font-bold md:text-2xl">
                    {{ $category ? __('shop.compare.title_in', ['category' => $category->name]) : __('shop.compare.title') }}
                </h1>
                <p class="text-base text-steel-500 tabular">{{ trans_choice('shop.catalog.models', $count, ['count' => $count]) }} · {{ $verdict }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($table->differences() > 0 && $sameCount > 0)
                    <nav aria-label="{{ __('shop.compare.rows') }}">
                        <ul class="flex overflow-hidden rounded-control border border-line bg-surface">
                            @foreach ([false => __('shop.compare.only_differences'), true => $rowsLink($table->total())] as $showAll => $label)
                                <li @class(['border-l border-line' => $showAll])>
                                    <a
                                        href="{{ $showAll ? route('compare', ['all' => 1]) : route('compare') }}"
                                        @if ($all === (bool) $showAll) aria-current="true" @endif
                                        @class([
                                            'flex h-control items-center px-3.5 text-sm leading-none font-medium whitespace-nowrap transition-colors duration-150 ease-out focus-visible:-outline-offset-2',
                                            'bg-slate' => $all === (bool) $showAll,
                                            'hover:bg-bg' => $all !== (bool) $showAll,
                                        ])
                                    >{{ $label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                <form method="post" action="{{ route('compare.clear') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="neutral" class="text-sm">{{ __('shop.compare.clear') }}</x-ui.button>
                </form>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto rounded-card border border-line bg-surface">
            <table class="{{ $columns }} w-full table-fixed border-separate border-spacing-0 text-base [&_tbody_tr:last-child>*]:border-b-0">
                <caption class="sr-only">{{ __('shop.compare.table') }}</caption>

                <thead>
                    <tr>
                        <th scope="col" class="{{ $param }} border-b border-b-line p-3 text-left align-bottom font-normal md:p-4">
                            <span class="block text-md font-semibold">{{ __('shop.compare.model') }}</span>
                            <span class="mt-1.5 block text-sm text-steel-500">{{ __('shop.compare.scroll_hint') }}</span>
                        </th>

                        @foreach ($products as $product)
                            @php($price = $prices[$product->id] ?? null)
                            <th scope="col" class="h-px border-r border-b border-r-line-soft border-b-line p-3 text-left align-top font-normal last:border-r-0 md:p-4">
                                <div class="flex h-full flex-col gap-2.5">
                                    <a href="{{ route('product', $product) }}" tabindex="-1" aria-hidden="true">
                                        <x-ui.product-image :product="$product" ratio="aspect-[4/3] rounded-card border border-line-soft" icon-class="size-11" />
                                    </a>

                                    <p class="flex items-baseline justify-between gap-2 text-sm font-medium">
                                        <span class="truncate">{{ $product->brand?->name }}</span>
                                        @if ($product->sku)
                                            <x-ui.data :label="__('shop.product.sku')" class="shrink-0">{{ $product->sku }}</x-ui.data>
                                        @endif
                                    </p>

                                    <a href="{{ route('product', $product) }}" class="line-clamp-4 text-md font-semibold transition-colors duration-150 ease-out hover:text-accent-ink md:text-lg">{{ $product->name }}</a>

                                    <x-ui.price :price="$price" />
                                    <x-ui.availability :availability="$product->availability" class="self-start" />

                                    <div class="mt-auto flex flex-col gap-1 pt-1.5">
                                        @if ($product->availability === Availability::Discontinued)
                                            <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.find_analog') }}</x-ui.button>
                                        @elseif ($price === null)
                                            <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.request_price') }}</x-ui.button>
                                        @else
                                            <x-cart.add :product="$product" button-class="w-full" />
                                        @endif

                                        <form method="post" action="{{ route('compare.remove', $product->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="tap-target inline-flex h-8 w-full items-center justify-center text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">
                                                {{ __('shop.compare.remove') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($groups as $group => $rows)
                        <tr>
                            <th scope="colgroup" colspan="{{ $count + 1 }}" class="border-b border-accent-line bg-accent-soft px-3 py-2.5 text-left text-base font-semibold md:px-4">
                                <span class="sticky left-3 md:left-4">{{ __('shop.compare.group_'.$group) }}</span>
                            </th>
                        </tr>

                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row" class="{{ $param }} border-b px-3 py-3 text-left align-top font-normal text-steel-500 md:px-4">{{ $row['label'] }}</th>

                                @foreach ($row['values'] as $value)
                                    <td @class([
                                        'border-r border-b border-line-soft px-3 py-3 align-top wrap-break-word last:border-r-0 md:px-4',
                                        'font-mono text-sm' => $row['mono'],
                                        'font-medium' => ! $row['same'],
                                        'text-steel-500' => $row['same'] && $count > 1,
                                    ])>{{ $value ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (! $all && $sameCount > 0)
            <p class="mt-3 max-w-prose text-sm text-steel-500">
                {{ __('shop.compare.same', ['list' => $summary['list']]) }}@if ($summary['more'] > 0) — {{ trans_choice('shop.compare.same_more', $summary['more'], ['count' => $summary['more']]) }}@endif.
                <a href="{{ route('compare', ['all' => 1]) }}" class="font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">
                    {{ trans_choice('shop.compare.show_all', $table->total(), ['count' => $table->total()]) }}
                </a>
            </p>
        @endif
    @endif
</x-layouts.app>
