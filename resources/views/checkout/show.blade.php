{{--
    Оформление заявки (ТЗ §10.2, макет — экран 12): один экран без шагов — контакты,
    получение, оплата, комментарий; справа липкая сводка с согласием и кнопкой. Поля
    юрлица и доставки раскрываются по выбору без скриптов (group-has; в id — дефисы:
    подчёркивание в классе Tailwind читается как пробел). Кнопка активна
    всегда (§10.2): ошибки после отправки — у полей и списком в сводке со ссылками.
    Подстановка реквизитов по ИНН из ЕГРЮЛ — после запуска (§21).
--}}
@php
    use App\Enums\DeliveryMethod;
    use App\Support\Typography;

    $company = $user?->hasApprovedCompany() ? $user->company : null;
    $legal = (bool) old('is_legal_entity', $company !== null);
    $delivery = old('delivery_method', DeliveryMethod::Pickup->value);
    $payment = old('payment_method', $payments[0]->value);
    $fieldIds = [
        'form' => 'checkout-form', 'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'inn' => 'inn',
        'company_name' => 'company_name', 'delivery_method' => 'delivery-pickup', 'delivery_city' => 'delivery_city',
        'tk_name' => 'tk_name', 'delivery_address' => 'delivery_address', 'payment_method' => 'payment-'.str_replace('_', '-', $payment),
        'comment' => 'comment', 'consent' => 'consent',
    ];
    $section = 'flex flex-col gap-4 rounded-card border border-line bg-surface p-4 md:p-6';
    $select = 'h-control w-full rounded-control border bg-surface px-3 text-base text-ink transition-colors duration-150 ease-out focus:border-accent';
@endphp

<x-layouts.app :title="__('shop.checkout.title')" noindex>
    <x-catalog.breadcrumbs :current="__('shop.checkout.title')" />

    <h1 class="mt-3 text-xl font-bold md:text-2xl">{{ __('shop.checkout.title') }}</h1>

    <form id="checkout-form" method="post" action="{{ route('checkout.store') }}" novalidate class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="started" value="{{ $started }}">

        {{-- Ловушка для роботов: людям не видна и в порядок фокуса не попадает. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label for="checkout-website">{{ __('shop.checkout.honeypot') }}</label>
            <input type="text" id="checkout-website" name="{{ \App\Http\Requests\CheckoutRequest::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
        </div>

        <div class="flex min-w-0 flex-col gap-4">
            <section class="{{ $section }} group/legal" aria-labelledby="checkout-contacts">
                <h2 id="checkout-contacts" class="text-lg font-semibold">{{ __('shop.checkout.contacts') }}</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('shop.checkout.fields.name')" :value="old('name', $user?->name)" autocomplete="name" maxlength="150" />
                    <x-ui.input
                        name="phone"
                        type="tel"
                        :label="__('shop.checkout.fields.phone')"
                        :value="old('phone', $user?->phone)"
                        placeholder="+7 ___ ___-__-__"
                        inputmode="tel"
                        autocomplete="tel"
                        data-phone-mask
                        mono
                    />
                    <x-ui.input
                        name="email"
                        type="email"
                        :label="__('shop.checkout.fields.email')"
                        :value="old('email', $user?->email)"
                        :hint="__('shop.checkout.email_hint')"
                        autocomplete="email"
                        maxlength="150"
                        class="md:col-span-2"
                    />
                </div>

                <x-ui.toggle name="is_legal_entity" id="is-legal" :checked="$legal">{{ __('shop.checkout.legal_entity') }}</x-ui.toggle>

                <div class="hidden grid-cols-1 gap-4 group-has-[#is-legal:checked]/legal:grid md:grid-cols-2">
                    <x-ui.input name="inn" :label="__('shop.checkout.fields.inn')" :value="old('inn', $company?->inn)" inputmode="numeric" autocomplete="off" maxlength="12" mono />
                    <x-ui.input name="company_name" :label="__('shop.checkout.fields.company_name')" :value="old('company_name', $company?->legal_name)" autocomplete="organization" maxlength="255" />
                </div>
            </section>

            <section class="{{ $section }} group/delivery" aria-labelledby="checkout-delivery">
                <h2 id="checkout-delivery" class="text-lg font-semibold">{{ __('shop.checkout.delivery') }}</h2>

                <div role="radiogroup" aria-labelledby="checkout-delivery" class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    @foreach (DeliveryMethod::cases() as $method)
                        <x-ui.choice
                            name="delivery_method"
                            :value="$method->value"
                            :id="'delivery-'.str_replace('_', '-', $method->value)"
                            :title="$method->getLabel()"
                            :description="$method === DeliveryMethod::Pickup && $pickup ? $pickup : __('shop.checkout.delivery_notes.'.$method->value)"
                            :checked="$delivery === $method->value"
                        />
                    @endforeach
                </div>
                @error('delivery_method')
                    <p class="text-sm text-danger-text">{{ $message }}</p>
                @enderror

                <div class="hidden grid-cols-1 gap-4 group-has-[#delivery-transport-company:checked]/delivery:grid md:grid-cols-2">
                    <x-ui.input name="delivery_city" :label="__('shop.checkout.fields.delivery_city')" :value="old('delivery_city')" autocomplete="address-level2" maxlength="150" />

                    <div class="flex flex-col gap-1.5">
                        <label for="tk_name" class="text-sm leading-[1.4] font-medium text-steel-500">{{ __('shop.checkout.fields.tk_name') }}</label>
                        <select id="tk_name" name="tk_name" @error('tk_name') aria-invalid="true" aria-describedby="tk_name-error" @enderror @class([$select, 'border-danger' => $errors->has('tk_name'), 'border-line' => ! $errors->has('tk_name')])>
                            @foreach ($carriers as $carrier)
                                <option value="{{ $carrier }}" @selected(old('tk_name') === $carrier)>{{ $carrier }}</option>
                            @endforeach
                        </select>
                        @error('tk_name')
                            <p id="tk_name-error" class="text-sm text-danger-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="hidden group-has-[#delivery-courier-city:checked]/delivery:block">
                    <x-ui.input name="delivery_address" :label="__('shop.checkout.fields.delivery_address')" :value="old('delivery_address')" autocomplete="street-address" maxlength="500" />
                </div>
            </section>

            <section class="{{ $section }}" aria-labelledby="checkout-payment">
                <h2 id="checkout-payment" class="text-lg font-semibold">{{ __('shop.checkout.payment') }}</h2>

                <div role="radiogroup" aria-labelledby="checkout-payment" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($payments as $method)
                        <x-ui.choice
                            name="payment_method"
                            :value="$method->value"
                            :id="'payment-'.str_replace('_', '-', $method->value)"
                            :title="__('shop.checkout.payment_titles.'.$method->value)"
                            :description="__('shop.checkout.payment_notes.'.$method->value)"
                            :checked="$payment === $method->value"
                        />
                    @endforeach
                </div>
            </section>

            <section class="{{ $section }}" aria-labelledby="checkout-comment">
                <h2 id="checkout-comment" class="text-lg font-semibold">
                    <label for="comment">{{ __('shop.checkout.fields.comment') }}</label>
                </h2>
                <textarea
                    id="comment"
                    name="comment"
                    rows="3"
                    maxlength="2000"
                    placeholder="{{ __('shop.checkout.comment_placeholder') }}"
                    class="w-full rounded-control border border-line bg-surface px-3 py-2.5 text-base text-ink placeholder:text-steel-500 focus:border-accent"
                >{{ old('comment') }}</textarea>
            </section>
        </div>

        <aside class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 md:p-5 lg:sticky lg:top-21" aria-labelledby="checkout-summary">
            <h2 id="checkout-summary" class="text-lg font-semibold">{{ __('shop.checkout.summary') }}</h2>

            @if ($errors->any())
                <div role="alert" class="flex flex-col gap-1.5 rounded-card border border-danger px-3.5 py-3">
                    <p class="text-base font-semibold text-danger-text">{{ trans_choice('shop.checkout.errors.left', count($errors->keys()), ['count' => count($errors->keys())]) }}</p>
                    <ul class="flex flex-col gap-1 text-sm">
                        @foreach ($errors->keys() as $field)
                            <li>
                                <a href="#{{ $fieldIds[$field] ?? 'checkout-form' }}" class="text-danger-text underline underline-offset-2">{{ $errors->first($field) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <ul class="flex flex-col gap-2 border-b border-line-soft pb-4 text-sm">
                @foreach ($summary->lines as $line)
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0">{{ $line->product->name }} <span class="whitespace-nowrap text-steel-500">× {{ $line->quantity() }}</span></span>
                        <span class="font-medium whitespace-nowrap tabular">{{ Typography::money($line->sum()) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="flex items-baseline justify-between gap-3">
                <span class="text-base font-semibold">{{ __('shop.cart.to_pay') }}</span>
                <span class="text-2xl font-bold whitespace-nowrap tabular">{{ Typography::money($summary->total()) }}</span>
            </div>
            @if ($vat === 'with_vat' || $vat === 'without_vat')
                <p class="-mt-2 text-sm text-steel-500">{{ __('shop.cart.vat.'.$vat) }}</p>
            @endif

            <div class="flex flex-col gap-1.5">
                <label for="consent" class="flex cursor-pointer items-start gap-2.5 text-sm">
                    <input
                        type="checkbox"
                        id="consent"
                        name="consent"
                        value="1"
                        @checked(old('consent'))
                        @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror
                        class="mt-0.5 size-4.5 shrink-0 accent-accent-ink"
                    >
                    <span>
                        {{ __('shop.checkout.consent_before') }}
                        <a href="{{ url('soglasie-na-obrabotku-personalnyh-dannyh') }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.checkout.consent_link') }}</a>
                        {{ __('shop.checkout.consent_and') }}
                        <a href="{{ url('politika-konfidencialnosti') }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.checkout.privacy_link') }}</a>
                    </span>
                </label>
                @error('consent')
                    <p id="consent-error" class="text-sm text-danger-text">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.button type="submit" class="w-full">{{ __('shop.checkout.submit') }}</x-ui.button>
            <p class="text-sm text-steel-500">{{ __('shop.cart.next') }}</p>
            <a href="{{ route('cart') }}" class="self-start text-sm font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.checkout.back_to_cart') }}</a>
        </aside>
    </form>
</x-layouts.app>
