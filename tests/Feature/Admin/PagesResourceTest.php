<?php

use App\Enums\UserRole;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use Database\Seeders\ProductionSeeder;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ProductionSeeder::class);
    $this->actingAs(staffUser(UserRole::Admin));
});

it('lists the required pages to an administrator only', function () {
    Livewire::test(ListPages::class)
        ->assertCanSeeTableRecords(Page::query()->get())
        ->assertSee('Доставка')
        ->assertSee('soglasie-na-obrabotku-personalnyh-dannyh');

    $this->actingAs(staffUser(UserRole::Manager))->get(PageResource::getUrl('index'))->assertForbidden();
});

it('creates a page in Markdown that opens on the site at its address', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Сервис',
            'slug' => 'servis',
            'content' => "## Ремонт\n\nВыезд мастера в день заявки. <script>alert(1)</script>",
            'is_active' => true,
            'meta_description' => 'Ремонт оборудования для кафе.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->get('/servis')
        ->assertOk()
        ->assertSee('<h2>Ремонт</h2>', false)
        ->assertSee('Выезд мастера в день заявки.')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('refuses an address taken by the site, a wrong address and an empty active page', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Каталог', 'slug' => 'catalog', 'content' => 'Текст'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Сервис', 'slug' => 'Servis page', 'content' => 'Текст'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'regex']);

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Сервис', 'slug' => 'servis', 'content' => '', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['content']);

    expect(Page::query()->where('slug', 'servis')->exists())->toBeFalse();
});

it('keeps the address of a required page and never deletes it', function () {
    $delivery = Page::query()->where('slug', 'dostavka')->sole();

    Livewire::test(EditPage::class, ['record' => $delivery->getRouteKey()])
        ->assertFormFieldIsDisabled('slug')
        ->assertActionHidden(DeleteAction::class)
        ->fillForm(['content' => 'По Симферополю — в день заказа.', 'is_active' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($delivery->refresh()->slug)->toBe('dostavka')
        ->and($delivery->is_active)->toBeTrue();

    $this->get('/dostavka')->assertOk()->assertSee('По Симферополю — в день заказа.');
});

it('leads the old address of a renamed page to the new one and deletes an own page', function () {
    $page = Page::factory()->create(['slug' => 'servis', 'title' => 'Сервис', 'content' => 'Текст', 'is_active' => true]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['slug' => 'remont'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get('/servis')->assertRedirect('/remont')->assertStatus(301);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->callAction(DeleteAction::class);

    expect(Page::query()->whereKey($page->id)->exists())->toBeFalse();
});
