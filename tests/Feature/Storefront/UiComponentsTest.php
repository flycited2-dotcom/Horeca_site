<?php

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\Price;
use App\Support\Money;
use Illuminate\Support\Carbon;

it('keeps a validation error at its field', function () {
    $this->withViewErrors(['phone' => 'Телефон не распознан. Формат: +7 978 123-45-67'])
        ->blade('<x-ui.input name="phone" label="Телефон" hint="Для связи по заказу" />')
        ->assertSee('for="phone"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="phone-error"', false)
        ->assertSee('Телефон не распознан. Формат: +7 978 123-45-67')
        ->assertDontSee('Для связи по заказу');
});

it('describes a valid field by its hint', function () {
    $this->blade('<x-ui.input name="inn" label="ИНН" hint="10 или 12 цифр" mono />')
        ->assertSee('aria-describedby="inn-hint"', false)
        ->assertSee('10 или 12 цифр')
        ->assertSee('font-mono', false)
        ->assertDontSee('aria-invalid', false);
});

it('finds the error of a nested field name', function () {
    $this->withViewErrors(['price.from' => 'Цена «от» должна быть числом'])
        ->blade('<x-ui.input name="price[from]" label="Цена от" />')
        ->assertSee('id="price-from"', false)
        ->assertSee('Цена «от» должна быть числом');
});

it('renders the counter as a plain numeric field with step buttons', function () {
    $this->blade('<x-ui.counter name="quantity" :value="4" :min="1" :max="50" />')
        ->assertSee('type="number"', false)
        ->assertSee('name="quantity"', false)
        ->assertSee('value="4"', false)
        ->assertSee('min="1"', false)
        ->assertSee('max="50"', false)
        ->assertSee('Уменьшить количество')
        ->assertSee('Увеличить количество');
});

it('renders the toggle as a real checkbox with the switch role', function () {
    $checkbox = fn (string $html): string => preg_match('/<input[^>]*>/', $html, $match) ? $match[0] : '';

    $on = (string) $this->blade('<x-ui.toggle name="in_stock" :checked="true">Только в наличии</x-ui.toggle>');
    $off = (string) $this->blade('<x-ui.toggle name="in_stock">Только в наличии</x-ui.toggle>');

    expect($checkbox($on))
        ->toContain('type="checkbox"', 'role="switch"', 'name="in_stock"')
        ->toMatch('/\schecked[\s>]/')
        ->and($on)->toContain('Только в наличии')
        ->and($checkbox($off))->not->toMatch('/\schecked[\s>]/');
});

it('names every availability status in words', function (Availability $availability) {
    $this->blade('<x-ui.availability :availability="$availability" />', ['availability' => $availability])
        ->assertSee($availability->getLabel());
})->with(Availability::cases());

it('shows the expected arrival date when the supplier gave one', function () {
    $this->blade('<x-ui.availability :availability="$availability" :incoming-at="$date" />', [
        'availability' => Availability::Incoming,
        'date' => Carbon::create(2026, 9, 28),
    ])->assertSee('Ожидается 28 сентября');
});

it('marks a disabled button for assistive technology', function () {
    $html = (string) $this->blade('<x-ui.button disabled>Недоступна</x-ui.button>');

    expect($html)->toContain('aria-disabled="true"')->toMatch('/<button[^>]*\sdisabled[\s>]/');
});

it('makes the main button orange with dark text and the secondary ones glass or outline', function () {
    $primary = (string) $this->blade('<x-ui.button>Найти</x-ui.button>');
    $secondary = (string) $this->blade('<x-ui.button variant="secondary">Сбросить</x-ui.button>');
    $neutral = (string) $this->blade('<x-ui.button variant="neutral">Назад</x-ui.button>');

    expect($primary)->toContain('gl-btn', 'gl-btn--sm', 'gl-btn--hot')
        // Белый текст на оранжевом не читается: цвет текста даёт только основа (#15191D).
        ->not->toContain('text-white')
        ->not->toContain('bg-signal')
        ->and($secondary)->toContain('gl-btn--glass')->not->toContain('gl-btn--hot')
        ->and($neutral)->toContain('gl-btn--quiet')->not->toContain('gl-btn--hot');
});

it('keeps a link button a link and a disabled one off the page flow of clicks', function () {
    $this->blade('<x-ui.button href="/catalog" variant="neutral">Каталог</x-ui.button>')
        ->assertSee('<a href="/catalog"', false)
        ->assertSee('gl-btn--quiet', false);

    $disabled = (string) $this->blade('<x-ui.button disabled>Недоступна</x-ui.button>');

    expect($disabled)->toContain('gl-btn--off', 'pointer-events-none')->not->toContain('gl-btn--hot');
});

it('passes the classes of the caller on top of the button', function () {
    $this->blade('<x-ui.button class="w-full text-sm" data-hook="x">В корзину</x-ui.button>')
        ->assertSee('w-full text-sm', false)
        ->assertSee('data-hook="x"', false);
});

it('keeps the quantity controls and their hooks in the glass counter', function () {
    $html = (string) $this->blade('<x-ui.counter name="quantity" id="q-1" form="cart-add-1" />');

    expect($html)->toContain('gl-counter', 'gl-counter__btn', 'gl-counter__input')
        ->and($html)->toContain('role="group"', 'aria-controls="q-1"', 'form="cart-add-1"', 'stepDown', 'stepUp');
});

it('draws every status on the light plate in words and in its own marker form', function () {
    $plate = fn (Availability $availability): string => (string) test()->blade(
        '<x-ui.availability :availability="$availability" tone="plate" />',
        ['availability' => $availability],
    );

    expect($plate(Availability::InStock))->toContain('gl-av gl-av--in', 'В наличии')
        ->and($plate(Availability::Incoming))->toContain('gl-av--incoming', 'Ожидается')
        ->and($plate(Availability::OnOrder))->toContain('gl-av--order', 'Под заказ')
        ->and($plate(Availability::Discontinued))->toContain('gl-av--off', Availability::Discontinued->getLabel());

    // Круглый маркер, ромб (в CSS — поворот квадрата), квадрат с пунктирной рамкой и чёрточка не совпадают по форме.
    foreach (Availability::cases() as $availability) {
        expect($plate($availability))->toContain('gl-av__m', 'aria-hidden="true"');
    }
});

it('keeps the dark status badges with their marker forms on glass surfaces', function () {
    $dark = fn (Availability $availability): string => (string) test()->blade(
        '<x-ui.availability :availability="$availability" />',
        ['availability' => $availability],
    );

    expect($dark(Availability::InStock))->toContain('rounded-full', 'bg-stock-dot')
        ->and($dark(Availability::Incoming))->toContain('rotate-45')
        ->and($dark(Availability::OnOrder))->toContain('border-dashed')
        ->and($dark(Availability::InStock))->not->toContain('gl-av');
});

it('prints the amount in the price face with non-breaking spaces', function () {
    $price = new Price(Money::ofRubles(383_995), Money::ofRubles(383_995));

    $this->blade('<x-ui.price :price="$price" />', ['price' => $price])
        ->assertSee('class="gl-price gl-price--card"', false)
        ->assertSee("383\u{00A0}995\u{00A0}₽", false);

    $this->blade('<x-ui.price :price="$price" size="page" />', ['price' => $price])
        ->assertSee('gl-price--page', false);
});

it('shows the wholesale price with the retail one struck out and a pill', function () {
    $price = new Price(Money::ofRubles(21_160), Money::ofRubles(24_900), isWholesale: true, tierName: 'Опт 1');

    $this->blade('<x-ui.price :price="$price" />', ['price' => $price])
        ->assertSee('line-through', false)
        ->assertSee("24\u{00A0}900\u{00A0}₽", false)
        ->assertSee('gl-yours', false)
        ->assertSee('Ваша цена, Опт 1');
});

it('says what happens next when there is no price', function () {
    $this->blade('<x-ui.price :price="null" />')
        ->assertSee('gl-price-ask', false)
        ->assertSee('Цена по запросу');
});

it('lays a product without a photo on a light plate with the glow of its zone', function () {
    $category = (new Category)->forceFill(['name' => 'Холодильное оборудование', 'icon' => 'refrigeration']);
    $product = (new Product)->forceFill(['id' => 1, 'name' => 'Шкаф холодильный'])->setRelation('category', $category);

    $plate = (string) $this->blade(
        '<x-ui.product-image :product="$product" plate zone="cold" ratio="aspect-[4/3]" :with-type="true" />',
        ['product' => $product],
    );
    $flat = (string) $this->blade('<x-ui.product-image :product="$product" />', ['product' => $product]);

    expect($plate)->toContain('gl-plate gl-pimg', 'gl-cold', 'aspect-[4/3]', 'Фото нет, показан тип оборудования', 'Холодильное', '<svg')
        ->not->toContain('<img')
        ->and($flat)->not->toContain('gl-plate')->toContain('bg-bg');
});

it('keeps the hooks the storefront script uses in the notice', function () {
    $this->blade('<x-ui.notice text="Шкаф добавлен" href="/cart" link="Открыть корзину" />')
        ->assertSee('data-notice-item', false)
        ->assertSee('data-notice-text', false)
        ->assertSee('data-notice-link', false)
        ->assertSee('data-notice-close', false)
        ->assertSee('gl-toast', false)
        ->assertSee('Шкаф добавлен')
        ->assertSee('Открыть корзину');
});

it('marks the chosen delivery card with a real radio button', function () {
    $html = (string) $this->blade('<x-ui.choice name="delivery" value="pickup" title="Самовывоз" description="Из Симферополя" checked />');

    expect($html)->toContain('gl-choice', 'type="radio"', 'name="delivery"', 'value="pickup"', 'Самовывоз', 'Из Симферополя')
        ->toMatch('/<input[^>]*\schecked[\s>]/');
});

it('shows the switch track after the real checkbox', function () {
    $html = (string) $this->blade('<x-ui.toggle name="in_stock">Только в наличии</x-ui.toggle>');

    expect($html)->toContain('role="switch"', 'gl-switch')
        ->and(strpos($html, 'role="switch"'))->toBeLessThan(strpos($html, 'gl-switch'));
});

it('lays the fields on the opaque dark surface and keeps the error border from the utility', function () {
    $this->withViewErrors(['phone' => 'Телефон не распознан'])
        ->blade('<x-ui.input name="phone" label="Телефон" />')
        ->assertSee('gl-field', false)
        ->assertSee('border-danger', false);

    $this->blade('<x-ui.select name="city" :options="[\'a\' => \'Симферополь\']" label="Город" />')
        ->assertSee('gl-field', false)
        ->assertSee('Симферополь');
});
