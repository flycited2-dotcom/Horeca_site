{{-- Скелетон карточки (ТЗ §9): те же блоки, пока листинг грузится. --}}
<div {{ $attributes->class('flex h-full flex-col gap-2.5 rounded-card border border-line bg-surface p-4') }} aria-hidden="true">
    <div class="skeleton aspect-[4/3] w-full"></div>
    <div class="skeleton h-3.5 w-1/2"></div>
    <div class="skeleton h-5 w-full"></div>
    <div class="skeleton h-3.5 w-2/3"></div>
    <div class="mt-auto flex flex-col gap-2 pt-2">
        <div class="skeleton h-7 w-1/2"></div>
        <div class="skeleton h-7 w-1/3"></div>
        <div class="skeleton h-control w-full"></div>
    </div>
</div>
