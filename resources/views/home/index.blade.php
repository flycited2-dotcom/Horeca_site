{{--
    Главная (ТЗ §8.1, облик «Свечение», макет gs-designs/d.html): живая сцена каркаса, первый экран с цифрами каталога
    (x-home.hero) и ниже блоки сверху вниз — каталог (две витринные карточки «Холод» и «Жар», остальные разделы
    ровной сеткой), «В наличии» гармошкой, ленты местного склада, хитов и новинок карточками, подборки
    «Соберём кухню под задачу» (если включены), бренды и поле «Знаете артикул?». Каждый блок строит свою
    секцию в .gl-wrap макета, поэтому <main> главной без полей. Всё содержимое приходит из HomeController:
    разделы — App\View\HomeCatalog, цифры — App\View\HomeHero, ленты товаров — App\View\HomeShelf. Пустая
    лента и выключенные подборки не показываются. Стили — resources/css/glow.css и glow-home.css.
--}}
@php
    use App\Support\Typography;

    $stripIds = ['in_stock' => 'stock', 'local' => 'ready', 'hits' => 'hits', 'new' => 'fresh'];
    $sectionCount = count($sections->tiles);
@endphp

<x-layouts.app :title="__('shop.home.title')" :description="__('shop.seo.home_description')">
    <x-slot:hero>
        <x-home.hero :hero="$hero" :sections="$sections" :images="$images" />
    </x-slot:hero>

    <x-lead.dialog id="lead-not-found" type="not_found" :message-label="__('shop.leads.fields.what')" />

    <section class="gl-sec" id="catalog" aria-labelledby="home-sections">
        <i class="gl-glowzone gl-gz--cat-c" aria-hidden="true"></i>
        <i class="gl-glowzone gl-gz--cat-h" aria-hidden="true"></i>

        <div class="gl-wrap">
            <x-home.heading
                id="home-sections"
                :chip="__('shop.home.catalog')"
                :lead="__('shop.home.catalog_heading_lead')"
                :accent="__('shop.home.catalog_heading_accent')"
                :sub="$sectionCount === 0 ? null : trans_choice('shop.home.catalog_sections', $sectionCount, ['count' => Typography::number($sectionCount)]).'. '.__('shop.home.catalog_note')"
            />

            @if ($sectionCount === 0)
                <p class="gl-sub">{{ __('shop.home.catalog_empty') }}</p>
            @else
                <nav aria-label="{{ __('shop.home.catalog') }}">
                    <ul class="gl-cats">
                        @foreach ($sections->tiles as $tile)
                            <li @class(['gl-cats__li', 'gl-cats__li--lg' => $tile['featured'], 'gl-cats__li--wide' => $tile['wide']])>
                                <x-home.category :tile="$tile" :image="$images[$tile['id']] ?? null" />
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </section>

    @foreach ($shelves as $key => $shelf)
        @php
            $text = __("shop.home.strips.{$key}");
            $accordion = $key === 'in_stock' && $shelf->isAccordion();
        @endphp

        <section class="gl-sec" id="{{ $stripIds[$key] }}" aria-labelledby="strip-{{ $key }}">
            @if ($accordion)
                <i class="gl-glowzone gl-gz--stock" aria-hidden="true"></i>
            @else
                <i class="gl-glowzone gl-gz--ship-c" aria-hidden="true"></i>
                <i class="gl-glowzone gl-gz--ship-h" aria-hidden="true"></i>
            @endif

            <div class="gl-wrap">
                <x-home.heading
                    :id="'strip-'.$key"
                    :chip="$text['chip']"
                    :lead="$text['lead'] ?? null"
                    :accent="$key === 'local' ? $warehouse : ($text['accent'] ?? null)"
                    :sub="$text['sub']"
                    :hint="$accordion ? __('shop.home.stock.hint') : null"
                />

                @if ($accordion)
                    <x-home.accordion :shelf="$shelf" />
                @else
                    <ul class="gl-ships">
                        @foreach ($shelf->items as $item)
                            <x-home.ship :item="$item" />
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endforeach

    @if ($collections->isNotEmpty())
        <section class="gl-sec" id="picks" aria-labelledby="collections-heading">
            <i class="gl-glowzone gl-gz--stock" aria-hidden="true"></i>

            <div class="gl-wrap">
                <x-home.heading
                    id="collections-heading"
                    :chip="__('shop.home.picks.chip')"
                    :lead="__('shop.home.picks.heading_lead')"
                    :accent="__('shop.home.picks.heading_accent')"
                    :sub="__('shop.home.picks.sub')"
                />

                <ul class="gl-picks">
                    @foreach ($collections as $collection)
                        <li>
                            <x-home.pick :collection="$collection" />
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($brands !== [])
        <section class="gl-sec" id="brands" aria-labelledby="home-brands">
            <i class="gl-glowzone gl-gz--brand-c" aria-hidden="true"></i>
            <i class="gl-glowzone gl-gz--brand-h" aria-hidden="true"></i>

            <div class="gl-wrap">
                <x-home.heading
                    id="home-brands"
                    :chip="__('shop.home.brands.chip')"
                    :lead="__('shop.home.brands.heading_lead')"
                    :accent="__('shop.home.brands.heading_accent')"
                    :sub="$brandsTotal > count($brands) ? trans_choice('shop.home.brands.sub_part', $brandsTotal, ['count' => Typography::number($brandsTotal)]) : __('shop.home.brands.sub_all')"
                    :row="true"
                >
                    <a class="gl-btn gl-btn--glass" href="{{ route('brands') }}">{{ trans_choice('shop.brands.all_count', $brandsTotal, ['count' => Typography::number($brandsTotal)]) }}</a>
                </x-home.heading>

                <ul class="gl-brands">
                    @foreach ($brands as $brand)
                        <li>
                            <a class="gl-card gl-card--quiet gl-brand" href="{{ route('brand', $brand['slug']) }}">{{ $brand['name'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-home.sku />
</x-layouts.app>
