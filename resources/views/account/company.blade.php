{{--
    «Компания» в кабинете (ТЗ §11): реквизиты организации, банка и контакт для счетов. Смена
    ИНН или юр. названия отправляет одобренную компанию на повторную проверку — об этом
    предупреждение над формой. У заблокированной компании ИНН и название только для чтения:
    их меняет менеджер. Подстановка реквизитов по ИНН из ЕГРЮЛ — после запуска (ТЗ §21).
--}}
@php
    use App\Enums\CompanySegment;
    use App\Enums\CompanyStatus;

    $segments = collect(CompanySegment::cases())->mapWithKeys(fn (CompanySegment $segment): array => [$segment->value => $segment->getLabel()])->all();
    $blocked = $company->status === CompanyStatus::Blocked;
    $note = match ($company->status) {
        CompanyStatus::Approved, CompanyStatus::Rejected => ['border-incoming-line bg-incoming-bg text-incoming', __('shop.account.company.recheck_warning')],
        CompanyStatus::Pending => ['border-line-soft bg-bg text-ink', __('shop.account.company.pending_note')],
        CompanyStatus::Blocked => ['border-line-soft bg-bg text-ink', __('shop.account.company.blocked')],
    };
    $field = fn (string $name): string => __('shop.account.company.fields.'.$name);
    $fieldset = 'flex flex-col gap-3.5';
    $legend = 'mb-3.5 text-lg font-semibold';
@endphp

<x-layouts.app :title="__('shop.account.company.title')" noindex>
    <x-account.frame :user="$user" :company="$company" active="company" :current="__('shop.account.company.title')">
        <form method="post" action="{{ route('account.company.update') }}" novalidate aria-labelledby="company-heading" class="flex max-w-[820px] flex-col gap-6 rounded-card border border-line bg-surface p-4 md:p-6">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-3">
                <h2 id="company-heading" class="text-title font-semibold">{{ __('shop.account.company.heading') }}</h2>
                <p class="rounded-card border px-3.5 py-2.5 text-base {{ $note[0] }}">{{ $note[1] }}</p>
            </div>

            <fieldset class="{{ $fieldset }}">
                <legend class="{{ $legend }}">{{ __('shop.account.company.organization') }}</legend>
                <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                    <x-ui.input name="legal_name" :label="$field('legal_name')" :value="old('legal_name', $company->legal_name)" autocomplete="organization" maxlength="255" :readonly="$blocked" required />
                    <x-ui.input name="brand_name" :label="$field('brand_name')" :value="old('brand_name', $company->brand_name)" maxlength="255" />
                    <x-ui.input name="inn" :label="$field('inn')" :value="old('inn', $company->inn)" inputmode="numeric" maxlength="14" autocomplete="off" :readonly="$blocked" mono required />
                    <x-ui.input name="kpp" :label="$field('kpp')" :value="old('kpp', $company->kpp)" inputmode="numeric" maxlength="12" autocomplete="off" mono />
                    <x-ui.input name="ogrn" :label="$field('ogrn')" :value="old('ogrn', $company->ogrn)" inputmode="numeric" maxlength="20" autocomplete="off" mono />
                    <x-ui.select name="segment" :label="$field('segment')" :options="$segments" :selected="old('segment', $company->segment->value)" required />
                    <x-ui.input name="city" :label="$field('city')" :value="old('city', $company->city)" autocomplete="address-level2" maxlength="150" required />
                </div>
                <x-ui.input name="legal_address" :label="$field('legal_address')" :value="old('legal_address', $company->legal_address)" maxlength="500" />
                <x-ui.input name="delivery_address" :label="$field('delivery_address')" :value="old('delivery_address', $company->delivery_address)" autocomplete="street-address" maxlength="500" />
            </fieldset>

            <fieldset class="{{ $fieldset }}">
                <legend class="{{ $legend }}">{{ __('shop.account.company.bank') }}</legend>
                <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                    <x-ui.input name="bank_name" :label="$field('bank_name')" :value="old('bank_name', $company->bank_name)" maxlength="255" />
                    <x-ui.input name="bik" :label="$field('bik')" :value="old('bik', $company->bik)" inputmode="numeric" maxlength="12" autocomplete="off" mono />
                    <x-ui.input name="account" :label="$field('account')" :value="old('account', $company->account)" inputmode="numeric" maxlength="26" autocomplete="off" mono />
                    <x-ui.input name="corr_account" :label="$field('corr_account')" :value="old('corr_account', $company->corr_account)" inputmode="numeric" maxlength="26" autocomplete="off" mono />
                </div>
            </fieldset>

            <fieldset class="{{ $fieldset }}">
                <legend class="{{ $legend }}">{{ __('shop.account.company.contact') }}</legend>
                <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                    <x-ui.input name="contact_person" :label="$field('contact_person')" :value="old('contact_person', $company->contact_person)" autocomplete="name" maxlength="150" required />
                    <x-ui.input
                        name="phone"
                        type="tel"
                        :label="$field('phone')"
                        :value="old('phone', $company->phone)"
                        placeholder="+7 ___ ___-__-__"
                        inputmode="tel"
                        autocomplete="tel"
                        data-phone-mask
                        mono
                        required
                    />
                    <x-ui.input name="email" type="email" :label="$field('email')" :value="old('email', $company->email)" autocomplete="email" maxlength="150" required />
                </div>
            </fieldset>

            <div>
                <x-ui.button type="submit" class="px-5 max-sm:w-full">{{ __('shop.account.company.submit') }}</x-ui.button>
            </div>
        </form>
    </x-account.frame>
</x-layouts.app>
