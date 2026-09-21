<?php

use App\Enums\Availability;
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
