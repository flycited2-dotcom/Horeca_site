<?php

use App\Enums\Availability;
use App\Enums\CompanyStatus;
use App\Enums\LeadType;
use App\Http\Controllers\BulkOrderController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Lead;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    Queue::fake();
    $this->section = Category::factory()->create(['is_active' => true]);
});

function bulkBuyer(CompanyStatus $status = CompanyStatus::Approved): User
{
    return wholesaleCustomer(PriceTier::factory()->create(), $status);
}

function bulkProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + ['category_id' => test()->section->id]);
}

/**
 * An XLSX file with the given rows, as a customer would upload it.
 *
 * @param  list<list<mixed>>  $rows
 */
function bulkXlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'bulk-test-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return new UploadedFile($path, 'zakaz.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('is open only to customers of an approved company', function () {
    $this->get(route('account.bulk-order'))->assertRedirect(route('login'));

    foreach ([User::factory()->create(), bulkBuyer(CompanyStatus::Pending), bulkBuyer(CompanyStatus::Blocked)] as $user) {
        $this->actingAs($user)->get(route('account.bulk-order'))
            ->assertRedirect(route('wholesale'))
            ->assertSessionHas('notice.text', 'Заказ списком и прайс доступны оптовым клиентам после проверки заявки.');
        $this->actingAs($user)->post(route('account.bulk-order.check'), ['list' => '11000019106;2'])->assertRedirect(route('wholesale'));
    }

    $buyer = bulkBuyer();

    $this->actingAs($buyer)->get(route('account.bulk-order'))
        ->assertOk()
        ->assertSee('name="list"', false)
        ->assertSee('name="file"', false)
        ->assertSee('href="'.route('account.bulk-order.template').'"', false);

    $this->actingAs($buyer)->get(route('account'))->assertSee('href="'.route('account.bulk-order').'"', false);
    $this->actingAs(User::factory()->create())->get(route('account'))->assertDontSee('href="'.route('account.bulk-order').'"', false);
});

it('matches every line by article, then by 1C code, and shows what came out', function () {
    $buyer = bulkBuyer();
    bulkProduct(['sku' => '11000019106', 'name' => 'Пароконвектомат ПКА 10-1/1', 'retail_price' => Money::ofRubles(383_995)]);
    bulkProduct(['sku' => null, 'supplier_code' => 'ЦБ-Ц0012345', 'name' => 'Шкаф холодильный ШХс-0,7']);
    bulkProduct(['sku' => 'SM-25', 'name' => 'Сковорода SM-25 Prima']);
    bulkProduct(['sku' => 'SM25', 'name' => 'Сковорода SM25 Abat']);
    bulkProduct(['sku' => 'КИП-27Н', 'name' => 'Плита индукционная КИП-27Н', 'retail_price' => null]);
    bulkProduct(['sku' => 'МПК-700', 'name' => 'Машина посудомоечная МПК-700', 'availability' => Availability::Discontinued]);
    bulkProduct(['sku' => '555', 'name' => 'Скрытый товар', 'is_visible' => false]);

    $list = implode("\n", [
        '11000019106;2',
        "цб ц0012345\t3",
        '',
        'sm 25;1',
        'КИП 27н',
        'МПК-700;1',
        '555;1',
        '404404;1',
        '11000019106;два',
        ';4',
    ]);

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['list' => $list])->assertRedirect(route('account.bulk-order'));

    $lines = session(BulkOrderController::SESSION_KEY)['lines'];

    expect($lines)->toHaveCount(9)
        ->and($lines[1])->toBe(['row' => 2, 'sku' => 'цб ц0012345', 'qty' => 3, 'error' => null])
        ->and($lines[3]['qty'])->toBe(1)
        ->and($lines[7]['error'])->toBe('qty')
        ->and($lines[8]['error'])->toBe('sku');

    $this->actingAs($buyer)->get(route('account.bulk-order'))
        ->assertOk()
        ->assertSee('9 строк')
        ->assertSeeInOrder(['11000019106', 'Найден', 'Пароконвектомат ПКА 10-1/1', "383\u{00A0}995\u{00A0}₽"])
        ->assertSeeInOrder(['цб ц0012345', 'Найден', 'Шкаф холодильный ШХс-0,7'])
        ->assertSeeInOrder(['sm 25', 'Несколько совпадений', 'Сковорода SM-25 Prima', 'Сковорода SM25 Abat'])
        ->assertSeeInOrder(['КИП 27н', 'Цена по запросу', 'Плита индукционная КИП-27Н'])
        ->assertSeeInOrder(['МПК-700', 'Снят с производства'])
        ->assertSeeInOrder(['555', 'Не найден'])
        ->assertSeeInOrder(['404404', 'Не найден'])
        ->assertSeeInOrder(['Не разобрали строку', 'Количество — целое число от 1 до 9999.'])
        ->assertSee('Нет артикула в строке.')
        ->assertDontSee('Скрытый товар')
        ->assertSee('Запросить цену на эти позиции')
        ->assertSee('name="choice[4]"', false);
});

it('puts the found and the chosen lines into the cart at today\'s prices', function () {
    $buyer = bulkBuyer();
    $oven = bulkProduct(['sku' => '11000019106', 'retail_price' => Money::ofRubles(383_995)]);
    $prima = bulkProduct(['sku' => 'SM-25']);
    $abat = bulkProduct(['sku' => 'SM25']);
    bulkProduct(['sku' => 'КИП-27Н', 'retail_price' => null]);

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['list' => "11000019106;2\nSM-25;3\nКИП-27Н;1\n11000019106;1"]);

    $this->actingAs($buyer)->post(route('account.bulk-order.cart'), ['choice' => [2 => $abat->id]])
        ->assertRedirect(route('cart'))
        ->assertSessionHas('notice.text', 'В корзину добавлено позиций: 3. Не добавлено: 1 — их не нашли, у них цена по запросу или не выбран товар.')
        ->assertSessionMissing(BulkOrderController::SESSION_KEY);

    $items = Cart::query()->where('user_id', $buyer->id)->sole()->items()->get()->keyBy('product_id');

    expect($items)->toHaveCount(2)
        ->and($items[$oven->id]->qty)->toBe(3)
        ->and($items[$oven->id]->price->equals(Money::ofRubles(383_995)))->toBeTrue()
        ->and($items[$abat->id]->qty)->toBe(3)
        ->and($items->has($prima->id))->toBeFalse();
});

it('keeps the list when there is nothing to add', function () {
    $buyer = bulkBuyer();
    bulkProduct(['sku' => 'SM-25']);
    bulkProduct(['sku' => 'SM25']);

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['list' => 'SM-25;3']);

    $this->actingAs($buyer)->post(route('account.bulk-order.cart'), ['choice' => [1 => 999_999]])
        ->assertRedirect(route('account.bulk-order'))
        ->assertSessionHas('notice.text', 'Добавить нечего: выберите товар в строках с несколькими совпадениями или исправьте список.')
        ->assertSessionHas(BulkOrderController::SESSION_KEY);

    expect(Cart::query()->count())->toBe(0);
});

it('asks for prices of the «price on request» lines with one lead', function () {
    $buyer = bulkBuyer();
    $buyer->forceFill(['phone' => '+7 978 123-45-67'])->save();
    bulkProduct(['sku' => 'КИП-27Н', 'name' => 'Плита индукционная КИП-27Н', 'retail_price' => null]);
    bulkProduct(['sku' => 'ПЭ-4', 'name' => 'Плита электрическая ПЭ-4', 'retail_price' => null, 'unit' => 'шт']);
    bulkProduct(['sku' => '11000019106']);

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['list' => "КИП-27Н;2\nПЭ-4;1\n11000019106;1"]);

    $this->actingAs($buyer)->post(route('account.bulk-order.prices'))
        ->assertRedirect(route('account.bulk-order'))
        ->assertSessionHas('notice.text', 'Запрос цены отправлен — менеджер пришлёт цены на позиции «по запросу».');

    $lead = Lead::query()->sole();

    expect($lead->type)->toBe(LeadType::PriceRequest)
        ->and($lead->phone)->toBe('+7 978 123-45-67')
        ->and($lead->email)->toBe($buyer->email)
        ->and($lead->product_id)->toBeNull()
        ->and($lead->message)->toBe("Заказ списком — запрос цены на 2 поз.:\n1) КИП-27Н — Плита индукционная КИП-27Н × 2 шт\n2) ПЭ-4 — Плита электрическая ПЭ-4 × 1 шт");
});

it('reads an XLSX file: heading skipped, numbers as articles', function () {
    $buyer = bulkBuyer();
    $oven = bulkProduct(['sku' => '11000019106']);

    $file = bulkXlsx([['Артикул', 'Количество'], [11000019106, 4], ['', ''], ['404404', '1']]);

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['file' => $file])->assertRedirect(route('account.bulk-order'));

    $saved = session(BulkOrderController::SESSION_KEY);

    expect($saved['source'])->toBe('zakaz.xlsx')
        ->and($saved['lines'])->toBe([
            ['row' => 2, 'sku' => '11000019106', 'qty' => 4, 'error' => null],
            ['row' => 4, 'sku' => '404404', 'qty' => 1, 'error' => null],
        ]);

    $this->actingAs($buyer)->get(route('account.bulk-order'))
        ->assertSee('Файл «zakaz.xlsx»')
        ->assertSeeInOrder(['11000019106', 'Найден', $oven->name]);
});

it('refuses an empty list, a list over 500 lines and a file that is not XLSX', function () {
    $buyer = bulkBuyer();

    $this->actingAs($buyer)->from(route('account.bulk-order'))
        ->post(route('account.bulk-order.check'), ['list' => ''])
        ->assertSessionHasErrors(['list' => 'Вставьте строки с артикулами или выберите файл XLSX.']);

    $this->actingAs($buyer)->from(route('account.bulk-order'))
        ->post(route('account.bulk-order.check'), ['list' => implode("\n", array_fill(0, 501, '11000019106;1'))])
        ->assertSessionHasErrors(['list' => 'В списке больше 500 строк. Разбейте его на части.']);

    $this->actingAs($buyer)->from(route('account.bulk-order'))
        ->post(route('account.bulk-order.check'), ['file' => UploadedFile::fake()->create('zakaz.txt', 3, 'text/plain')])
        ->assertSessionHasErrors('file');

    $this->actingAs($buyer)->from(route('account.bulk-order'))
        ->post(route('account.bulk-order.check'), ['file' => UploadedFile::fake()->create('zakaz.xlsx', 2048, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
        ->assertSessionHasErrors('file');

    expect(session(BulkOrderController::SESSION_KEY))->toBeNull();
});

it('gives an example file and starts a new list', function () {
    $buyer = bulkBuyer();

    $this->actingAs($buyer)->get(route('account.bulk-order.template'))
        ->assertOk()
        ->assertDownload('zakaz-spiskom.xlsx');

    $this->actingAs($buyer)->post(route('account.bulk-order.check'), ['list' => '11000019106;1']);
    $this->actingAs($buyer)->delete(route('account.bulk-order.reset'))->assertRedirect(route('account.bulk-order'));

    expect(session(BulkOrderController::SESSION_KEY))->toBeNull();
});
