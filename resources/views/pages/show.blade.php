{{--
    Статическая страница (ТЗ §5.5, §8): «Доставка», «Оплата», «Гарантия» и другие, которые
    менеджер включил в админке. Текст — Markdown, выводится через App\View\RichText.
--}}
<x-layouts.app :title="$page->meta_title ?: $page->title" :description="$page->meta_description">
    <article class="rounded-card border border-line bg-surface p-4 md:p-8">
        <h1 class="text-2xl font-bold md:text-3xl">{{ $page->title }}</h1>

        <div class="rich-text mt-5">{{ \App\View\RichText::html($page->content) }}</div>
    </article>
</x-layouts.app>
