{{--
    Новый пароль по ссылке из письма (ТЗ §8: /reset-password/{token}). Почта приходит в
    ссылке; если ссылка устарела — ошибка у почты и переход к новой ссылке.
--}}
<x-layouts.app :title="__('shop.auth.reset.title')" noindex>
    <x-auth.card :heading="__('shop.auth.reset.heading')" :intro="__('shop.auth.reset.intro')">
        <form method="post" action="{{ route('password.store') }}" novalidate class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <x-ui.input name="email" type="email" :label="__('shop.auth.reset.email')" :value="old('email', $email)" autocomplete="email" maxlength="150" required />
            <x-ui.input
                name="password"
                type="password"
                :label="__('shop.auth.reset.password')"
                :hint="__('shop.auth.register.password_hint')"
                autocomplete="new-password"
                required
                autofocus
            />
            <x-ui.input
                name="password_confirmation"
                type="password"
                :label="__('shop.auth.reset.password_confirmation')"
                autocomplete="new-password"
                required
            />

            <x-ui.button type="submit" class="w-full">{{ __('shop.auth.reset.submit') }}</x-ui.button>
        </form>

        @error('email')
            <x-slot:footer>
                <a href="{{ route('password.request') }}" class="text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.auth.reset.again') }}</a>
            </x-slot:footer>
        @enderror
    </x-auth.card>
</x-layouts.app>
