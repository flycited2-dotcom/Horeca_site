@props(['phone' => null, 'email' => null])

{{--
    Контакты магазина внизу письма клиенту (ТЗ §13): телефоны из настроек — ссылками tel:,
    почта — mailto:. Телефоны в настройках — одной строкой через запятую или списком.
    Нет ни одного контакта — блок не выводится.
--}}
@php
    $phones = \App\Support\Phone::links($phone);
    $email = is_string($email) && trim($email) !== '' ? trim($email) : null;
@endphp

@if ($phones !== [] || $email)
    <p style="margin:24px 0 0 0; padding-top:16px; border-top:1px solid #e7ebee; color:#5d686f; font-size:14px;">
        {{ __('notifications.order.questions') }}
        @foreach ($phones as $number)
            <a href="{{ $number['href'] }}" style="color:#0072b0; white-space:nowrap;">{{ $number['label'] }}</a>@if (! $loop->last), @endif
        @endforeach
        @if ($email)
            @if ($phones !== []) · @endif<a href="mailto:{{ $email }}" style="color:#0072b0;">{{ $email }}</a>
        @endif
    </p>
@endif
