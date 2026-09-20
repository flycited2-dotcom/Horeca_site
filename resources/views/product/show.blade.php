<x-layouts.app :title="$product->meta_title ?: $product->name" :description="$product->meta_description">
    <x-catalog.breadcrumbs :product="$product" />

    <div class="mt-4 flex flex-col gap-8 lg:flex-row">
        <div class="lg:w-140 lg:shrink-0">
            <x-ui.product-image :product="$product" conversion="full" ratio="aspect-4/3" class="border border-line bg-surface" />
        </div>

        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold md:text-3xl">{{ $product->h1 ?: $product->name }}</h1>

            <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                @if ($product->brand)
                    <span class="font-medium">{{ $product->brand->name }}</span>
                @endif
                @if ($product->sku)
                    <x-ui.data>{{ __('shop.product.sku') }}: {{ $product->sku }}</x-ui.data>
                @endif
                @if ($product->supplier_code)
                    <x-ui.data>{{ __('shop.product.supplier_code') }}: {{ $product->supplier_code }}</x-ui.data>
                @endif
            </p>

            <div class="mt-6 rounded-card border border-line bg-surface p-4 lg:sticky lg:top-24">
                <x-ui.price :price="$price" size="page" />

                <div class="mt-3">
                    <x-ui.availability :availability="$product->availability" />
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    @if ($product->availability === \App\Enums\Availability::Discontinued)
                        <p class="text-base text-steel-500">{{ __('shop.product.discontinued_note') }}</p>
                        <x-ui.button variant="secondary">{{ __('shop.product.find_analog') }}</x-ui.button>
                    @elseif ($price === null)
                        <x-ui.button variant="secondary">{{ __('shop.product.request_price') }}</x-ui.button>
                    @else
                        <x-ui.button>{{ __('shop.product.add_to_cart') }}</x-ui.button>

                        @if ($product->availability === \App\Enums\Availability::OnOrder)
                            <p class="text-sm text-steel-500">{{ __('shop.product.on_order_note') }}</p>
                            <x-ui.button variant="neutral">{{ __('shop.product.ask_term') }}</x-ui.button>
                        @endif
                    @endif
                </div>

                @if ($stocks->isNotEmpty())
                    <section class="mt-4 border-t border-line-soft pt-4">
                        <h2 class="text-sm font-medium text-steel-500">{{ __('shop.product.warehouses') }}</h2>

                        <ul class="mt-2 flex flex-col gap-1.5 text-base">
                            @foreach ($stocks as $stock)
                                <li class="flex flex-wrap items-center justify-between gap-2">
                                    <span>{{ $stock->warehouse->name }}</span>
                                    <span class="flex items-center gap-2 text-sm text-steel-500">
                                        {{ $stock->status->getLabel() }}
                                        @if ($stock->warehouse->delivery_days_min && $stock->warehouse->delivery_days_max)
                                            <span class="tabular">{{ __('shop.product.delivery_days', [
                                                'min' => $stock->warehouse->delivery_days_min,
                                                'max' => $stock->warehouse->delivery_days_max,
                                            ]) }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    </div>

    @if (filled($product->description) || $product->attributeValues->isNotEmpty())
        <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
            @if (filled($product->description))
                <section>
                    <h2 class="text-lg font-semibold">{{ __('shop.product.description') }}</h2>
                    <div class="mt-3 max-w-prose text-base">{!! nl2br(e($product->description)) !!}</div>
                </section>
            @endif

            @if ($product->attributeValues->isNotEmpty())
                <section>
                    <h2 class="text-lg font-semibold">{{ __('shop.product.characteristics') }}</h2>

                    <table class="mt-3 w-full border-collapse text-base">
                        <tbody>
                            @foreach ($product->attributeValues as $attribute)
                                <tr class="border-b border-line-soft">
                                    <th scope="row" class="py-2 pr-4 text-left font-normal text-steel-500">{{ $attribute->name }}</th>
                                    <td class="py-2 text-right font-mono text-sm tabular">
                                        {{ $attribute->pivot->raw_value ?: $attribute->pivot->value_string ?: $attribute->pivot->value_number }}
                                        {{ $attribute->unit }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </div>
    @endif

    @if ($similar->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-semibold">{{ __('shop.home.hits') }}</h2>

            <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($similar as $item)
                    <x-catalog.product-card :product="$item" :price="$similarPrices[$item->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
