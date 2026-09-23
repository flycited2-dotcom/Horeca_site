@php
    use App\Support\Money;
    use App\Support\Typography;

    $nbsp = Typography::NBSP;

    $palette = [
        'slate' => ['bg-slate border-slate-line', '#E3E8EC'],
        'steel_500' => ['bg-steel-500 border-line', '#5D686F'],
        'steel_400' => ['bg-steel-400 border-line', '#8A959E'],
        'line' => ['bg-line border-slate-line', '#D3D9DE'],
        'bg' => ['bg-bg border-line', '#F4F6F7'],
        'accent' => ['bg-accent border-accent-ink', '#0098EA'],
    ];

    $statuses = [
        'stock' => ['border-stock-line bg-stock-bg', 'text-stock-text', \App\Enums\Availability::InStock],
        'incoming' => ['border-incoming-line bg-incoming-bg', 'text-incoming', \App\Enums\Availability::Incoming],
        'on_order' => ['border-dashed border-on-order-line bg-surface', 'text-on-order', \App\Enums\Availability::OnOrder],
    ];

    $type = [
        ['Manrope 700 · 36/1.15', 'text-2xl font-bold md:text-3xl', __('styleguide.typography.h1')],
        ['Manrope 700 · 28/1.15', 'text-2xl font-bold', __('styleguide.typography.h2')],
        ['Manrope 600 · 16/1.25', 'text-lg font-semibold', __('styleguide.typography.h3')],
        ['Manrope 400 · 14/1.5', 'text-base', __('styleguide.typography.body')],
        ['Manrope 400/500 · 13/1.45', 'text-sm text-steel-500', __('styleguide.typography.caption')],
        ['JetBrains Mono 400 · 13/1.45', 'font-mono text-sm', "11000019106 · 840×800×1120 · 18,9{$nbsp}кВт"],
    ];
@endphp

<x-layouts.app :title="__('styleguide.title')">
    <header class="flex max-w-3xl flex-col gap-3">
        <p class="font-mono text-sm font-medium text-steel-500">{{ __('styleguide.caption') }}</p>
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('styleguide.heading') }}</h1>
        <p class="max-w-prose text-lg leading-normal">{{ __('styleguide.intro') }}</p>
    </header>

    <div class="mt-8 flex flex-col gap-6">
        <x-styleguide.panel :title="__('styleguide.palette.title')" :note="__('styleguide.palette.note')">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($palette as $key => [$swatch, $hex])
                    <div class="flex flex-col gap-2">
                        <span @class(['relative h-18 overflow-hidden rounded-card border', $swatch])>
                            @if ($key === 'accent')
                                <span class="absolute inset-y-0 left-0 w-1/2 bg-accent-ink"></span>
                            @endif
                        </span>
                        <span class="text-base font-semibold leading-tight">{{ __("styleguide.palette.{$key}.name") }}</span>
                        <span class="font-mono text-sm text-steel-500">{{ $hex }}<br>{{ __("styleguide.palette.{$key}.value") }}</span>
                        <span class="text-xs text-steel-500">{{ __("styleguide.palette.{$key}.usage") }}</span>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 border-t border-line-soft pt-4 md:grid-cols-3">
                @foreach ($statuses as $key => [$swatch, $text, $availability])
                    <div class="flex items-center gap-3">
                        <span @class(['size-14 shrink-0 rounded-card border', $swatch])></span>
                        <div class="flex flex-col gap-0.5">
                            <span @class(['text-base font-semibold leading-tight', $text])>{{ $availability->getLabel() }}</span>
                            <span class="font-mono text-sm text-steel-500">{{ __("styleguide.palette.{$key}") }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-styleguide.panel>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <x-styleguide.panel :title="__('styleguide.typography.title')">
                <div class="flex flex-col">
                    @foreach ($type as [$spec, $classes, $sample])
                        <div class="grid grid-cols-1 items-baseline gap-2 border-b border-line-soft py-4 first:pt-0 md:grid-cols-[150px_minmax(0,1fr)] md:gap-5">
                            <span class="font-mono text-sm text-steel-500">{{ $spec }}</span>
                            <span class="{{ $classes }}">{{ $sample }}</span>
                        </div>
                    @endforeach

                    <div class="grid grid-cols-1 items-baseline gap-2 py-4 md:grid-cols-[150px_minmax(0,1fr)] md:gap-5">
                        <span class="text-sm text-steel-500">{{ __('styleguide.typography.data') }}</span>
                        <span class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            <span class="font-bold tabular">{{ Typography::money(Money::ofRubles(383_995)) }}</span>
                            <span class="font-mono text-sm">7{{ $nbsp }}кВт</span>
                            <span class="font-mono text-sm">400×750×470{{ $nbsp }}мм</span>
                            <span>«Под заказ» — 30–45 дней</span>
                        </span>
                    </div>
                </div>

                <p class="text-sm text-steel-500">{{ __('styleguide.typography.note') }}</p>
            </x-styleguide.panel>

            <div class="flex flex-col gap-6">
                <x-styleguide.panel :title="__('styleguide.buttons.title')">
                    <div class="flex flex-col gap-2.5">
                        <x-ui.button>{{ __('styleguide.buttons.primary') }}</x-ui.button>
                        <x-ui.button variant="secondary">{{ __('styleguide.buttons.secondary') }}</x-ui.button>
                        <x-ui.button variant="neutral">{{ __('styleguide.buttons.neutral') }}</x-ui.button>
                        <x-ui.button disabled>{{ __('styleguide.buttons.disabled') }}</x-ui.button>
                    </div>

                    <p class="text-sm text-steel-500">{{ __('styleguide.buttons.note') }}</p>
                </x-styleguide.panel>

                <x-styleguide.panel :title="__('styleguide.fields.title')">
                    <x-ui.input
                        name="styleguide_search"
                        type="search"
                        :label="__('styleguide.fields.search')"
                        :placeholder="__('styleguide.fields.search_placeholder')"
                        :hint="__('styleguide.fields.search_hint')"
                    />
                    <x-ui.input name="styleguide_inn" :label="__('styleguide.fields.inn')" value="7714365421" inputmode="numeric" mono />
                    <x-ui.input
                        name="styleguide_phone"
                        type="tel"
                        :label="__('styleguide.fields.phone')"
                        value="+7 978 12"
                        :error="__('styleguide.fields.phone_error')"
                        mono
                    />

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <x-ui.counter name="styleguide_quantity" :value="4" />
                        <x-ui.toggle name="styleguide_in_stock" checked>{{ __('styleguide.fields.in_stock') }}</x-ui.toggle>
                        <x-ui.toggle name="styleguide_with_photo">{{ __('styleguide.fields.with_photo') }}</x-ui.toggle>
                    </div>

                    <x-ui.consent id="styleguide-consent" />
                </x-styleguide.panel>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <x-styleguide.panel :title="__('styleguide.grid.title')">
                <x-styleguide.facts :rows="[
                    [__('styleguide.grid.desktop'), '1440 / 1320', true],
                    [__('styleguide.grid.listing'), __('styleguide.grid.listing_value'), false],
                    [__('styleguide.grid.per_row'), '4 / 3 / 2 / 1', false],
                    [__('styleguide.grid.spacing'), '4 · 8 · 12 · 16 · 24 · 32', true],
                    [__('styleguide.grid.mobile'), __('styleguide.grid.mobile_value'), true],
                ]" />
            </x-styleguide.panel>

            <x-styleguide.panel :title="__('styleguide.states.title')">
                <x-styleguide.facts :rows="[
                    [__('styleguide.states.card_hover'), __('styleguide.states.card_hover_value'), false],
                    [__('styleguide.states.shadow'), '0 4px 16px /.10', true],
                    [__('styleguide.states.focus'), 'outline 2px #0098EA', false],
                    [__('styleguide.states.transition'), '.15s ease', true],
                    [__('styleguide.states.shift'), __('styleguide.states.shift_value'), false],
                ]" />
            </x-styleguide.panel>

            <x-styleguide.panel :title="__('styleguide.rules.title')" tone="slate">
                <ul class="flex flex-col gap-2.5 text-base text-steel-500">
                    @foreach (__('styleguide.rules.items') as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </x-styleguide.panel>
        </div>

        <x-styleguide.panel :title="__('styleguide.availability.title')" :note="__('styleguide.availability.note')">
            <div class="flex flex-wrap gap-x-8 gap-y-4">
                @foreach ($availabilities as $availability)
                    <div class="flex flex-col gap-2">
                        <x-ui.availability :availability="$availability" />
                        <x-ui.data>{{ $availability->value }}</x-ui.data>
                    </div>
                @endforeach

                <div class="flex flex-col gap-2">
                    <x-ui.availability :availability="\App\Enums\Availability::Incoming" :incoming-at="$incomingAt" />
                    <x-ui.data>{{ __('styleguide.availability.with_date') }}</x-ui.data>
                </div>
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.prices.title')" :note="__('styleguide.prices.note')">
            <div class="grid grid-cols-2 gap-6 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($prices as $key => $price)
                    <div class="flex flex-col gap-2">
                        <span class="text-sm text-steel-500">{{ __("styleguide.prices.{$key}") }}</span>
                        <x-ui.price :price="$price" />
                    </div>
                @endforeach

                <div class="flex flex-col gap-2">
                    <span class="text-sm text-steel-500">{{ __('styleguide.prices.on_request') }}</span>
                    <x-ui.price :price="null" />
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-sm text-steel-500">{{ __('styleguide.prices.page') }}</span>
                    <x-ui.price :price="$prices['wholesale']" size="page" />
                </div>
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.placeholders.title')" :note="__('styleguide.placeholders.note')">
            <div class="flex flex-wrap items-end gap-6">
                @foreach ($icons as $icon)
                    <div class="flex flex-col items-center gap-2">
                        <span class="flex size-18 items-center justify-center rounded-card bg-bg">
                            <x-ui.equipment-icon :icon="$icon" class="size-11 text-steel-400" />
                        </span>
                        <x-ui.data>{{ $icon ?? __('styleguide.placeholders.default') }}</x-ui.data>
                    </div>
                @endforeach

                <x-ui.product-image :product="$cards[0]['product']" class="w-72" />
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.cards.title')" :note="__('styleguide.cards.note')" tone="canvas">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($cards as $card)
                    <x-catalog.product-card :product="$card['product']" :price="$card['price']" />
                @endforeach

                <x-catalog.product-card-skeleton />
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.rows.title')" :note="__('styleguide.rows.note')" tone="canvas">
            <x-catalog.product-row :product="$cards[0]['product']" :price="$cards[0]['price']" exact />

            <div class="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-card md:border md:border-line md:bg-surface md:[&>article:last-child]:border-b-0">
                @foreach (array_slice($cards, 2, 3) as $card)
                    <x-catalog.product-row :product="$card['product']" :price="$card['price']" />
                @endforeach
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.listing.title')" :note="__('styleguide.listing.note')">
            <div class="flex flex-wrap items-center gap-3">
                <x-catalog.sort-control :filters="$listing['filters']" :sorts="$listing['sorts']" :url-for="$listing['urlFor']" />
                <x-catalog.view-toggle view="grid" :filters="$listing['filters']" :url-for="$listing['urlFor']" />
            </div>

            <x-catalog.section-links
                :label="__('shop.brands.sections_label', ['brand' => 'Abat'])"
                :title="__('shop.brands.sections')"
                :sections="$listing['sections']"
                :current="$listing['section']"
                :filters="$listing['filters']"
                :url-for="$listing['urlFor']"
            />

            <x-catalog.filter-chips :chips="$listing['chips']" :reset-url="$listing['urlFor']($listing['filters']->cleared())" />

            <x-catalog.pagination :slice="$listing['slice']" :filters="$listing['filters']" :url-for="$listing['urlFor']" class="border-t border-line-soft pt-4" />
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.checkout.title')" :note="__('styleguide.checkout.note')">
            <div role="radiogroup" aria-label="{{ __('shop.checkout.delivery') }}" class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <x-ui.choice name="sample_delivery" value="pickup" :title="__('enums.delivery_method.pickup')" :description="__('shop.checkout.delivery_notes.pickup')" checked />
                <x-ui.choice name="sample_delivery" value="transport" :title="__('enums.delivery_method.transport_company')" :description="__('shop.checkout.delivery_notes.transport_company')" />
                <x-ui.choice name="sample_delivery" value="courier" :title="__('enums.delivery_method.courier_city')" :description="__('shop.checkout.delivery_notes.courier_city')" />
            </div>

            <x-ui.notice :text="__('shop.cart.added', ['name' => 'Пароконвектомат ПКА 10-1/1ВП2-01', 'quantity' => 2, 'unit' => 'шт'])" href="#" :link="__('shop.cart.open')" />

            <div class="flex max-w-xl flex-col gap-2 rounded-card border border-line p-4">
                <h3 class="text-lg font-semibold">{{ __('shop.leads.titles.price_request') }}</h3>
                <p class="text-base text-steel-500">{{ __('shop.leads.texts.price_request') }}</p>
                <x-lead.form id="sample-lead" type="price_request" class="mt-2" />
            </div>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.auth.title')" :note="__('styleguide.auth.note')">
            <x-auth.card :heading="__('shop.auth.login.heading')" :intro="__('shop.auth.login.intro')" :level="3" class="mt-0!">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="styleguide_login" :label="__('shop.auth.login.login')" value="zakupki@kafe.ru" />
                    <x-ui.input name="styleguide_password" type="password" :label="__('shop.auth.login.password')" value="secret" :error="trans_choice('shop.auth.login.failed', 3, ['count' => 3])" />
                    <x-ui.button class="w-full">{{ __('shop.auth.login.submit') }}</x-ui.button>
                </div>

                <x-slot:footer>
                    <p class="text-base font-medium">{{ __('shop.auth.login.no_account') }}</p>
                    <p class="text-sm text-steel-500">{{ __('shop.auth.login.no_account_text') }}</p>
                </x-slot:footer>
            </x-auth.card>
        </x-styleguide.panel>

        <x-styleguide.panel :title="__('styleguide.breadcrumbs.title')">
            <div class="flex flex-col gap-3">
                <span class="text-sm text-steel-500">{{ __('styleguide.breadcrumbs.category') }}</span>
                <x-catalog.breadcrumbs :category="$crumbCategory" />

                <span class="mt-2 text-sm text-steel-500">{{ __('styleguide.breadcrumbs.product') }}</span>
                <x-catalog.breadcrumbs :product="$crumbProduct" />

                <span class="mt-2 text-sm text-steel-500">{{ __('styleguide.breadcrumbs.brand') }}</span>
                <x-catalog.breadcrumbs :brand="$crumbBrand" />
            </div>
        </x-styleguide.panel>
    </div>
</x-layouts.app>
