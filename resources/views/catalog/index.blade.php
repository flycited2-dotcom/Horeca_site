<x-layouts.app :title="__('shop.catalog.title')">
    <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.catalog.title') }}</h1>

    @if ($categories->isEmpty())
        <p class="mt-6 text-steel-500">{{ __('shop.home.catalog_empty') }}</p>
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <section class="rounded-card border border-line bg-surface p-4">
                    <h2 class="flex items-center gap-3 text-lg font-semibold">
                        <x-ui.equipment-icon :icon="$category->icon" class="size-6 text-steel-400" />
                        <a href="{{ route('category', $category) }}" class="transition-colors duration-150 ease-out hover:text-accent-ink">
                            {{ $category->name }}
                        </a>
                    </h2>

                    <p class="mt-1 text-sm tabular text-steel-500">
                        {{ trans_choice('shop.home.products_count', $category->products_count, ['count' => number_format($category->products_count, 0, ',', "\u{00A0}")]) }}
                    </p>

                    @if ($category->children->isNotEmpty())
                        <ul class="mt-3 flex flex-col gap-1.5 border-t border-line-soft pt-3">
                            @foreach ($category->children as $child)
                                <li>
                                    <a href="{{ route('category', $child) }}" class="text-base transition-colors duration-150 ease-out hover:text-accent-ink">
                                        {{ $child->name }}
                                    </a>
                                    <span class="ml-1 text-sm tabular text-steel-500">{{ number_format($child->products_count, 0, ',', "\u{00A0}") }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
