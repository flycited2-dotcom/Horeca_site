@props(['text' => '', 'href' => null, 'link' => null])

{{--
    Уведомление об итоге действия («… — в сравнении, 2 из 4. Открыть сравнение»): всплывает
    внизу экрана стеклянной панелью с холодным свечением (gl-toast). Без скриптов приходит с
    перезагрузкой страницы, со скриптами — собирается из этого же шаблона. Закрыть его можно только
    со скриптами.
--}}
<div data-notice-item {{ $attributes->class('gl-toast pointer-events-auto flex w-full max-w-md items-center gap-3 py-1.5 pr-1.5 pl-4 text-base') }}>
    <span data-notice-text class="min-w-0 flex-1 py-1.5">{{ $text }}</span>
    <a
        data-notice-link
        href="{{ $href ?? '#' }}"
        @if (! $href) hidden @endif
        class="shrink-0 font-bold whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
    >{{ $link }}</a>
    <button
        type="button"
        data-notice-close
        class="requires-js flex size-control shrink-0 items-center justify-center rounded-full text-steel-500 transition-colors duration-150 ease-out hover:bg-white/10 hover:text-ink"
    >
        <span class="sr-only">{{ __('shop.compare.close') }}</span>
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>
