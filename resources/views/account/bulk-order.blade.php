{{--
    Заказ списком (ТЗ §11, сценарий 4 из §2; бриф, экран 17): слева строки или файл XLSX,
    после проверки — превью по каждой строке с результатом: найден (товар и цена клиента),
    несколько совпадений (выбор карточками), не найден, цена по запросу, снят с производства,
    строку не разобрали. Внизу — «Добавить в корзину» и, если есть позиции с ценой по
    запросу, «Запросить цену на эти позиции». Всё работает без скриптов.
--}}
@php
    use App\Actions\BulkOrder\ParseBulkOrderList;
    use App\Services\BulkOrder\BulkOrderStatus;
    use App\Support\Typography;
    use App\View\OrderProgress;

    $max = ParseBulkOrderList::MAX_LINES;
    $tone = fn (BulkOrderStatus $status): string => match ($status) {
        BulkOrderStatus::Found => OrderProgress::OK,
        BulkOrderStatus::Multiple, BulkOrderStatus::PriceOnRequest => OrderProgress::WAIT,
        default => OrderProgress::NEUTRAL,
    };
    $grid = 'md:grid md:grid-cols-[3.5rem_minmax(8rem,12rem)_4.5rem_minmax(0,1fr)] md:gap-x-4';
    $toPrice = $counts[BulkOrderStatus::PriceOnRequest->value] ?? 0;
@endphp

<x-layouts.app :title="__('shop.bulk.title')" noindex>
    <x-account.frame :user="$user" :company="$company" active="bulk" :current="__('shop.bulk.title')">
        <form method="post" action="{{ route('account.bulk-order.check') }}" enctype="multipart/form-data" novalidate aria-labelledby="bulk-heading" class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 md:p-6">
            @csrf
            <div class="flex flex-col gap-1.5">
                <h2 id="bulk-heading" class="text-title font-semibold">{{ __('shop.bulk.title') }}</h2>
                <p class="text-base text-steel-500">{{ __('shop.bulk.intro') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="flex flex-col gap-1.5">
                    <label for="bulk-list" class="text-sm leading-[1.4] font-medium text-steel-500">{{ __('shop.bulk.list_label') }}</label>
                    <textarea
                        id="bulk-list"
                        name="list"
                        rows="8"
                        spellcheck="false"
                        placeholder="{{ __('shop.bulk.list_placeholder') }}"
                        aria-describedby="{{ $errors->has('list') ? 'bulk-list-error' : 'bulk-list-hint' }}"
                        @error('list') aria-invalid="true" @enderror
                        @class([
                            'w-full rounded-control border bg-surface px-3 py-2.5 font-mono text-base text-ink tabular transition-colors duration-150 ease-out placeholder:text-steel-500 focus:border-accent',
                            'border-danger' => $errors->has('list'),
                            'border-line' => ! $errors->has('list'),
                        ])
                    >{{ old('list', $text) }}</textarea>
                    @error('list')
                        <p id="bulk-list-error" class="text-sm text-danger-text">{{ $message }}</p>
                    @else
                        <p id="bulk-list-hint" class="text-sm text-steel-500">{{ __('shop.bulk.list_hint', ['max' => $max]) }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="bulk-file" class="text-sm leading-[1.4] font-medium text-steel-500">{{ __('shop.bulk.file_label') }}</label>
                        <input
                            id="bulk-file"
                            type="file"
                            name="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            aria-describedby="{{ $errors->has('file') ? 'bulk-file-error' : 'bulk-file-hint' }}"
                            @error('file') aria-invalid="true" @enderror
                            class="w-full rounded-control border border-line bg-surface text-base file:mr-3 file:h-control file:border-0 file:border-r file:border-line file:bg-bg file:px-4 file:font-medium file:text-ink"
                        >
                        @error('file')
                            <p id="bulk-file-error" class="text-sm text-danger-text">{{ $message }}</p>
                        @else
                            <p id="bulk-file-hint" class="text-sm text-steel-500">{{ __('shop.bulk.file_hint', ['max' => $max]) }}</p>
                        @enderror
                    </div>
                    <a href="{{ route('account.bulk-order.template') }}" class="self-start text-base font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.bulk.template_link') }}</a>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" class="px-5 max-sm:w-full">{{ __('shop.bulk.check') }}</x-ui.button>
                @if ($matches !== null)
                    <x-ui.button type="submit" form="bulk-reset" variant="neutral" class="max-sm:w-full">{{ __('shop.bulk.new_list') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($matches !== null)
            <form id="bulk-reset" method="post" action="{{ route('account.bulk-order.reset') }}" hidden>
                @csrf
                @method('DELETE')
            </form>
            <form id="bulk-prices" method="post" action="{{ route('account.bulk-order.prices') }}" hidden>
                @csrf
            </form>

            <form method="post" action="{{ route('account.bulk-order.cart') }}" aria-labelledby="bulk-preview" class="flex flex-col gap-4">
                @csrf
                <section class="rounded-card border border-line bg-surface">
                    <div class="flex flex-col gap-2 border-b border-line-soft px-4 py-3.5">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <h2 id="bulk-preview" class="text-title font-semibold">{{ __('shop.bulk.preview') }}</h2>
                            <p class="text-sm text-steel-500 tabular">
                                @if ($source){{ __('shop.bulk.source_file', ['name' => $source]) }} · @endif{{ trans_choice('shop.bulk.lines', count($matches), ['count' => count($matches)]) }}
                            </p>
                        </div>
                        <ul class="flex flex-wrap gap-x-5 gap-y-1.5" aria-label="{{ __('shop.bulk.result') }}">
                            @foreach (BulkOrderStatus::cases() as $status)
                                @if (($counts[$status->value] ?? 0) > 0)
                                    <li><x-account.progress :tone="$tone($status)" :label="$status->label().': '.$counts[$status->value]" /></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>

                    <div aria-hidden="true" class="hidden border-b border-line-soft px-4 py-2.5 text-sm font-medium text-steel-500 {{ $grid }}">
                        <span>{{ __('shop.bulk.row') }}</span>
                        <span>{{ __('shop.bulk.entered') }}</span>
                        <span>{{ __('shop.bulk.qty') }}</span>
                        <span>{{ __('shop.bulk.result') }}</span>
                    </div>

                    <ol class="divide-y divide-line-soft">
                        @foreach ($matches as $match)
                            @php($line = $match->line)
                            <li class="flex flex-col gap-2 px-4 py-3 {{ $grid }}">
                                <span class="text-sm text-steel-500 tabular"><span class="md:sr-only">{{ __('shop.bulk.row') }} </span>{{ $line->row }}</span>
                                <span class="min-w-0 font-mono text-base break-all tabular"><span class="sr-only">{{ __('shop.bulk.entered') }}: </span>{{ $line->sku !== '' ? $line->sku : '—' }}</span>
                                <span class="text-base tabular"><span class="md:sr-only">{{ __('shop.bulk.qty') }}: </span>{{ $line->error === 'qty' ? '—' : Typography::number($line->qty) }}</span>

                                <div class="flex min-w-0 flex-col gap-2">
                                    <x-account.progress :tone="$tone($match->status)" :label="$match->status->label()" />

                                    @if ($match->status === BulkOrderStatus::Invalid)
                                        <p class="text-sm text-danger-text">{{ __('shop.bulk.errors_line.'.$line->error) }}</p>
                                    @elseif ($match->status === BulkOrderStatus::Multiple)
                                        <fieldset class="flex flex-col gap-2">
                                            <legend class="mb-2 text-sm text-steel-500">{{ __('shop.bulk.choose_hint') }}</legend>
                                            @foreach ($match->candidates as $candidate)
                                                @php($price = $match->price($candidate))
                                                @php($details = collect([$candidate->brand?->name, $candidate->sku, $candidate->supplier_code, $price ? Typography::money($price->amount) : __('shop.price.on_request')])->filter()->implode(' · '))
                                                @if ($match->isBuyable($candidate))
                                                    <x-ui.choice
                                                        :name="'choice['.$line->row.']'"
                                                        :value="$candidate->id"
                                                        :title="$candidate->name"
                                                        :description="$details"
                                                        :checked="(string) old('choice.'.$line->row) === (string) $candidate->id"
                                                    />
                                                @else
                                                    <p class="rounded-card border border-line-soft bg-bg px-3 py-2 text-sm text-steel-500">
                                                        {{ $candidate->name }} · {{ $details }} — {{ __('shop.bulk.not_buyable') }}
                                                    </p>
                                                @endif
                                            @endforeach
                                        </fieldset>
                                    @elseif ($product = $match->product())
                                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                            <div class="flex min-w-0 flex-col gap-0.5">
                                                <a href="{{ route('product', $product) }}" class="text-base font-semibold transition-colors duration-150 ease-out hover:text-accent-ink">{{ $product->name }}</a>
                                                <x-ui.data>{{ collect([$product->brand?->name, $product->sku, $product->supplier_code])->filter()->implode(' · ') }}</x-ui.data>
                                            </div>
                                            @if ($match->status === BulkOrderStatus::Found)
                                                <x-ui.price :price="$match->price($product)" class="items-end text-right" />
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <div class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 md:flex-row md:items-start md:justify-between">
                    <div class="flex flex-col gap-1.5">
                        <x-ui.button type="submit" class="px-5 max-sm:w-full">{{ __('shop.bulk.add') }}</x-ui.button>
                        <p class="max-w-prose text-sm text-steel-500">{{ __('shop.bulk.add_hint') }}</p>
                    </div>
                    @if ($toPrice > 0)
                        <div class="flex flex-col gap-1.5 md:items-end">
                            <x-ui.button type="submit" form="bulk-prices" variant="secondary" class="max-sm:w-full">{{ __('shop.bulk.request_prices') }}</x-ui.button>
                            <p class="max-w-prose text-sm text-steel-500 md:text-right">{{ __('shop.bulk.request_hint') }}</p>
                        </div>
                    @endif
                </div>
            </form>
        @endif
    </x-account.frame>
</x-layouts.app>
