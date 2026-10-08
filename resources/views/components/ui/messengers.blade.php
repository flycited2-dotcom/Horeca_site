@props(['links' => [], 'variant' => 'buttons'])

{{--
    Мессенджеры магазина (ТЗ §5.5, App\Support\Messengers): Telegram и MAX из «Настроек».
    «strip» — короткие ссылки в служебной полосе и подвале, «buttons» — кнопки по 44 px
    на «Контактах» и в карточке товара. Ссылка открывается в приложении или новой вкладке.
--}}
@if ($links !== [])
    <ul {{ $attributes->class(['flex flex-wrap items-center', 'gap-4' => $variant === 'strip', 'gap-2' => $variant !== 'strip']) }}>
        @foreach ($links as $link)
            <li>
                <a
                    href="{{ $link['href'] }}"
                    target="_blank"
                    rel="noopener"
                    data-messenger="{{ $link['key'] }}"
                    @if ($variant === 'strip') aria-label="{{ __('shop.messengers.write', ['name' => $link['label']]) }}" @endif
                    @class([
                        'inline-flex items-center gap-1.5 font-medium transition-colors duration-150 ease-out',
                        'hover:text-accent-ink' => $variant === 'strip',
                        'h-control rounded-control border border-line bg-surface px-4 text-base hover:border-accent-ink' => $variant !== 'strip',
                    ])
                >
                    @if ($link['key'] === \App\Support\Messengers::TELEGRAM)
                        <svg class="size-4.5 shrink-0 text-accent-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 4 3 11l6 2.5M21 4l-3 16-9-6.5M21 4 9 13.5V19l3-3.5"/>
                        </svg>
                    @else
                        <svg class="size-4.5 shrink-0 text-accent-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 5h16v11H9l-5 4z"/>
                        </svg>
                    @endif
                    <span>{{ $variant === 'strip' ? $link['label'] : __('shop.messengers.write', ['name' => $link['label']]) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
