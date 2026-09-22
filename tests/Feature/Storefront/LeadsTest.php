<?php

use App\Enums\Availability;
use App\Enums\LeadType;
use App\Http\Requests\LeadRequest;
use App\Jobs\SendTelegramMessage;
use App\Models\Category;
use App\Models\Lead;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Telegram is configured, so every lead queues a message: the queue is faked, nothing leaves the test.
    Queue::fake();
    $this->category = Category::factory()->create(['slug' => 'shkafy']);
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leadForm(array $overrides = []): array
{
    return $overrides + [
        'type' => LeadType::PriceRequest->value,
        'name' => 'Ирина',
        'phone' => '89781234567',
        'message' => 'Нужно две штуки',
        'consent' => '1',
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        LeadRequest::HONEYPOT => '',
    ];
}

it('offers the forms a product needs on its page', function (array $attributes, array $dialogs, array $absent) {
    $product = Product::factory()->create($attributes + ['category_id' => $this->category->id]);

    $response = $this->get(route('product', $product))->assertOk();

    foreach ($dialogs as $dialog) {
        $response->assertSee('id="'.$dialog.'" popover', false)->assertSee('popovertarget="'.$dialog.'"', false);
    }

    foreach ($absent as $dialog) {
        $response->assertDontSee('id="'.$dialog.'"', false);
    }
})->with([
    'price on request' => [['retail_price' => null], ['lead-price'], ['lead-one-click']],
    'on order' => [['retail_price' => Money::ofRubles(100), 'availability' => Availability::OnOrder], ['lead-one-click', 'lead-term'], ['lead-price']],
    'in stock' => [['retail_price' => Money::ofRubles(100), 'availability' => Availability::InStock], ['lead-one-click'], ['lead-term']],
    'discontinued' => [['retail_price' => Money::ofRubles(100), 'availability' => Availability::Discontinued], ['lead-analog'], ['lead-one-click']],
]);

it('asks for the price from a listing through one window of the page', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'retail_price' => null, 'name' => 'Шкаф без цены']);

    $this->get(route('category', $this->category))
        ->assertSee('id="lead-price-shared" popover', false)
        ->assertSee('data-lead-product-id="'.$product->id.'"', false)
        ->assertSee('popovertarget="lead-price-shared"', false);
});

it('keeps the lead with its product and tells the managers without the contacts', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Шкаф холодильный', 'sku' => '11000018820']);
    $this->get('/?utm_source=avito');

    $this->postJson(route('leads.store'), leadForm(['product_id' => $product->id]))
        ->assertOk()
        ->assertJson(['notice' => ['text' => 'Заявка принята — менеджер свяжется с вами в рабочее время.']]);

    $lead = Lead::query()->sole();

    expect($lead->type)->toBe(LeadType::PriceRequest)
        ->and($lead->phone)->toBe('+7 978 123-45-67')
        ->and($lead->product_id)->toBe($product->id)
        ->and($lead->message)->toBe('Нужно две штуки')
        ->and($lead->utm)->toBe(['utm_source' => 'avito']);

    Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job): bool {
        $message = (fn () => $this->message)->call($job);

        return str_contains($message, 'Лид: запрос цены')
            && str_contains($message, 'Шкаф холодильный (11000018820)')
            && ! str_contains($message, 'Ирина')
            && ! str_contains($message, '978');
    });
});

it('names what to fix for the script', function () {
    $this->postJson(route('leads.store'), leadForm(['phone' => '12', 'consent' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'phone' => 'Нужен российский номер: +7 и десять цифр, например +7 978 123-45-67.',
            'consent' => 'Отметьте согласие на обработку персональных данных.',
        ]);

    expect(Lead::query()->count())->toBe(0);
});

it('goes back with a notice when scripts are off', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'retail_price' => null]);

    $this->from(route('product', $product))
        ->post(route('leads.store'), leadForm(['product_id' => $product->id]))
        ->assertRedirect(route('product', $product))
        ->assertSessionHas('notice.text');

    $this->from(route('product', $product))
        ->post(route('leads.store'), leadForm(['phone' => '12']))
        ->assertRedirect(route('product', $product))
        ->assertSessionHasErrorsIn('lead', ['phone']);

    // The next page shows the error in a notice. The session is JSON-serialized: between real
    // requests the errors travel as arrays, so the page is opened with them in that shape.
    $this->withSession(['errors' => ['lead' => ['format' => ':message', 'messages' => ['phone' => ['Нужен российский номер: +7 и десять цифр.']]]]])
        ->get(route('product', $product))
        ->assertSee('Заявка не отправлена: Нужен российский номер: +7 и десять цифр.');
});

it('refuses a robot', function () {
    $this->postJson(route('leads.store'), leadForm([LeadRequest::HONEYPOT => 'spam']))->assertStatus(422)->assertJsonValidationErrors('form');
    $this->postJson(route('leads.store'), leadForm(['started' => Crypt::encryptString((string) time())]))->assertStatus(422);

    expect(Lead::query()->count())->toBe(0);
});

it('takes ten leads an hour from one address', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('leads.store'), leadForm())->assertOk();
    }

    $this->postJson(route('leads.store'), leadForm())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['form' => 'С этого адреса уже отправлено много заявок за час. Позвоните нам — поможем по телефону.']);

    expect(Lead::query()->count())->toBe(10);
});

it('offers to find what the search could not', function () {
    $this->get(route('search', ['q' => 'Плита индукционная Hendi 1234']))
        ->assertOk()
        ->assertSee('Найдём за вас')
        ->assertSee('name="type" value="not_found"', false)
        ->assertSee('Ищу: Плита индукционная Hendi 1234');

    $this->postJson(route('leads.store'), leadForm(['type' => LeadType::NotFound->value, 'message' => 'Ищу: Плита индукционная Hendi 1234']))
        ->assertJson(['notice' => ['text' => 'Заявка принята — поищем и перезвоним.']]);

    expect(Lead::query()->sole()->type)->toBe(LeadType::NotFound);
});
