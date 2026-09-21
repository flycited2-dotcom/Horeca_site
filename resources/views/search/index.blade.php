<x-layouts.app :title="$query !== '' ? __('shop.search.heading', ['query' => $query]) : __('shop.search.title')">
    <livewire:search-listing :query="$query" />
</x-layouts.app>
