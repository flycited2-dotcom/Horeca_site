@props(['title', 'note' => null, 'tone' => 'surface'])

{{--
    Блок стайлгайда (макет, экран 9): белая плашка с рамкой, слейт для правил или полотно
    страницы — чтобы карточки лежали на том же фоне, что в каталоге.
--}}
<section {{ $attributes->class([
    'flex flex-col gap-4 rounded-card p-4 md:p-6',
    'border border-line bg-surface' => $tone === 'surface',
    'bg-slate' => $tone === 'slate',
    'border border-line bg-bg' => $tone === 'canvas',
]) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 class="text-xl font-semibold">{{ $title }}</h2>
        @if ($note)
            <p class="text-sm text-steel-500">{{ $note }}</p>
        @endif
    </div>

    {{ $slot }}
</section>
