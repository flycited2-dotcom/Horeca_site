{{--
    Плашка для клиента, чья заявка на опт ещё на проверке (ТЗ §7): цены пока розничные,
    оптовые откроются после одобрения. Ссылка — на статус заявки.
--}}
<p {{ $attributes->class('rounded-control border border-incoming-line bg-incoming-bg px-3 py-2 text-sm text-incoming') }}>
    {{ __('shop.price.wholesale_pending') }}
    <a href="{{ route('wholesale') }}" class="font-medium underline underline-offset-2">{{ __('shop.price.wholesale_status') }}</a>
</p>
