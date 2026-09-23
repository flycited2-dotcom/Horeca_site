<?php

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Events\CompanyApproved;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\PriceTiers\Pages\CreatePriceTier;
use App\Filament\Resources\PriceTiers\Pages\ListPriceTiers;
use App\Filament\Resources\PriceTiers\PriceTierResource;
use App\Mail\WholesaleApprovedMail;
use App\Models\Company;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\User;
use App\Support\Money;
use App\Support\Percent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = staffUser();
    $this->actingAs($this->manager);
    $this->tier = PriceTier::factory()->create(['name' => 'Опт-1', 'slug' => 'opt-1', 'discount_percent' => Percent::fromDecimal('10'), 'is_default' => true]);
});

/**
 * A wholesale application: the company and the customer who sent it.
 */
function applicant(array $attributes = []): Company
{
    $company = Company::factory()->create($attributes + [
        'legal_name' => 'ООО «Вкусный дом»',
        'inn' => '7714365426',
        'email' => 'zakupki@vkusnydom.ru',
        'phone' => '+7 917 220-44-21',
    ]);
    $user = User::factory()->create(['email' => 'irina@vkusnydom.ru']);
    $user->company()->associate($company)->save();

    return $company;
}

it('shows the applications first, then the card with the phone link and the edit form', function () {
    $pending = applicant();
    $approved = Company::factory()->create(['status' => CompanyStatus::Approved, 'legal_name' => 'ИП Петров']);

    Livewire::test(ListCompanies::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$approved])
        ->set('activeTab', 'approved')
        ->assertCanSeeTableRecords([$approved]);

    $this->get(CompanyResource::getUrl('view', ['record' => $pending]))
        ->assertOk()
        ->assertSee('tel:+79172204421', false)
        ->assertSee('irina@vkusnydom.ru');
    $this->get(CompanyResource::getUrl('edit', ['record' => $pending]))->assertOk();
});

it('lists companies with a fixed number of queries', function () {
    Company::factory()->count(25)->create(['price_tier_id' => $this->tier->id]);

    DB::enableQueryLog();
    Livewire::test(ListCompanies::class)->set('activeTab', 'all');
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(20);
});

it('approves with a price tier and tells the customer once', function () {
    Mail::fake();
    $company = applicant();

    // The default tier is offered first.
    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->mountAction('approve')
        ->assertActionDataSet(['price_tier_id' => $this->tier->id]);

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('approve', data: ['price_tier_id' => null])
        ->assertHasActionErrors(['price_tier_id' => 'required']);
    expect($company->refresh()->status)->toBe(CompanyStatus::Pending);

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('approve', data: ['price_tier_id' => $this->tier->id, 'comment' => 'Проверили по ЕГРЮЛ'])
        ->assertHasNoActionErrors();

    $company->refresh();

    expect($company->status)->toBe(CompanyStatus::Approved)
        ->and($company->price_tier_id)->toBe($this->tier->id)
        ->and($company->approved_by)->toBe($this->manager->id)
        ->and($company->approved_at)->not->toBeNull()
        ->and($company->manager_comment)->toBe('Проверили по ЕГРЮЛ');

    Mail::assertQueued(WholesaleApprovedMail::class, fn (WholesaleApprovedMail $mail): bool => $mail->hasTo('zakupki@vkusnydom.ru') && $mail->hasTo('irina@vkusnydom.ru'));

    // Moving an approved company to another tier does not write again.
    $opt2 = PriceTier::factory()->create(['name' => 'Опт-2', 'slug' => 'opt-2']);
    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('approve', data: ['price_tier_id' => $opt2->id]);

    expect($company->refresh()->price_tier_id)->toBe($opt2->id);
    Mail::assertQueuedCount(1);
});

it('writes «Оптовые цены открыты» with the company name', function () {
    $mail = new WholesaleApprovedMail(Company::factory()->make(['legal_name' => 'ООО «Вкусный дом»']));

    $mail->assertHasSubject('Оптовые цены открыты')
        ->assertSeeInHtml('Компания «ООО «Вкусный дом»» проверена.', false)
        ->assertSeeInHtml(route('catalog'), false);
});

it('rejects and blocks only with a reason, and returns to moderation without one', function () {
    Event::fake([CompanyApproved::class]);
    $company = applicant();

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('reject', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('reject', data: ['reason' => 'ИНН не совпадает с названием']);
    expect($company->refresh()->status)->toBe(CompanyStatus::Rejected)
        ->and($company->manager_comment)->toBe('ИНН не совпадает с названием');

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])->callAction('to_pending');
    expect($company->refresh()->status)->toBe(CompanyStatus::Pending);

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->callAction('block', data: ['reason' => 'Не платит по счетам']);
    expect($company->refresh()->status)->toBe(CompanyStatus::Blocked);

    Event::assertNotDispatched(CompanyApproved::class);
});

it('lets the manager fix the requisites, checking the INN and the phone', function () {
    $company = applicant();

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm(['inn' => '7714365420'])
        ->call('save')
        ->assertHasFormErrors(['inn']);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm(['legal_name' => 'ООО «Вкусный дом плюс»', 'phone' => '89172204421', 'kpp' => '771401001'])
        ->call('save')
        ->assertHasNoFormErrors();

    $company->refresh();

    expect($company->legal_name)->toBe('ООО «Вкусный дом плюс»')
        ->and($company->phone)->toBe('+7 917 220-44-21')
        ->and($company->kpp)->toBe('771401001')
        ->and($company->status)->toBe(CompanyStatus::Pending);
});

it('shows the orders of the company', function () {
    $company = applicant();
    $order = Order::factory()->create(['company_id' => $company->id, 'total' => Money::ofRubles(51_794)]);
    $other = Order::factory()->create();

    Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $company, 'pageClass' => ViewCompany::class])
        ->assertCanSeeTableRecords([$order])
        ->assertCanNotSeeTableRecords([$other]);
});

it('lets only an administrator change price tiers, keeping one default', function () {
    $this->get(PriceTierResource::getUrl('index'))->assertOk();
    $this->get(PriceTierResource::getUrl('create'))->assertForbidden();

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(CreatePriceTier::class)
        ->fillForm(['name' => 'Сеть', 'slug' => 'chain', 'discount_percent' => '12.5', 'min_order_amount' => '150000', 'is_default' => true, 'sort' => 30])
        ->call('create')
        ->assertHasNoFormErrors();

    $chain = PriceTier::query()->where('slug', 'chain')->sole();

    expect($chain->discount_percent->toDecimal())->toBe('12.50')
        ->and($chain->min_order_amount->toDecimal())->toBe('150000.00')
        ->and($chain->is_default)->toBeTrue()
        ->and($this->tier->refresh()->is_default)->toBeFalse();

    Livewire::test(ListPriceTiers::class)->assertCanSeeTableRecords([$chain, $this->tier]);
});

it('keeps customers out of the companies and price tiers', function () {
    $this->actingAs(User::factory()->create());

    $this->get(CompanyResource::getUrl('index'))->assertForbidden();
    $this->get(PriceTierResource::getUrl('index'))->assertForbidden();
});
