{{--
    «Знаете артикул?» (ТЗ §8.4, облик «Свечение», макет — блок sku): поле поиска по артикулу или модели. Это обычная
    GET-форма на /search с полем q, как и поиск в шапке: работает без скриптов. Кегль поля — 17 px, чтобы телефон
    не увеличивал страницу при фокусе.
--}}
<section class="gl-sec" id="sku" aria-labelledby="home-sku">
    <i class="gl-glowzone gl-gz--sku" aria-hidden="true"></i>

    <div class="gl-wrap">
        <div class="gl-sku">
            <div class="gl-sku__grid">
                <h2 class="gl-h2" id="home-sku">{{ __('shop.home.sku.heading_lead') }} <span class="gl-grad">{{ __('shop.home.sku.heading_accent') }}</span>{{ __('shop.home.sku.heading_tail') }}</h2>

                <div>
                    <form action="{{ route('search') }}" method="get" role="search" aria-label="{{ __('shop.home.sku.label') }}">
                        <x-home.icon name="search" class="gl-ic--lead" />
                        <label class="sr-only" for="home-sku-field">{{ __('shop.home.sku.field') }}</label>
                        <input
                            id="home-sku-field"
                            type="search"
                            name="q"
                            placeholder="{{ __('shop.home.sku.field') }}"
                            autocomplete="off"
                            spellcheck="false"
                            enterkeyhint="search"
                        >
                        <button type="submit" class="gl-btn gl-btn--hot">{{ __('shop.layout.search') }}</button>
                    </form>

                    <p class="gl-sku__cap">{{ __('shop.home.sku.text') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
