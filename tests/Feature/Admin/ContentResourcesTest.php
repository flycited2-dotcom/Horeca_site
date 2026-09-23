<?php

use App\Enums\AttributeType;
use App\Enums\UserRole;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Redirect;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(staffUser());
});

it('creates a redirect from a normalised address and follows it on the site', function () {
    Livewire::test(CreateRedirect::class)
        ->fillForm(['from_path' => config('app.url').'/old/Pechi/', 'to_path' => '/catalog/pechi', 'status_code' => 301])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Redirect::query()->sole()->only(['from_path', 'to_path', 'status_code']))
        ->toBe(['from_path' => '/old/Pechi', 'to_path' => '/catalog/pechi', 'status_code' => 301]);

    $this->get('/old/Pechi')->assertRedirect('/catalog/pechi')->assertStatus(301);
});

it('leads straight to the end of a chain and refuses a loop or a taken address', function () {
    Redirect::factory()->create(['from_path' => '/b', 'to_path' => '/c']);
    Redirect::factory()->create(['from_path' => '/z', 'to_path' => '/a']);

    Livewire::test(CreateRedirect::class)
        ->fillForm(['from_path' => '/a', 'to_path' => '/b', 'status_code' => 301])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Redirect::query()->where('from_path', '/a')->value('to_path'))->toBe('/c')
        ->and(Redirect::query()->where('from_path', '/z')->value('to_path'))->toBe('/c');

    Livewire::test(CreateRedirect::class)
        ->fillForm(['from_path' => '/c', 'to_path' => '/a', 'status_code' => 301])
        ->call('create')
        ->assertHasErrors(['data.to_path']);

    Livewire::test(CreateRedirect::class)
        ->fillForm(['from_path' => '/a/', 'to_path' => '/d', 'status_code' => 302])
        ->call('create')
        ->assertHasErrors(['data.from_path']);

    $redirect = Redirect::query()->where('from_path', '/a')->sole();

    Livewire::test(EditRedirect::class, ['record' => $redirect->getRouteKey()])
        ->fillForm(['to_path' => 'https://old.example.ru/page'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($redirect->refresh()->to_path)->toBe('https://old.example.ru/page');
});

it('lets the manager set up characteristics for filters and keeps the type of used ones', function () {
    Livewire::test(CreateAttribute::class)
        ->fillForm(['name' => 'Мощность', 'slug' => 'moshchnost', 'type' => AttributeType::Number->value, 'unit' => 'кВт', 'is_filterable' => true, 'sort' => 10])
        ->call('create')
        ->assertHasNoFormErrors();

    $power = Attribute::query()->where('slug', 'moshchnost')->sole();

    expect($power->type)->toBe(AttributeType::Number)
        ->and($power->is_filterable)->toBeTrue();

    $power->products()->attach(Product::factory()->create()->id, ['value_number' => 7]);

    Livewire::test(EditAttribute::class, ['record' => $power->getRouteKey()])
        ->assertFormFieldIsDisabled('type')
        ->assertActionHidden(DeleteAction::class);

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(EditAttribute::class, ['record' => $power->getRouteKey()])->assertActionVisible(DeleteAction::class);
});
