@props([
    'id',
    'type',
    'product' => null,
    'message' => null,
    'messageLabel' => null,
    'submit' => null,
])

{{--
    Форма короткой заявки (ТЗ §5: leads): имя, телефон с маской, сообщение, согласие на
    обработку ПДн. Обычная POST-форма на /leads — работает без скриптов; скрипт витрины
    отправляет её без перезагрузки и показывает ошибки у полей (data-field-error).
    Товар — скрытым полем; в общем окне «Запросить цену» его подставляет кнопка. Ошибки
    других форм страницы (оформления) полям не передаются — error="".
--}}
@php
    use App\Enums\LeadType;
    use App\Http\Requests\LeadRequest;

    $type = $type instanceof LeadType ? $type : LeadType::from($type);
    $error = 'text-sm text-danger-text';
@endphp

<form method="post" action="{{ route('leads.store') }}" novalidate data-lead-form {{ $attributes->class('flex flex-col gap-3.5') }}>
    @csrf
    <input type="hidden" name="type" value="{{ $type->value }}">
    <input type="hidden" name="product_id" value="{{ $product?->id }}" data-lead-product-field>
    <input type="hidden" name="started" value="{{ LeadRequest::openedAt() }}">
    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
        <label for="{{ $id }}-website">{{ __('shop.checkout.honeypot') }}</label>
        <input type="text" id="{{ $id }}-website" name="{{ LeadRequest::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
    </div>

    <p data-field-error="form" hidden class="{{ $error }}"></p>

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
        <div class="flex flex-col gap-1.5">
            <x-ui.input :id="$id.'-name'" name="name" error="" :label="__('shop.leads.fields.name')" autocomplete="name" maxlength="150" />
            <p data-field-error="name" hidden class="{{ $error }}"></p>
        </div>
        <div class="flex flex-col gap-1.5">
            <x-ui.input :id="$id.'-phone'" name="phone" error="" type="tel" :label="__('shop.leads.fields.phone')" placeholder="+7 ___ ___-__-__" inputmode="tel" autocomplete="tel" data-phone-mask mono />
            <p data-field-error="phone" hidden class="{{ $error }}"></p>
        </div>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="{{ $id }}-message" class="text-sm leading-[1.4] font-medium text-steel-500">{{ $messageLabel ?? __('shop.leads.fields.message') }}</label>
        <textarea
            id="{{ $id }}-message"
            name="message"
            rows="3"
            maxlength="2000"
            class="w-full rounded-control border border-line bg-surface px-3 py-2.5 text-base text-ink placeholder:text-steel-500 focus:border-accent"
        >{{ $message }}</textarea>
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="{{ $id }}-consent" class="flex cursor-pointer items-start gap-2.5 text-sm">
            <input type="checkbox" id="{{ $id }}-consent" name="consent" value="1" class="mt-0.5 size-4.5 shrink-0 accent-accent-ink">
            <span>
                {{ __('shop.checkout.consent_before') }}
                <a href="{{ url('soglasie-na-obrabotku-personalnyh-dannyh') }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.checkout.consent_link') }}</a>
            </span>
        </label>
        <p data-field-error="consent" hidden class="{{ $error }}"></p>
    </div>

    <x-ui.button type="submit" class="w-full sm:w-auto sm:self-start">{{ $submit ?? __('shop.leads.submit.'.$type->value) }}</x-ui.button>
</form>
