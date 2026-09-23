@props(['product', 'variant' => 'link'])

{{--
    «В избранное» (ТЗ §8.2, §8.3): на карточке листинга, в строке поиска и в таблице — ссылкой
    с сердцем рядом со «Сравнить», на странице товара — нейтральной кнопкой рядом с «К сравнению»
    (макет, экран 3). Сердце залито, когда товар уже в избранном. Обе формы работают без
    скриптов; скрипт витрины отправляет их без перезагрузки и показывает ту, что
    соответствует новому состоянию.
--}}
@inject('favorites', 'App\Services\Favorites\FavoriteList')

@php
    $favorite = $favorites->contains($product->id, request()->user());
    $link = 'tap-target inline-flex h-8 w-full items-center justify-center gap-1.5 text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark';
@endphp

<div data-favorite="{{ $product->id }}" {{ $attributes }}>
    @foreach ([false, true] as $remove)
        <form
            method="post"
            action="{{ route($remove ? 'favorites.remove' : 'favorites.add', $product->id) }}"
            data-favorite-form
            @if ($remove !== $favorite) hidden @endif
        >
            @csrf
            @if ($remove)
                @method('DELETE')
            @endif
            @if ($variant === 'button')
                <x-ui.button type="submit" variant="neutral" class="w-full text-sm">
                    <x-favorites.heart :filled="$remove" class="size-4 text-accent-ink" />
                    {{ __($remove ? 'shop.favorites.remove' : 'shop.favorites.add') }}
                </x-ui.button>
            @else
                <button type="submit" class="{{ $link }}">
                    <x-favorites.heart :filled="$remove" class="size-3.5" />
                    {{ __($remove ? 'shop.favorites.remove' : 'shop.favorites.add') }}
                </button>
            @endif
        </form>
    @endforeach
</div>
