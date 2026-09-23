@props(['heading', 'intro' => null, 'level' => 1])

{{--
    Карточка входа, регистрации и восстановления пароля (ТЗ §8; макет — экран 15a): белая,
    до 420 px, отступы 24, заголовок 24/700 и пояснение серым. Внизу — слот «footer» с
    переходом к соседней форме, отделённый чертой. Уровень заголовка — для стайлгайда,
    где своя страница со своим h1.
--}}
<div {{ $attributes->class('mx-auto mt-4 w-full max-w-[420px] md:mt-10') }}>
    <div class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-1.5">
            <{{ 'h'.$level }} class="text-[1.5rem] leading-[1.2] font-bold">{{ $heading }}</{{ 'h'.$level }}>
            @if ($intro)
                <p class="text-base text-steel-500">{{ $intro }}</p>
            @endif
        </div>

        {{ $slot }}

        @isset($footer)
            <div class="flex flex-col gap-1 border-t border-line-soft pt-3.5">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
