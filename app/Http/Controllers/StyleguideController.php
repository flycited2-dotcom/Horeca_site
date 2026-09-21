<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogSort;
use App\Services\Catalog\ListingSlice;
use App\Services\Pricing\Price;
use App\Support\Money;
use App\Support\Typography;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

/**
 * Стайлгайд витрины (ТЗ §18, спринт 3): все компоненты и их состояния рядом — для сверки
 * с экраном 9 утверждённого макета. Только для локальной разработки, в остальных
 * окружениях — 404.
 *
 * Образцы не читаются из базы, а собираются из несохранённых моделей с содержимым
 * карточек макета: страница показывает все состояния при любом каталоге.
 */
class StyleguideController extends Controller
{
    /**
     * The number of sample products made so far: their ids.
     */
    private int $samples = 0;

    public function __invoke(): View
    {
        abort_unless(App::isLocal(), 404);

        $thermal = $this->category('Тепловое оборудование', 'teplovoe-oborudovanie', 'thermal');
        $combiOvens = $this->category('Пароконвектоматы', 'parokonvektomaty', 'thermal', $thermal);
        $filters = CatalogFilters::fromQuery(['in_stock' => '1', 'brand' => ['abat'], 'price_from' => '20000', 'price_to' => '400000']);
        $urlFor = fn (CatalogFilters $state, array $extra = []): string => route('styleguide', $state->toQuery() + $extra);
        $sections = new Collection([
            $this->section(1, 'Пароконвектоматы', 'parokonvektomaty', 34),
            $this->section(2, 'Плиты электрические', 'plity-elektricheskie', 21),
            $this->section(3, 'Шкафы жарочные', 'shkafy-zharochnye', 12),
        ]);

        return view('styleguide', [
            'cards' => $this->cards($thermal),
            'crumbProduct' => $this->product([
                'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01',
                'slug' => 'parokonvektomat-pka-10-1-1vp2-01',
            ], $combiOvens),
            'crumbCategory' => $combiOvens,
            'crumbBrand' => (new Brand)->forceFill(['name' => 'Abat', 'slug' => 'abat']),
            'icons' => ['refrigeration', 'thermal', 'neutral', 'dishwashing', 'electromechanical', 'ventilation', null],
            'availabilities' => Availability::cases(),
            'incomingAt' => Carbon::create(2026, 9, 28),
            'listing' => [
                'filters' => $filters,
                'sorts' => CatalogSort::cases(),
                // Page 6 of 12: the links show gaps on both sides.
                'slice' => new ListingSlice(new Collection, 288, 6, 6),
                'urlFor' => $urlFor,
                'chips' => [
                    ['label' => __('shop.catalog.in_stock_only'), 'filter' => 'in_stock', 'brand' => null, 'url' => $urlFor($filters->without('in_stock'))],
                    ['label' => 'Abat', 'filter' => 'brand', 'brand' => 'abat', 'url' => $urlFor($filters->without('brand', 'abat'))],
                    ['label' => __('shop.catalog.chip_price_range', ['from' => Typography::number(20_000), 'to' => Typography::number(400_000)]), 'filter' => 'price', 'brand' => null, 'url' => $urlFor($filters->without('price'))],
                ],
                'sections' => $sections,
                'section' => $sections->first(),
            ],
            'prices' => [
                'retail' => $this->retail(383_995),
                'promo' => new Price(Money::ofRubles(21_160), Money::ofRubles(21_160), oldPrice: Money::ofRubles(24_900)),
                'wholesale' => new Price(Money::ofRubles(21_160), Money::ofRubles(24_900), isWholesale: true, tierName: 'Опт 1'),
                'fraction' => new Price(Money::ofKopecks(4_860_580), Money::ofKopecks(4_860_580)),
            ],
        ]);
    }

    /**
     * Шесть карточек листинга из макета (экран 2): все статусы наличия, фото и заглушки,
     * цена обычная, оптовая и по запросу.
     *
     * @return list<array{product: Product, price: ?Price}>
     */
    private function cards(Category $thermal): array
    {
        $refrigeration = $this->category('Холодильное оборудование', 'holodilnoe-oborudovanie', 'refrigeration');
        $neutral = $this->category('Нейтральное оборудование', 'neytralnoe-oborudovanie', 'neutral');
        $dishwashing = $this->category('Посудомоечное оборудование', 'posudomoechnoe-oborudovanie', 'dishwashing');

        return [
            [
                'product' => $this->product(['brand' => 'Abat', 'sku' => '11000019106', 'model' => 'ПКА 10-1/1ВП2-01',
                    'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01', 'availability' => Availability::InStock], $thermal),
                'price' => $this->retail(383_995),
            ],
            [
                'product' => $this->product(['brand' => 'Abat', 'sku' => '11000021403', 'model' => 'ЭП-6ЖШ',
                    'name' => 'Плита электрическая ЭП-6ЖШ с жарочным шкафом', 'availability' => Availability::Low], $thermal),
                'price' => $this->retail(152_400),
            ],
            [
                'product' => $this->product(['brand' => 'Abat', 'sku' => '11000018820', 'model' => 'ШХс-0,7-02',
                    'name' => 'Шкаф холодильный ШХс-0,7-02 краш.', 'availability' => Availability::Incoming], $refrigeration),
                'price' => $this->retail(96_750),
            ],
            [
                'product' => $this->product(['brand' => 'Prima', 'sku' => '11000030512', 'model' => 'SM-25',
                    'name' => 'Тестомес спиральный Prima SM-25, дежа 25 л', 'availability' => Availability::OnOrder], $neutral),
                'price' => $this->retail(241_900),
            ],
            [
                'product' => $this->product(['brand' => 'Abat', 'sku' => '11000019233', 'model' => 'МПК-1400К',
                    'name' => 'Машина посудомоечная конвейерная МПК-1400К', 'availability' => Availability::InStock], $dishwashing),
                'price' => null,
            ],
            [
                'product' => $this->product(['brand' => 'Luxstahl', 'sku' => '11000044170', 'model' => 'СП-2/1200',
                    'name' => 'Стол производственный СП-2/1200, нерж. AISI 430', 'availability' => Availability::InStock], $neutral),
                'price' => new Price(Money::ofRubles(21_160), Money::ofRubles(24_900), isWholesale: true, tierName: 'Опт 1'),
            ],
        ];
    }

    /**
     * A section of «Уточнить:» and «Разделы:» with the number of products in it.
     */
    private function section(int $id, string $name, string $slug, int $products): Category
    {
        return (new Category)->forceFill(['id' => $id, 'name' => $name, 'slug' => $slug, 'products_count' => $products]);
    }

    private function category(string $name, string $slug, string $icon, ?Category $parent = null): Category
    {
        return (new Category)
            ->forceFill(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'is_active' => true])
            ->setRelation('parent', $parent);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes, Category $category): Product
    {
        $brand = isset($attributes['brand'])
            ? (new Brand)->forceFill(['name' => $attributes['brand'], 'slug' => mb_strtolower($attributes['brand'])])
            : null;

        return (new Product)
            ->forceFill(array_diff_key($attributes, ['brand' => true]) + [
                // Forms on the card (e.g. «Сравнить») need an id; the samples are never saved.
                'id' => ++$this->samples,
                'slug' => str($attributes['name'])->slug()->value(),
            ])
            ->setRelation('brand', $brand)
            ->setRelation('category', $category);
    }

    private function retail(int $rubles): Price
    {
        return new Price(Money::ofRubles($rubles), Money::ofRubles($rubles));
    }
}
