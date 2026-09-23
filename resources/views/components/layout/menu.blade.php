@props(['shell'])

{{--
    Меню каталога на планшете и телефоне (макет, экраны 5, 10 и 14): бургер раскрывает под
    шапкой корневые разделы со счётчиками и служебные страницы. Это <details>: открывается
    и закрывается без скриптов, бургер в открытом меню становится крестиком. Скрипт витрины
    добавляет закрытие по Esc и по нажатию мимо.
--}}
<details data-dismissable {{ $attributes->class('group') }}>
    <summary class="flex size-control cursor-pointer list-none items-center justify-center rounded-control border border-line bg-surface transition-colors duration-150 ease-out hover:border-accent-ink [&::-webkit-details-marker]:hidden">
        <span class="sr-only">{{ __('shop.layout.menu') }}</span>
        <svg class="size-6 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
        <svg class="hidden size-6 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18"/>
        </svg>
    </summary>

    <div class="absolute inset-x-0 top-full z-40 max-h-[calc(100dvh-8rem)] overflow-y-auto border-b border-line bg-surface shadow-raised">
        <nav aria-label="{{ __('shop.layout.sections') }}">
            <ul>
                @foreach ($shell->categories as $category)
                    <li>
                        <a
                            href="{{ route('category', $category['slug']) }}"
                            @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                            @class([
                                'flex min-h-control items-center gap-3 border-b border-line-soft px-3 py-3 transition-colors duration-150 ease-out hover:bg-bg md:px-6',
                                'bg-bg' => $category['id'] === $shell->currentRootId,
                            ])
                        >
                            <x-ui.equipment-icon :icon="$category['icon']" class="size-6 text-steel-500" />
                            <span class="min-w-0 flex-1 text-md font-medium">{{ $category['name'] }}</span>
                            <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <ul class="flex flex-col gap-2 bg-bg p-3 md:px-6">
            <li>
                <a href="{{ route('catalog') }}" class="flex h-control items-center rounded-control border border-line bg-surface px-3 text-base font-medium transition-colors duration-150 ease-out hover:border-accent-ink">
                    {{ __('shop.layout.all_categories') }}
                </a>
            </li>
            @foreach ($shell->stripPages as $page)
                <li>
                    <a href="{{ $shell->pageUrl($page) }}" class="flex h-control items-center rounded-control border border-line bg-surface px-3 text-base font-medium transition-colors duration-150 ease-out hover:border-accent-ink">
                        {{ $page->title }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</details>
