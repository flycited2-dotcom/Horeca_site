@props(['product', 'price' => null])

{{--
    Карточка листинга (ТЗ §8.2, облик «Свечение», макет — блок «Готово к отгрузке»; экраны 2 и 14). Стеклянная
    карточка с тонкой светящейся рамкой зоны товара (App\Support\CategoryZone: холод — голубая, жар — оранжевая,
    остальное — серая). С 768 px — вертикальная: фото на светлой плашке с меткой наличия, бренд и артикул,
    название до трёх строк, цена крупно и круглая кнопка корзины (по §6.5), под ними «Сравнить» и «В избранное»
    (§8.5). На телефоне — горизонтальная: плашка 96×96 слева, цена и наличие, без кнопки. Вся карточка — одна
    ссылка на товар (растянутая ссылка названия), кнопки лежат над ней. Тень только на наведении, сдвига нет.
--}}
@php
    use App\Support\CategoryZone;

    $url = route('product', $product);
    $zone = CategoryZone::of($product->category?->icon, $product->category?->name);
@endphp

<article {{ $attributes->class([
    'gl-card gl-pcard',
    'gl-cold' => $zone === CategoryZone::COLD,
    'gl-hot' => $zone === CategoryZone::HOT,
    'gl-neutral' => $zone === CategoryZone::NEUTRAL,
]) }}>
    <div class="gl-pcard__ph">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <x-ui.product-image
                :product="$product"
                plate
                ratio="gl-pcard__plate"
                icon-class="size-10 md:size-16"
                :with-type="true"
            />
        </a>

        <x-ui.availability :availability="$product->availability" tone="plate" class="gl-pcard__stock max-md:hidden" />
    </div>

    <div class="gl-pcard__body">
        <p class="gl-pcard__meta">
            <span class="truncate">{{ $product->brand?->name }}</span>
            @if ($product->sku)
                <x-ui.data :label="__('shop.product.sku')" class="shrink-0">{{ $product->sku }}</x-ui.data>
            @endif
        </p>

        <h3 class="gl-pcard__name line-clamp-3">
            <a href="{{ $url }}">{{ $product->name }}</a>
        </h3>
    </div>

    <div class="gl-pcard__buy">
        <div class="gl-pcard__foot">
            <x-ui.price :price="$price" />
            <x-ui.availability :availability="$product->availability" class="md:hidden" />

            @if ($price !== null)
                <x-cart.add :product="$product" round class="max-md:hidden" />
            @endif
        </div>

        @if ($price === null)
            <x-lead.request-price :product="$product" class="w-full max-md:hidden" />
        @endif

        <div class="gl-pcard__tools max-md:hidden">
            <x-compare.toggle :product="$product" />
            <x-favorites.toggle :product="$product" />
        </div>
    </div>
</article>
