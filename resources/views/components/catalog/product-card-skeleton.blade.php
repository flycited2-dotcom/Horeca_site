{{-- Скелетон карточки (ТЗ §9, облик «Свечение»): те же блоки, пока листинг грузится. --}}
<div {{ $attributes->class('gl-card gl-card--quiet flex h-full flex-col gap-2.5 p-3') }} aria-hidden="true">
    <div class="skeleton aspect-[4/3] w-full"></div>
    <div class="flex flex-col gap-2.5 px-1.5 pt-2.5">
        <div class="skeleton h-3.5 w-1/2"></div>
        <div class="skeleton h-5 w-full"></div>
        <div class="skeleton h-3.5 w-2/3"></div>
    </div>
    <div class="mt-auto flex items-center justify-between gap-3 border-t border-white/10 px-1.5 pt-4">
        <div class="skeleton h-7 w-1/2"></div>
        <div class="skeleton size-control"></div>
    </div>
</div>
