@props(['user', 'company' => null, 'active', 'current', 'links' => []])

{{--
    Каркас кабинета (ТЗ §11; макет — экран 7): крошки, название компании или имя клиента,
    ИНН моноширинным, статус заявки на опт, пока она не одобрена, и вкладки разделов.
    Разделы дилерского портала из макета — счета и закрывающие, шаблоны, сервис,
    сотрудники — после запуска (ТЗ §21). Вкладки — обычные ссылки, работают без скриптов.
--}}
@php
    use App\Enums\CompanyStatus;

    $tabs = [
        'summary' => route('account'),
        'orders' => route('account.orders'),
        'favorites' => route('favorites'),
        'company' => $company !== null ? route('account.company') : null,
    ];

    $statusChip = match ($company?->status) {
        CompanyStatus::Pending => 'border-incoming-line bg-incoming-bg text-incoming',
        CompanyStatus::Rejected, CompanyStatus::Blocked => 'border-line bg-bg text-steel-500',
        default => null,
    };
@endphp

<x-catalog.breadcrumbs :links="$active === 'summary' ? [] : [['name' => __('shop.account.title'), 'url' => route('account')], ...$links]" :current="$current" />

<div class="mt-3 flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="flex min-w-0 flex-col gap-1.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ $company?->legal_name ?? $user->name }}</h1>
            <p class="text-base text-steel-500">
                @if ($company !== null)
                    <x-ui.data class="text-base">{{ __('shop.account.inn', ['inn' => $company->inn]) }}</x-ui.data> · {{ $user->name }}
                @else
                    {{ $user->email }}
                @endif
            </p>
        </div>

        @if ($statusChip)
            <span class="rounded-full border px-3 py-1.75 text-sm leading-[1.2] font-semibold {{ $statusChip }}">{{ __('shop.account.company_status.'.$company->status->value) }}</span>
        @endif
    </div>

    <nav aria-label="{{ __('shop.account.tabs_label') }}" class="-mx-3 overflow-x-auto border-b border-line-soft px-3 md:mx-0 md:px-0">
        <ul class="flex gap-1">
            @foreach ($tabs as $tab => $href)
                @continue($href === null)
                <li>
                    <a
                        href="{{ $href }}"
                        @if ($tab === $active) aria-current="page" @endif
                        @class([
                            'flex h-control items-center px-2.5 text-md whitespace-nowrap transition-colors duration-150 ease-out',
                            'font-semibold text-ink shadow-[inset_0_-2px_0_var(--color-accent)]' => $tab === $active,
                            'font-medium text-steel-500 hover:text-accent-ink' => $tab !== $active,
                        ])
                    >{{ __('shop.account.tabs.'.$tab) }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{ $slot }}
</div>
