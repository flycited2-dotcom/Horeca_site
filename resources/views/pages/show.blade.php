{{--
    Статическая страница (ТЗ §5.5, §8): «Доставка», «Оплата», «Гарантия» и другие, которые
    менеджер включил в админке. Текст — Markdown, выводится через App\View\RichText.
    На «Доставке» и «Оплате» заголовки-вопросы с ответами идут в разметку FAQPage (ТЗ §14).
    На «Контактах» под текстом — телефоны, почта, адрес, режим работы и реквизиты из «Настроек».
--}}
<x-layouts.app :meta="$meta">
    <article class="rounded-card border border-line bg-surface p-4 md:p-8">
        <h1 class="text-2xl font-bold md:text-3xl">{{ $page->title }}</h1>

        <div class="rich-text mt-5">{{ \App\View\RichText::html($page->content) }}</div>

        @if ($contacts && ! $contacts->isEmpty())
            <dl class="mt-6 grid gap-5 border-t border-line pt-6 md:grid-cols-2">
                @if ($contacts->phones !== [])
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-steel-500">{{ __('shop.contacts.phones') }}</dt>
                        @foreach ($contacts->phones as $phone)
                            <dd><a href="{{ $phone['href'] }}" class="text-lg font-semibold whitespace-nowrap tabular transition-colors duration-150 ease-out hover:text-accent-ink">{{ $phone['label'] }}</a></dd>
                        @endforeach
                    </div>
                @endif
                @if ($contacts->email)
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-steel-500">{{ __('shop.contacts.email') }}</dt>
                        <dd><a href="mailto:{{ $contacts->email }}" class="text-lg font-semibold transition-colors duration-150 ease-out hover:text-accent-ink">{{ $contacts->email }}</a></dd>
                    </div>
                @endif
                @if ($contacts->address)
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-steel-500">{{ __('shop.contacts.address') }}</dt>
                        <dd class="text-base">{{ $contacts->address }}</dd>
                    </div>
                @endif
                @if ($contacts->schedule)
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-steel-500">{{ __('shop.layout.schedule') }}</dt>
                        <dd class="text-base">{{ $contacts->schedule }}</dd>
                    </div>
                @endif
                @if ($contacts->requisites)
                    <div class="flex flex-col gap-1 md:col-span-2">
                        <dt class="text-sm text-steel-500">{{ __('shop.layout.requisites') }}</dt>
                        <dd class="max-w-prose text-base whitespace-pre-line">{{ $contacts->requisites }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </article>

    @if ($faq)
        <script type="application/ld+json">{!! \App\Support\StructuredData::json($faq) !!}</script>
    @endif
</x-layouts.app>
