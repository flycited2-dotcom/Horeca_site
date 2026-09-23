{{--
    Регистрация покупателя (ТЗ §8, §15): имя, почта, телефон с маской, пароль с повтором,
    согласие на обработку ПДн. Карточка — как у входа (макет, экран 15a). Юрлица, которым
    нужны оптовые цены, подают отдельную заявку на опт (§11).
--}}
@php
    use App\Http\Requests\Auth\RegisterRequest;
@endphp

<x-layouts.app :title="__('shop.auth.register.title')" noindex>
    <x-auth.card :heading="__('shop.auth.register.heading')" :intro="__('shop.auth.register.intro')">
        <form method="post" action="{{ route('register.store') }}" novalidate class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="started" value="{{ $started }}">

            {{-- Ловушка для роботов: людям не видна и в порядок фокуса не попадает. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="register-website">{{ __('shop.checkout.honeypot') }}</label>
                <input type="text" id="register-website" name="{{ RegisterRequest::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
            </div>

            @error('form')
                <p class="text-sm text-danger-text">{{ $message }}</p>
            @enderror

            <x-ui.input name="name" :label="__('shop.auth.register.name')" :value="old('name')" autocomplete="name" maxlength="150" required autofocus />
            <x-ui.input name="email" type="email" :label="__('shop.auth.register.email')" :value="old('email')" autocomplete="email" maxlength="150" required />
            <x-ui.input
                name="phone"
                type="tel"
                :label="__('shop.auth.register.phone')"
                :value="old('phone')"
                placeholder="+7 ___ ___-__-__"
                inputmode="tel"
                autocomplete="tel"
                data-phone-mask
                mono
                required
            />
            <x-ui.input
                name="password"
                type="password"
                :label="__('shop.auth.register.password')"
                :hint="__('shop.auth.register.password_hint')"
                autocomplete="new-password"
                required
            />
            <x-ui.input
                name="password_confirmation"
                type="password"
                :label="__('shop.auth.register.password_confirmation')"
                autocomplete="new-password"
                required
            />

            <div class="flex flex-col gap-1.5">
                <x-ui.consent :checked="(bool) old('consent')" :error="$errors->first('consent')" />
                @error('consent')
                    <p id="consent-error" class="text-sm text-danger-text">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.button type="submit" class="w-full">{{ __('shop.auth.register.submit') }}</x-ui.button>
        </form>

        <x-slot:footer>
            <p class="text-sm text-steel-500">
                {{ __('shop.auth.register.has_account') }}
                <a href="{{ route('login') }}" class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.register.login_link') }}</a>
            </p>
            <p class="text-sm text-steel-500">
                {{ __('shop.auth.login.wholesale_text') }}
                <a href="{{ route('wholesale') }}" class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.login.wholesale_link') }}</a>
            </p>
        </x-slot:footer>
    </x-auth.card>
</x-layouts.app>
