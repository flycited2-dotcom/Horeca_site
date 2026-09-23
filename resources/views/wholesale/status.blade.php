{{--
    Статус заявки на опт (ТЗ §11; макет — экран 15d): на проверке — номер, когда отправлена,
    плашка «Проверка реквизитов», что уже работает и что откроется после проверки; одобрена —
    «Оптовые цены открыты»; отклонена или заблокирована — что делать дальше. Каталог работает
    всегда: блокируются только оптовые цены.
--}}
@php
    use App\Enums\CompanyStatus;

    $phones = is_string($phone) ? array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $phone) ?: []))) : (is_array($phone) ? $phone : []);
    $managerPhone = $phones[0] ?? null;
    $number = __('shop.wholesale.number', ['number' => $company->id]);
    $box = 'flex flex-col gap-2 rounded-card border border-line-soft bg-bg p-3.5';
@endphp

<x-layouts.app :title="__('shop.wholesale.title')" noindex>
    <x-catalog.breadcrumbs :current="__('shop.wholesale.title')" />

    <section class="mx-auto mt-4 flex w-full max-w-[820px] flex-col gap-4 rounded-card border border-line bg-surface p-4 md:mt-8 md:p-6">
        @if ($company->status === CompanyStatus::Pending)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[1.5rem] leading-[1.2] font-bold">
                        {!! __('shop.wholesale.status.pending_heading', ['number' => '<span class="font-mono">'.e($number).'</span>']) !!}
                    </h1>
                    <p class="text-base">{{ __('shop.wholesale.status.sent_at', ['date' => $company->created_at->timezone('Europe/Moscow')->format('d.m.Y H:i')]) }}</p>
                </div>
                <span class="rounded-full border border-incoming-line bg-incoming-bg px-3 py-1.75 text-sm leading-[1.2] font-semibold text-incoming">{{ __('shop.wholesale.status.pending_badge') }}</span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="{{ $box }}">
                    <p class="text-base leading-tight font-semibold text-stock-text">{{ __('shop.wholesale.status.available_title') }}</p>
                    <p class="text-sm">{{ __('shop.wholesale.status.available_text') }}</p>
                </div>
                <div class="{{ $box }}">
                    <p class="text-base leading-tight font-semibold text-incoming">{{ __('shop.wholesale.status.locked_title') }}</p>
                    <p class="text-sm">{{ __('shop.wholesale.status.locked_text') }}</p>
                </div>
            </div>
        @elseif ($company->status === CompanyStatus::Approved)
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[1.5rem] leading-[1.2] font-bold">{{ __('shop.wholesale.status.approved_heading') }}</h1>
                <p class="text-base">{{ __('shop.wholesale.status.approved_text', ['company' => $company->legal_name]) }}</p>
                @if ($showTier && $company->priceTier)
                    <p class="text-sm text-steel-500">{{ __('shop.wholesale.status.tier', ['tier' => $company->priceTier->name]) }}</p>
                @endif
            </div>
        @else
            @php($blocked = $company->status === CompanyStatus::Blocked)
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[1.5rem] leading-[1.2] font-bold">{{ __($blocked ? 'shop.wholesale.status.blocked_heading' : 'shop.wholesale.status.rejected_heading') }}</h1>
                <p class="text-base">{{ __($blocked ? 'shop.wholesale.status.blocked_text' : 'shop.wholesale.status.rejected_text') }}</p>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button :href="route('catalog')" class="px-5">{{ __('shop.wholesale.status.to_catalog') }}</x-ui.button>
            @if ($managerPhone)
                <p class="text-sm text-steel-500">
                    {{ __('shop.wholesale.status.urgent') }}
                    <a href="tel:{{ preg_replace('/[^+\d]/', '', $managerPhone) }}" class="font-mono whitespace-nowrap text-ink tabular">{{ $managerPhone }}</a>
                </p>
            @endif
        </div>
    </section>
</x-layouts.app>
