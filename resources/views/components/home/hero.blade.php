@props(['hero', 'sections', 'images'])

{{--
    Первый экран главной (облик «Свечение», ТЗ §8.1, макет — блок hero): плашка с условиями, заголовок со словом-акцентом
    в градиенте, текст, две кнопки, мелкая строка «Цены от» (только если у моделей главной есть цены) и два
    светлых снимка — холодильного и теплового раздела — на фоне свечений с метками «Холод» и «Жар». Снимки берутся
    у витринных карточек каталога (App\View\HomeCatalog), без фото — пиктограмма раздела; без таких разделов
    визуала нет и текст занимает всю строку. Снизу — цифры каталога. Кнопка «Найдём за вас» открывает окно заявки
    lead-not-found, которое стоит на странице.
--}}
@php
    use App\Support\CategoryZone;
    use App\Support\Typography;

    $cold = $sections->lead(CategoryZone::COLD);
    $hot = $sections->lead(CategoryZone::HOT);
    $visual = $cold !== null || $hot !== null;
@endphp

<section class="gl-hero" aria-labelledby="home-heading">
    <div class="gl-wrap">
        <div @class(['gl-hero__grid', 'gl-hero__grid--solo' => ! $visual])>
            <div class="gl-hero__copy">
                <p class="gl-chip"><i class="gl-dot" aria-hidden="true"></i>{{ __('shop.home.hero.chip') }}</p>

                <h1 class="gl-h1" id="home-heading">{{ __('shop.home.hero.heading_lead') }} <span class="gl-grad">{{ __('shop.home.hero.heading_accent') }}</span></h1>

                <p class="gl-lead">{{ __('shop.home.hero.text') }}</p>

                <div class="gl-cta">
                    <a class="gl-btn gl-btn--hot" href="{{ route('catalog') }}">{{ __('shop.home.hero.catalog') }}</a>
                    <button type="button" class="gl-btn gl-btn--glass" popovertarget="lead-not-found">{{ __('shop.leads.titles.not_found') }}</button>
                </div>

                @if ($hero->priceFrom)
                    <p class="gl-fine">{{ __('shop.home.hero.price_from', ['price' => Typography::money($hero->priceFrom)]) }}</p>
                @endif
            </div>

            @if ($visual)
                {{-- Снимки и метки только украшают: те же разделы названы ниже карточками каталога. --}}
                <div @class(['gl-hv', 'gl-hv--solo' => $cold === null || $hot === null]) aria-hidden="true">
                    <span class="gl-hv__g gl-hv__g--c"></span>
                    <span class="gl-hv__g gl-hv__g--h"></span>
                    <span class="gl-hv__ring"></span>

                    @if ($cold)
                        <x-home.plate :image="$images[$cold['id']] ?? null" :icon="$cold['icon'] ?? 'refrigeration'" :eager="true" class="gl-hv__plate gl-hv__fridge gl-cold" />
                        <span class="gl-badge gl-cold gl-hv__tc"><i></i>{{ __('shop.home.zones.cold') }}</span>
                    @endif

                    @if ($hot)
                        <x-home.plate :image="$images[$hot['id']] ?? null" :icon="$hot['icon'] ?? 'thermal'" :eager="true" class="gl-hv__plate gl-hv__grill gl-hot" />
                        <span class="gl-badge gl-hot gl-hv__th"><i></i>{{ __('shop.home.zones.hot') }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if ($hero->facts !== [])
            <ul class="gl-stats" aria-label="{{ __('shop.home.hero.facts_label') }}">
                @foreach ($hero->facts as $fact)
                    <li @class([
                        'gl-card gl-stat',
                        'gl-cold' => $fact['zone'] === CategoryZone::COLD,
                        'gl-hot' => $fact['zone'] === CategoryZone::HOT,
                    ])>
                        <b>{{ Typography::number($fact['value']) }}</b>
                        <span>{{ trans_choice($fact['label'], $fact['value']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
