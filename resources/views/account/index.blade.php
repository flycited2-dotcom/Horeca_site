{{--
    Сводка кабинета (ТЗ §11; макет — экран 7 в объёме MVP): три карточки — оптовые цены,
    контакты менеджера, быстрые действия, — ниже последние 5 заявок. Лимит отсрочки, счета
    и закрывающие, шаблоны закупки и сервис из макета — после запуска (ТЗ §21).
--}}
@php
    use App\Enums\CompanyStatus;

    $card = 'flex flex-col gap-3 rounded-card border border-line bg-surface p-4 md:p-5';
    $caption = 'text-sm leading-[1.3] font-medium text-steel-500';
    $link = 'self-start text-base font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark';
    $latest = $orders->first();

    [$pricesValue, $pricesTone, $pricesText] = match ($company?->status) {
        CompanyStatus::Approved => [__('shop.account.summary.prices_open'), 'text-stock-text', __('shop.account.summary.prices_open_text')],
        CompanyStatus::Pending => [__('shop.account.summary.prices_pending'), 'text-incoming', __('shop.account.summary.prices_pending_text')],
        CompanyStatus::Rejected => [__('shop.account.summary.prices_rejected'), 'text-ink', __('shop.wholesale.status.rejected_text')],
        CompanyStatus::Blocked => [__('shop.account.summary.prices_blocked'), 'text-ink', __('shop.wholesale.status.blocked_text')],
        default => [__('shop.account.summary.prices_none'), 'text-ink', __('shop.account.summary.prices_none_text')],
    };
@endphp

<x-layouts.app :title="__('shop.account.title')" noindex>
    <x-account.frame :user="$user" :company="$company" active="summary" :current="__('shop.account.title')">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6">
            <section class="{{ $card }}" aria-labelledby="account-prices">
                <h2 id="account-prices" class="{{ $caption }}">{{ __('shop.account.summary.prices_heading') }}</h2>
                <p class="text-xl leading-[1.1] font-bold {{ $pricesTone }}">{{ $pricesValue }}</p>
                <p class="text-base">{{ $pricesText }}</p>
                @if ($company?->status === CompanyStatus::Approved && $showTier && $company->priceTier)
                    <p class="text-sm text-steel-500">{{ __('shop.wholesale.status.tier', ['tier' => $company->priceTier->name]) }}</p>
                @endif
                @if ($company === null)
                    <a href="{{ route('wholesale') }}" class="{{ $link }}">{{ __('shop.account.summary.apply_link') }}</a>
                @elseif ($company->status !== CompanyStatus::Approved)
                    <a href="{{ route('wholesale') }}" class="{{ $link }}">{{ __('shop.account.summary.application_link') }}</a>
                @endif
            </section>

            <section class="{{ $card }}" aria-labelledby="account-contacts">
                <h2 id="account-contacts" class="{{ $caption }}">{{ __('shop.account.summary.contacts_heading') }}</h2>
                @if ($contacts->isEmpty())
                    <p class="text-base">{{ __('shop.account.summary.contacts_empty') }}</p>
                @else
                    <p class="text-base">{{ __('shop.account.summary.contacts_text') }}</p>
                    <div class="flex flex-col gap-1">
                        @foreach ($contacts->phones as $phone)
                            <a href="{{ $phone['href'] }}" class="self-start text-lg font-semibold whitespace-nowrap tabular transition-colors duration-150 ease-out hover:text-accent-ink">{{ $phone['label'] }}</a>
                        @endforeach
                        @if ($contacts->email)
                            <a href="mailto:{{ $contacts->email }}" class="{{ $link }}">{{ $contacts->email }}</a>
                        @endif
                    </div>
                    @if ($contacts->schedule)
                        <p class="text-sm text-steel-500">{{ $contacts->schedule }}</p>
                    @endif
                @endif
            </section>

            <section class="{{ $card }}" aria-labelledby="account-actions">
                <h2 id="account-actions" class="{{ $caption }}">{{ __('shop.account.summary.actions_heading') }}</h2>
                <div class="flex flex-col gap-2">
                    @if ($latest)
                        <form method="post" action="{{ route('account.order.repeat', $latest) }}">
                            @csrf
                            <x-ui.button type="submit" class="w-full">{{ __('shop.account.summary.repeat_last', ['number' => $latest->number]) }}</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button :href="route('catalog')" :variant="$latest ? 'neutral' : 'primary'" class="w-full">{{ __('shop.account.summary.to_catalog') }}</x-ui.button>
                    @if ($company !== null)
                        <x-ui.button :href="route('account.company')" variant="neutral" class="w-full">{{ __('shop.account.summary.company_link') }}</x-ui.button>
                    @endif
                </div>
            </section>
        </div>

        <section class="lg:rounded-card lg:border lg:border-line lg:bg-surface" aria-labelledby="account-orders">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 pb-3 lg:border-b lg:border-line-soft lg:px-4 lg:py-3.5">
                <h2 id="account-orders" class="text-title font-semibold">{{ __('shop.account.summary.orders_heading') }}</h2>
                @if ($ordersCount > 0)
                    <a href="{{ route('account.orders') }}" class="{{ $link }}">{{ __('shop.account.summary.all_orders') }} <span class="font-mono text-sm text-steel-500 tabular">{{ $ordersCount }}</span></a>
                @endif
            </div>

            @if ($orders->isEmpty())
                <p class="rounded-card border border-line bg-surface px-4 py-6 text-base text-steel-500 lg:rounded-none lg:border-0">{{ __('shop.account.orders.empty') }}</p>
            @else
                <x-account.orders-table :orders="$orders" />
            @endif
        </section>
    </x-account.frame>
</x-layouts.app>
