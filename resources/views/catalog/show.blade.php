<x-layouts.app :title="$category->meta_title ?: $category->name" :description="$category->meta_description">
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
            <div class="max-w-prose text-base">{!! nl2br(e($category->seo_text)) !!}</div>
        </section>
    @endif
</x-layouts.app>
