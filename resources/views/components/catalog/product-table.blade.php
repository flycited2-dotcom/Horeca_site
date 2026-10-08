@props(['products', 'prices'])

{{--
    Листинг списком (ТЗ §8.2, облик «Свечение»): строка таблицы — артикул, наименование с брендом,
    наличие, цена, количество и кнопка. Без свечения: таблицу читают и сравнивают по столбцам.
    Настоящая <table> с заголовками: снабженец сравнивает близкие модели по столбцам
    (ТЗ §9, доступность).
--}}
<div {{ $attributes->class('gl-tablewrap') }}>
    <table class="w-full border-collapse text-left text-base">
        <thead class="border-b border-line text-sm text-steel-500">
            <tr>
                <th scope="col" class="px-4 py-3 font-medium">{{ __('shop.catalog.columns.sku') }}</th>
                <th scope="col" class="px-4 py-3 font-medium">{{ __('shop.catalog.columns.name') }}</th>
                <th scope="col" class="px-4 py-3 font-medium">{{ __('shop.catalog.columns.availability') }}</th>
                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('shop.catalog.columns.price') }}</th>
                <th scope="col" class="px-4 py-3 font-medium"><span class="sr-only">{{ __('shop.catalog.columns.quantity') }}</span></th>
                <th scope="col" class="px-4 py-3 font-medium"><span class="sr-only">{{ __('shop.catalog.columns.action') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                @php($price = $prices[$product->id] ?? null)
                <tr wire:key="row-{{ $product->id }}" class="border-b border-line-soft align-middle last:border-b-0">
                    <td class="px-4 py-3 whitespace-nowrap"><x-ui.data>{{ $product->sku }}</x-ui.data></td>
                    <th scope="row" class="px-4 py-3 text-left font-normal">
                        <a href="{{ route('product', $product) }}" class="text-md font-bold text-white transition-colors duration-150 ease-out hover:text-accent-ink">{{ $product->name }}</a>
                        @if ($product->brand)
                            <span class="mt-0.5 block text-sm text-steel-500">{{ $product->brand->name }}</span>
                        @endif
                    </th>
                    <td class="px-4 py-3 whitespace-nowrap"><x-ui.availability :availability="$product->availability" /></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if ($price === null)
                            <span class="font-bold text-white">{{ __('shop.price.on_request') }}</span>
                        @else
                            <span class="gl-price gl-price--row">{{ \App\Support\Typography::money($price->amount) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($price !== null)
                            <x-ui.counter name="quantity" id="quantity-{{ $product->id }}" form="cart-add-{{ $product->id }}" :label="__('shop.counter.label').': '.$product->name" />
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($price === null)
                            <x-lead.request-price :product="$product" class="w-full whitespace-nowrap" />
                        @else
                            <x-cart.add :product="$product" :form-id="'cart-add-'.$product->id" button-class="w-full whitespace-nowrap gl-btn--calm" />
                        @endif
                        <x-compare.toggle :product="$product" class="mt-1" />
                        <x-favorites.toggle :product="$product" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
