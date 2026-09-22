<?php

/*
 * Сценарии 1, 7 и 8 из ТЗ §2 — DoD спринта 4: путь покупателя от главной до «Спасибо»,
 * товар без остатков и товар с ценой по запросу. Фильтр по характеристике из сценария 1
 * появится с данными API (спринт 7); здесь — фильтр по бренду.
 */

use App\Enums\Availability;
use App\Enums\DeliveryMethod;
use App\Enums\LeadType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Http\Requests\LeadRequest;
use App\Jobs\SendTelegramMessage;
use App\Mail\OrderPlacedMail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');

    $this->root = Category::factory()->create(['name' => 'Холодильное оборудование', 'slug' => 'holodilnoe', 'show_on_home' => true, 'products_count' => 3]);
    $this->cabinets = Category::factory()->childOf($this->root)->create(['name' => 'Шкафы холодильные', 'slug' => 'shkafy', 'products_count' => 3]);
    $this->abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $this->other = Brand::factory()->create(['name' => 'Polair', 'slug' => 'polair']);
});

function openedSecondsAgo(): string
{
    return Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp());
}

it('leads a guest from the home page to «Спасибо» and tells the manager (scenario 1)', function () {
    User::factory()->create(['role' => UserRole::Manager, 'email' => 'manager@horeca.test']);
    $cabinet = Product::factory()->create([
        'name' => 'Шкаф холодильный ШХс-0,7-02',
        'category_id' => $this->cabinets->id,
        'brand_id' => $this->abat->id,
        'availability' => Availability::InStock,
        'retail_price' => Money::ofRubles(96_750),
    ]);
    Product::factory()->create(['name' => 'Шкаф Polair', 'category_id' => $this->cabinets->id, 'brand_id' => $this->other->id, 'retail_price' => Money::ofRubles(80_000)]);

    $this->get('/')->assertOk()->assertSee(route('category', $this->root));
    $this->get(route('category', $this->root))->assertOk()->assertSee(route('category', $this->cabinets));
    $this->get(route('category', ['category' => $this->cabinets, 'brand' => ['abat']]))
        ->assertOk()
        ->assertSee('Шкаф холодильный ШХс-0,7-02')
        ->assertDontSee('Шкаф Polair');
    $this->get(route('product', $cabinet))->assertOk()->assertSee('Добавить в корзину');

    putInCart($cabinet);
    $this->get(route('cart'))->assertOk()->assertSee('Шкаф холодильный ШХс-0,7-02')->assertSee(route('checkout'));
    $this->get(route('checkout'))->assertOk()->assertSee('Отправить заявку');

    $this->post(route('checkout.store'), [
        'name' => 'Алексей',
        'phone' => '+7 978 123-45-67',
        'delivery_method' => DeliveryMethod::TransportCompany->value,
        'delivery_city' => 'Керчь',
        'tk_name' => 'СДЭК',
        'payment_method' => PaymentMethod::Invoice->value,
        'comment' => 'Нужна доставка до двери',
        'consent' => '1',
        'idempotency_key' => (string) Str::uuid(),
        'started' => openedSecondsAgo(),
        LeadRequest::HONEYPOT => '',
    ])->assertRedirect();

    $order = Order::query()->sole();

    $this->get(route('checkout.success', $order->number))->assertOk()->assertSee($order->number);

    Queue::assertPushed(SendTelegramMessage::class);
    Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $mail): bool => $mail->hasTo('manager@horeca.test'));
});

it('keeps a product without stocks buyable with the term to be confirmed (scenario 7)', function () {
    $inStock = Product::factory()->create(['name' => 'Шкаф в наличии', 'category_id' => $this->cabinets->id, 'availability' => Availability::InStock, 'retail_price' => Money::ofRubles(1_000)]);
    $onOrder = Product::factory()->create(['name' => 'Шкаф под заказ', 'category_id' => $this->cabinets->id, 'availability' => Availability::OnOrder, 'retail_price' => Money::ofRubles(900)]);

    $this->get(route('category', $this->cabinets))
        ->assertSeeInOrder(['Шкаф в наличии', 'Шкаф под заказ'])
        ->assertSee('action="'.route('cart.add', $onOrder->id).'"', false);

    $this->get(route('product', $onOrder))
        ->assertSee('Под заказ')
        ->assertSee('Добавить в корзину')
        ->assertSee('Срок поставки уточнит менеджер')
        ->assertSee('Узнать срок')
        ->assertSee('popovertarget="lead-term"', false);

    putInCart($onOrder);
    $this->get(route('cart'))->assertSee('Срок поставки уточнит менеджер');

    expect($inStock->availability_rank)->toBeLessThan($onOrder->refresh()->availability_rank);
});

it('asks for the price instead of the cart when the supplier has none (scenario 8)', function () {
    $product = Product::factory()->create(['name' => 'Шкаф без цены', 'category_id' => $this->cabinets->id, 'retail_price' => null]);

    $this->get(route('category', $this->cabinets))
        ->assertSee('Цена по запросу')
        ->assertSee('data-lead-product-id="'.$product->id.'"', false)
        ->assertDontSee('action="'.route('cart.add', $product->id).'"', false);

    $this->get(route('product', $product))
        ->assertSee('Запросить цену')
        ->assertDontSee('Добавить в корзину');

    $this->postJson(route('leads.store'), [
        'type' => LeadType::PriceRequest->value,
        'phone' => '+7 978 123-45-67',
        'product_id' => $product->id,
        'consent' => '1',
        'started' => openedSecondsAgo(),
        LeadRequest::HONEYPOT => '',
    ])->assertOk();

    expect(Lead::query()->sole()->only(['type', 'product_id']))->toBe(['type' => LeadType::PriceRequest, 'product_id' => $product->id]);
    Queue::assertPushed(SendTelegramMessage::class);
});
