<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Actions\Catalog\MergeBrands;
use App\Models\Brand;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.brand.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country')
                    ->label(__('admin.brand.country'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label(__('admin.brand.products_count'))
                    ->counts('products')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('admin.brand.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.brand.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('merge')
                        ->label(__('admin.brand.merge'))
                        ->icon(Heroicon::OutlinedArrowsPointingIn)
                        ->modalDescription(fn (Brand $record): string => __('admin.brand.merge_hint', ['brand' => $record->name]))
                        ->schema(fn (Brand $record): array => [
                            Select::make('target_id')
                                ->label(__('admin.brand.merge_target'))
                                ->options(fn (): array => Brand::query()
                                    ->whereKeyNot($record->id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Brand $record, array $data, MergeBrands $merge): void {
                            $moved = $merge->handle($record, Brand::query()->findOrFail($data['target_id']));

                            Notification::make()
                                ->title(__('admin.brand.merged', ['count' => $moved]))
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make(),
                ]),
            ]);
    }
}
