@props(['text' => '', 'href' => null, 'link' => null])

{{--
    Уведомление об итоге действия («… — в сравнении, 2 из 4. Открыть сравнение»): всплывает
    внизу экрана. Без скриптов приходит с перезагрузкой страницы, со скриптами — собирается
    из этого же шаблона. Закрыть его можно только со скриптами.
--}}
<div data-notice-item {{ $attributes->class('pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-card border border-line bg-surface py-1.5 pr-1.5 pl-4 text-base shadow-raised') }}>
    <span data-notice-text class="min-w-0 flex-1 py-1.5">{{ $text }}</span>
    <a
        data-notice-link
        href="{{ $href ?? '#' }}"
        @if (! $href) hidden @endif
        class="shrink-0 font-medium whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
    >{{ $link }}</a>
    <button
        type="button"
        data-notice-close
        class="requires-js flex size-control shrink-0 items-center justify-center rounded-control text-steel-500 transition-colors duration-150 ease-out hover:text-ink"
    >
        <span class="sr-only">{{ __('shop.compare.close') }}</span>
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>
