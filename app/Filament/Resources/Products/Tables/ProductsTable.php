<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Catalog\ExportProductsXlsx;
use App\Actions\Catalog\SaveProductByManager;
use App\Enums\Availability;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Product;
use App\Services\Catalog\CategoryTree;
use App\Support\Money;
use App\Support\Typography;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The product list of TZ §12: columns, filters and bulk actions of the manager.
 */
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label(__('admin.product.photo'))
                    ->state(fn (Product $record): ?string => $record->getFirstMediaUrl(Product::IMAGES, 'thumb') ?: null)
                    ->square()
                    ->imageSize(40),

                TextColumn::make('sku')
                    ->label(__('admin.product.sku'))
                    ->searchable()
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('supplier_code')
                    ->label(__('admin.product.supplier_code'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('name')
                    ->label(__('admin.product.name'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2),

                TextColumn::make('category.name')
                    ->label(__('admin.product.category'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label(__('admin.product.brand'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('rrp_price')
                    ->label(__('admin.product.rrp_price'))
                    ->formatStateUsing(fn (?Money $state): string => $state === null ? '—' : Typography::money($state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('retail_price')
                    ->label(__('admin.product.retail_price'))
                    ->state(fn (Product $record): string => $record->retail_price === null
                        ? __('admin.product.price_on_request')
                        : Typography::money($record->retail_price))
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('retail_price', $direction)),

                TextColumn::make('availability')
                    ->label(__('admin.product.availability'))
                    ->badge()
                    ->color(fn (Availability $state): string => ProductForm::availabilityColor($state)),

                IconColumn::make('is_visible')
                    ->label(__('admin.product.is_visible'))
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('admin.product.category'))
                    ->options(fn (CategoryTree $tree): array => $tree->paths())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereIn('category_id', app(CategoryTree::class)->branch((int) $data['value']))),

                SelectFilter::make('brand_id')
                    ->label(__('admin.product.brand'))
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('availability')
                    ->label(__('admin.product.availability'))
                    ->options(Availability::class)
                    ->multiple(),

                Filter::make('price_on_request')
                    ->label(__('admin.product.filters.price_on_request'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('retail_price'))
                    ->toggle(),

                Filter::make('without_photo')
                    ->label(__('admin.product.filters.without_photo'))
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('media'))
                    ->toggle(),

                Filter::make('without_category')
                    ->label(__('admin.product.filters.without_category'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('category_id'))
                    ->toggle(),

                Filter::make('discontinued')
                    ->label(__('admin.product.filters.discontinued'))
                    ->query(fn (Builder $query): Builder => $query->where('availability', Availability::Discontinued))
                    ->toggle(),

                Filter::make('locked')
                    ->label(__('admin.product.filters.locked'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('locked_fields'))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::change('show', __('admin.product.bulk.show'), Heroicon::OutlinedEye, ['is_visible' => true]),
                    self::change('hide', __('admin.product.bulk.hide'), Heroicon::OutlinedEyeSlash, ['is_visible' => false]),
                    self::change('mark_hit', __('admin.product.bulk.mark_hit'), Heroicon::OutlinedFire, ['is_hit' => true]),
                    self::change('unmark_hit', __('admin.product.bulk.unmark_hit'), Heroicon::OutlinedFire, ['is_hit' => false]),
                    self::change('mark_new', __('admin.product.bulk.mark_new'), Heroicon::OutlinedSparkles, ['is_new' => true]),
                    self::change('unmark_new', __('admin.product.bulk.unmark_new'), Heroicon::OutlinedSparkles, ['is_new' => false]),

                    BulkAction::make('move')
                        ->label(__('admin.product.bulk.move'))
                        ->icon(Heroicon::OutlinedFolderArrowDown)
                        ->schema([
                            Select::make('category_id')
                                ->label(__('admin.product.category'))
                                ->options(fn (CategoryTree $tree): array => $tree->paths())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, SaveProductByManager $save): void {
                            $save->handleMany($records, ['category_id' => (int) $data['category_id']]);
                            self::done($records->count());
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('export')
                        ->label(__('admin.product.bulk.export'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(fn (Collection $records, ExportProductsXlsx $export): BinaryFileResponse => $export->handle(
                            $records->modelKeys(),
                        )),

                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * A bulk change of storefront flags. It goes through SaveProductByManager, so the
     * catalog cache is refreshed and import-owned fields would be locked.
     *
     * @param  array<string, bool>  $attributes
     */
    private static function change(string $name, string $label, Heroicon $icon, array $attributes): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->action(function (Collection $records, SaveProductByManager $save) use ($attributes): void {
                $save->handleMany($records, $attributes);
                self::done($records->count());
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function done(int $count): void
    {
        Notification::make()
            ->title(__('admin.product.bulk.done', ['count' => $count]))
            ->success()
            ->send();
    }
}
