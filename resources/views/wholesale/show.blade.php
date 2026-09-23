{{--
    «Оптовым клиентам» (ТЗ §8: /wholesale, §11; макет — экран 15c): как подключиться, выгоды
    со страницы «Оптовикам» (их текст даёт заказчик) и заявка — организация слева, контакт
    справа, доступ ниже. Подстановка реквизитов по ИНН из ЕГРЮЛ — после запуска (§21),
    поэтому название организации вводится вручную. Вошедшему клиенту пароль не нужен.
--}}
@php
    use App\Enums\CompanySegment;
    use App\Http\Requests\WholesaleApplicationRequest;

    $segments = collect(CompanySegment::cases())->mapWithKeys(fn (CompanySegment $segment): array => [$segment->value => $segment->getLabel()])->all();
    $chip = 'rounded-full px-3 py-1.75 text-sm leading-[1.2]';
    $note = 'flex flex-col gap-1.5 rounded-card border border-line-soft bg-bg p-3';
@endphp

<x-layouts.app :title="__('shop.wholesale.title')">
    <x-catalog.breadcrumbs :current="__('shop.wholesale.title')" />

    <div class="mx-auto flex max-w-[820px] flex-col gap-6">
        <div class="mt-3 flex flex-col gap-2">
            <h1 class="text-xl font-bold md:text-2xl">{{ __('shop.wholesale.heading') }}</h1>
            <p class="text-base text-steel-500">{{ __('shop.wholesale.intro') }}</p>
        </div>

        <section aria-labelledby="wholesale-steps" class="flex flex-col gap-3">
            <h2 id="wholesale-steps" class="text-lg font-semibold">{{ __('shop.wholesale.steps_heading') }}</h2>
            <ol class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach (__('shop.wholesale.steps') as $step)
                    <li class="flex flex-col gap-1 rounded-card border border-line bg-surface p-4">
                        <span class="font-mono text-sm text-steel-500 tabular">{{ $loop->iteration }}</span>
                        <span class="text-base font-semibold">{{ $step['title'] }}</span>
                        <span class="text-sm text-steel-500">{{ $step['text'] }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($benefits)
            <section class="rich-text rounded-card border border-line bg-surface p-4 md:p-6">{{ $benefits }}</section>
        @endif

        <form method="post" action="{{ route('wholesale.store') }}" novalidate aria-labelledby="wholesale-form" class="flex flex-col gap-5 rounded-card border border-line bg-surface p-4 md:p-6">
            @csrf
            <input type="hidden" name="started" value="{{ $started }}">

            {{-- Ловушка для роботов: людям не видна и в порядок фокуса не попадает. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="wholesale-website">{{ __('shop.checkout.honeypot') }}</label>
                <input type="text" id="wholesale-website" name="{{ WholesaleApplicationRequest::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="flex flex-col gap-3">
                <h2 id="wholesale-form" class="text-lg font-semibold">{{ __('shop.wholesale.form_heading') }}</h2>
                <div class="flex flex-wrap gap-2" aria-hidden="true">
                    @foreach (__('shop.wholesale.chips') as $label)
                        <span @class([$chip, 'bg-accent-ink font-semibold text-white' => $loop->first, 'border border-line font-medium text-steel-500' => ! $loop->first])>{{ $label }}</span>
                    @endforeach
                </div>
            </div>

            @error('form')
                <p class="text-sm text-danger-text">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-3.5">
                    <x-ui.input name="inn" :label="__('shop.wholesale.fields.inn')" :value="old('inn')" inputmode="numeric" maxlength="14" autocomplete="off" mono required />
                    <x-ui.input name="legal_name" :label="__('shop.wholesale.fields.legal_name')" :value="old('legal_name')" :placeholder="__('shop.wholesale.fields.legal_name_placeholder')" autocomplete="organization" maxlength="255" required />
                    <x-ui.select name="segment" :label="__('shop.wholesale.fields.segment')" :options="$segments" :selected="old('segment')" :placeholder="__('shop.wholesale.fields.segment_placeholder')" required />
                    <x-ui.input name="city" :label="__('shop.wholesale.fields.city')" :value="old('city')" autocomplete="address-level2" maxlength="150" required />
                </div>

                <div class="flex flex-col gap-3.5">
                    <x-ui.input name="contact_person" :label="__('shop.wholesale.fields.contact_person')" :value="old('contact_person', $user?->name)" :placeholder="__('shop.wholesale.fields.contact_person_placeholder')" autocomplete="name" maxlength="150" required />
                    <x-ui.input name="email" type="email" :label="__('shop.wholesale.fields.email')" :value="old('email', $user?->email)" autocomplete="email" maxlength="150" required />
                    <x-ui.input
                        name="phone"
                        type="tel"
                        :label="__('shop.wholesale.fields.phone')"
                        :value="old('phone', $user?->phone)"
                        placeholder="+7 ___ ___-__-__"
                        inputmode="tel"
                        autocomplete="tel"
                        data-phone-mask
                        mono
                        required
                    />
                    <div class="{{ $note }}">
                        <p class="text-sm font-semibold">{{ __('shop.wholesale.after_heading') }}</p>
                        <p class="text-sm text-steel-500">{{ __('shop.wholesale.after_text') }}</p>
                    </div>
                </div>
            </div>

            @if ($user === null)
                <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                    <x-ui.input name="password" type="password" :label="__('shop.wholesale.fields.password')" :hint="__('shop.auth.register.password_hint')" autocomplete="new-password" required />
                    <x-ui.input name="password_confirmation" type="password" :label="__('shop.wholesale.fields.password_confirmation')" autocomplete="new-password" required />
                </div>
                <p class="text-sm text-steel-500">
                    {{ __('shop.wholesale.login_hint') }}
                    <a href="{{ route('login') }}" class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.wholesale.login_link') }}</a>
                </p>
            @else
                <p class="text-sm text-steel-500">{{ __('shop.wholesale.account_hint', ['email' => $user->email]) }}</p>
            @endif

            <div class="flex flex-col gap-1.5">
                <label for="comment" class="text-sm leading-[1.4] font-medium text-steel-500">{{ __('shop.wholesale.fields.comment') }}</label>
                <textarea
                    id="comment"
                    name="comment"
                    rows="3"
                    maxlength="2000"
                    placeholder="{{ __('shop.wholesale.fields.comment_placeholder') }}"
                    @error('comment') aria-invalid="true" aria-describedby="comment-error" @enderror
                    @class([
                        'w-full rounded-control border bg-surface px-3 py-2.5 text-base text-ink transition-colors duration-150 ease-out placeholder:text-steel-500 focus:border-accent',
                        'border-danger' => $errors->has('comment'),
                        'border-line' => ! $errors->has('comment'),
                    ])
                >{{ old('comment') }}</textarea>
                @error('comment')
                    <p id="comment-error" class="text-sm text-danger-text">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-4 border-t border-line-soft pt-4">
                <div class="flex flex-col gap-1.5">
                    <x-ui.consent :checked="(bool) old('consent')" :error="$errors->first('consent')" />
                    @error('consent')
                        <p id="consent-error" class="text-sm text-danger-text">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="submit" class="self-start px-5">{{ __('shop.wholesale.submit') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
