{{--
    Восстановление пароля (ТЗ §8; макет — экран 15a, блок «Восстановление пароля»): почта и
    одинаковый ответ, есть такой кабинет или нет. Нет доступа к почте — звонок менеджеру.
--}}
<x-layouts.app :title="__('shop.auth.forgot.title')" noindex>
    <x-auth.card :heading="__('shop.auth.forgot.heading')" :intro="__('shop.auth.forgot.intro')">
        @if (session('status'))
            <p role="status" class="rounded-control border border-stock-line bg-stock-bg px-3 py-2.5 text-base text-stock-text">{{ session('status') }}</p>
        @endif

        <form method="post" action="{{ route('password.email') }}" novalidate class="flex flex-col gap-4">
            @csrf

            <x-ui.input name="email" type="email" :label="__('shop.auth.forgot.email')" :value="old('email')" autocomplete="email" maxlength="150" required autofocus />

            <x-ui.button type="submit" class="w-full">{{ __('shop.auth.forgot.submit') }}</x-ui.button>
        </form>

        <x-slot:footer>
            <p class="text-sm text-steel-500">{{ __('shop.auth.forgot.no_access') }}</p>
            <p class="text-sm text-steel-500">
                {{ __('shop.auth.forgot.back') }}
                <a href="{{ route('login') }}" class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.register.login_link') }}</a>
            </p>
        </x-slot:footer>
    </x-auth.card>
</x-layouts.app>
