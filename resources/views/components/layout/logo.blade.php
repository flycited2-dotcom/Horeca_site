@props(['name', 'href'])

{{--
    Знак и название магазина (облик «Свечение»): двухцветный квадрат — холодная и горячая половины
    с буквой — и название шрифтом Unbounded. Один и тот же знак в шапке, подвале и на странице
    ошибки; ссылка ведёт на главную, её имя читается как «Название — На главную».
--}}
<a href="{{ $href }}" {{ $attributes->class('gl-logo') }} aria-label="{{ $name }} — {{ __('shop.layout.home') }}">
    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
        <rect width="32" height="32" rx="9" fill="#FF6A13"/>
        <path d="M9 0H23A9 9 0 0 1 29.36 2.64L2.64 29.36A9 9 0 0 1 0 23V9A9 9 0 0 1 9 0Z" fill="#6CC8FF"/>
        <path d="M11 24V9h10.4v3.2h-7V24z" fill="#0B0F13"/>
    </svg>
    {{ $name }}
</a>
