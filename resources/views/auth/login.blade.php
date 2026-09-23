{{--
    Вход покупателя (ТЗ §8; макет — экран 15a): почта или телефон, пароль, «Запомнить» и
    «Забыли пароль?». Ошибка — у поля и говорит, сколько попыток осталось. «Вход по SMS»
    из макета — после запуска (§21).
--}}
<x-layouts.app :title="__('shop.auth.login.title')" noindex>
    <x-auth.card :heading="__('shop.auth.login.heading')" :intro="__('shop.auth.login.intro')">
        <form method="post" action="{{ route('login.store') }}" novalidate class="flex flex-col gap-4">
            @csrf

            <x-ui.input
                name="login"
                :label="__('shop.auth.login.login')"
                :value="old('login')"
                autocomplete="username"
                maxlength="150"
                required
                autofocus
            />
            <x-ui.input
                name="password"
                type="password"
                :label="__('shop.auth.login.password')"
                autocomplete="current-password"
                required
            />

            <div class="flex flex-wrap items-center justify-between gap-x-3">
                <x-ui.toggle name="remember" :checked="(bool) old('remember')">{{ __('shop.auth.login.remember') }}</x-ui.toggle>
                <a href="{{ route('password.request') }}" class="text-base text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.login.forgot') }}</a>
            </div>

            <x-ui.button type="submit" class="w-full">{{ __('shop.auth.login.submit') }}</x-ui.button>
        </form>

        <x-slot:footer>
            <p class="text-base font-medium">{{ __('shop.auth.login.no_account') }}</p>
            <p class="text-sm text-steel-500">
                {{ __('shop.auth.login.no_account_text') }}
                <a href="{{ route('register') }}" class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.login.register_link') }}</a>
            </p>
        </x-slot:footer>
    </x-auth.card>
</x-layouts.app>
