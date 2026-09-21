{{--
    Бренды (ТЗ §8): названия по буквам с числом моделей на витрине — логотипов у поставщика
    нет. Отдельного экрана в макетах нет, страница собрана из токенов и приёмов экрана 9:
    плоская карточка с границей, подписи steel-500, числа табличными цифрами.
--}}
@php
    use App\Support\Typography;
@endphp

<x-layouts.app :title="__('shop.brands.title')">
    <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.brands.title') }}</h1>

    @if ($brandsCount === 0)
        <p class="mt-6 text-steel-500">{{ __('shop.brands.list_empty') }}</p>
    @else
        <p class="mt-2 text-base text-steel-500 tabular">
            {{ trans_choice('shop.brands.count', $brandsCount, ['count' => Typography::number($brandsCount)]) }} · {{ trans_choice('shop.home.positions', $productsCount, ['count' => Typography::number($productsCount)]) }}
        </p>

        <div class="mt-6 columns-2 gap-6 rounded-card border border-line bg-surface p-4 md:columns-3 md:p-6 lg:columns-4 xl:columns-5">
            @foreach ($groups as $letter => $brands)
                <section class="mb-5 break-inside-avoid last:mb-0" aria-labelledby="brands-letter-{{ $loop->index }}">
                    <h2 id="brands-letter-{{ $loop->index }}" class="border-b border-line-soft pb-1.5 text-lg font-semibold text-steel-500">
                        <span aria-hidden="true">{{ $letter }}</span>
                        <span class="sr-only">{{ __('shop.brands.letters', ['letter' => $letter]) }}</span>
                    </h2>

                    <ul class="mt-1 flex flex-col">
                        @foreach ($brands as $brand)
                            <li>
                                <a
                                    href="{{ route('brand', $brand['slug']) }}"
                                    class="flex min-h-control items-center justify-between gap-2 text-base transition-colors duration-150 ease-out hover:text-accent-ink md:min-h-9"
                                >
                                    <span class="min-w-0 wrap-break-word">{{ $brand['name'] }}</span>
                                    <span class="shrink-0 text-sm text-steel-500 tabular">{{ Typography::number($brand['products_count']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
