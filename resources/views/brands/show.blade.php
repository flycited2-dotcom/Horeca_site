<x-layouts.app :title="__('shop.brands.meta_title', ['brand' => $brand->name])" :description="$brand->description ? \Illuminate\Support\Str::limit(trim($brand->description), 160) : null">
    <x-catalog.breadcrumbs :brand="$brand" />

    <div class="mt-3">
        <livewire:brand-listing :brand="$brand" />
    </div>

    @if (filled($brand->description))
        <section class="mt-10 rounded-card border border-line bg-surface p-6">
            <div class="max-w-prose text-base whitespace-pre-line">{{ trim($brand->description) }}</div>
        </section>
    @endif
</x-layouts.app>
