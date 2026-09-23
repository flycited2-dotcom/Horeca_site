@props([
    'id' => 'consent',
    'checked' => false,
    'error' => null,
])

{{--
    Согласие на обработку персональных данных (ТЗ §15.10): у каждой формы — ссылки на
    согласие и политику конфиденциальности. Настоящий чекбокс «consent», работает без
    скриптов. С ошибкой — связь с текстом ошибки, который выводит форма (id «<id>-error»).
    Остальные атрибуты уходят на чекбокс.
--}}
<label for="{{ $id }}" class="flex cursor-pointer items-start gap-2.5 text-sm">
    <input
        type="checkbox"
        id="{{ $id }}"
        name="consent"
        value="1"
        @checked($checked)
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class('mt-0.5 size-4.5 shrink-0 accent-accent-ink') }}
    >
    <span>
        {{ __('shop.checkout.consent_before') }}
        <a href="{{ url('soglasie-na-obrabotku-personalnyh-dannyh') }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.checkout.consent_link') }}</a>
        {{ __('shop.checkout.consent_and') }}
        <a href="{{ url('politika-konfidencialnosti') }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.checkout.privacy_link') }}</a>
    </span>
</label>
