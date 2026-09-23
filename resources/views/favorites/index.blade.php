{{--
    Избранное (ТЗ §8: /favorites, §11). Вошедшему клиенту — вкладка кабинета, гостю — своя
    страница с подсказкой войти: его список живёт, пока жива сессия. Карточки — те же, что
    в листинге, с покупкой, сравнением и «Убрать из избранного». Пустое избранное — с путём
    в каталог (бриф, экран 18). Страница служебная, в поиск не попадает.
--}}
<x-layouts.app :title="__('shop.favorites.title')" noindex>
    @if ($user !== null)
        <x-account.frame :user="$user" :company="$company" active="favorites" :current="__('shop.favorites.title')">
            @include('favorites.list')
        </x-account.frame>
    @else
        <x-catalog.breadcrumbs :current="__('shop.favorites.title')" />

        <div class="mt-3 flex flex-col gap-5">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-xl font-bold md:text-2xl">{{ __('shop.favorites.title') }}</h1>
                <p class="text-base text-steel-500">
                    {{ __('shop.favorites.guest_hint') }}
                    <a href="{{ route('login') }}" class="font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.favorites.login') }}</a>
                </p>
            </div>

            @include('favorites.list')
        </div>
    @endif
</x-layouts.app>
