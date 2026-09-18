<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Actions\Catalog\RecalculateCategoryCounts;
use App\Actions\Catalog\SetCategoriesActive;
use App\Models\Category;
use App\Services\Catalog\CategoryTree;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Categories as a flat list with the full path: "Холодильное оборудование › Шкафы".
 * The path comes from CategoryTree, read once per request, so rows cost no queries.
 */
class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label(__('admin.category.path'))
                    ->state(fn (Category $record, CategoryTree $tree): string => $tree->path($record->id))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%"))
                    ->wrap(),

                TextColumn::make('products_count')
                    ->label(__('admin.category.products_count'))
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('admin.category.is_active'))
                    ->boolean(),

                IconColumn::make('show_on_home')
                    ->label(__('admin.category.show_on_home'))
                    ->boolean(),

                TextColumn::make('sort')
                    ->label(__('admin.category.sort'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.category.is_active')),

                TernaryFilter::make('show_on_home')
                    ->label(__('admin.category.show_on_home')),

                SelectFilter::make('branch')
                    ->label(__('admin.category.parent'))
                    ->options(fn (CategoryTree $tree): array => $tree->paths())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereIn('id', app(CategoryTree::class)->branch((int) $data['value']))),

                Filter::make('roots')
                    ->label(__('admin.category.filters.roots'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id'))
                    ->toggle(),

                Filter::make('empty')
                    ->label(__('admin.category.filters.empty'))
                    ->query(fn (Builder $query): Builder => $query->where('products_count', 0))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::toggle('activate', __('admin.category.bulk.activate'), Heroicon::OutlinedEye, true),
                    self::toggle('deactivate', __('admin.category.bulk.deactivate'), Heroicon::OutlinedEyeSlash, false),
                    DeleteBulkAction::make()
                        ->after(fn (RecalculateCategoryCounts $counts) => $counts->handle()),
                ]),
            ]);
    }

    private static function toggle(string $name, string $label, Heroicon $icon, bool $active): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->action(function (Collection $records, SetCategoriesActive $setActive) use ($active): void {
                $changed = $setActive->handle($records->modelKeys(), $active);

                Notification::make()
                    ->title(__('admin.category.bulk.done', ['count' => $changed]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
