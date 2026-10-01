<x-layouts.app :meta="$meta">
    <x-catalog.breadcrumbs :category="$category" />

    <div class="mt-3">
        <livewire:category-listing
            :category="$category"
            :title="$category->h1 ?: $category->name"
            :subcategories="$subcategories"
        />
    </div>

    @if ($category->seo_text)
        <section class="mt-10 rounded-card border border-line bg-surface p-6">
            {{-- Текст из админки — Markdown (ТЗ §5.5): заголовки и списки, как на статических страницах. --}}
            <div class="rich-text max-w-prose text-base">{{ \App\View\RichText::html($category->seo_text) }}</div>
        </section>
    @endif
</x-layouts.app>
