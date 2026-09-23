<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(staffUser());
    $this->section = Category::factory()->create(['is_active' => true]);
    $this->oven = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Пароконвектомат ПКА 10-1/1']);
});

it('lets the manager pick products bought together and shows them on the product page', function () {
    $stand = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Подставка ПК-10']);
    $filter = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Фильтр для воды']);

    Livewire::test(RelatedProductsRelationManager::class, ['ownerRecord' => $this->oven, 'pageClass' => EditProduct::class])
        ->callTableAction(AttachAction::class, data: ['recordId' => [$stand->id, $filter->id]])
        ->assertHasNoTableActionErrors()
        ->assertCanSeeTableRecords([$stand, $filter]);

    expect($this->oven->relatedProducts()->pluck('products.id')->sort()->values()->all())
        ->toBe(collect([$stand->id, $filter->id])->sort()->values()->all());

    $this->get(route('product', $this->oven))
        ->assertOk()
        ->assertSee('Часто берут вместе')
        ->assertSee('Подставка ПК-10')
        ->assertSee('Фильтр для воды');

    Livewire::test(RelatedProductsRelationManager::class, ['ownerRecord' => $this->oven, 'pageClass' => EditProduct::class])
        ->callTableAction(DetachAction::class, $filter);

    expect($this->oven->relatedProducts()->pluck('products.id')->all())->toBe([$stand->id]);
});
