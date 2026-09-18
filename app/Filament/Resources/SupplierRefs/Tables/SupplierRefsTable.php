<?php

namespace App\Filament\Resources\SupplierRefs\Tables;

use App\Enums\SupplierRefEntity;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\SupplierRef;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogCache;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierRefsTable
{
    /**
     * Names of our records by entity, read once per request: the table would otherwise
     * ask the database for every row (TZ, N+1 запрещён).
     *
     * @var array<string, array<int, string>>
     */
    private static array $names = [];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('admin.supplier_ref.supplier'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('entity')
                    ->label(__('admin.supplier_ref.entity'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label(__('admin.supplier_ref.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('local')
                    ->label(__('admin.supplier_ref.local'))
                    ->state(fn (SupplierRef $record): string => self::localName($record) ?? __('admin.supplier_ref.local_missing'))
                    ->color(fn (SupplierRef $record): string => self::localName($record) === null ? 'danger' : 'gray'),

                IconColumn::make('is_ignored')
                    ->label(__('admin.supplier_ref.is_ignored'))
                    ->boolean(),

                TextColumn::make('last_seen_at')
                    ->label(__('admin.supplier_ref.last_seen_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label(__('admin.supplier_ref.supplier'))
                    ->relationship('supplier', 'name'),

                TernaryFilter::make('unmapped')
                    ->label(__('admin.supplier_ref.unmapped'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('local_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('local_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                TernaryFilter::make('is_ignored')
                    ->label(__('admin.supplier_ref.is_ignored')),
            ])
            ->recordActions([
                Action::make('match')
                    ->label(__('admin.supplier_ref.match'))
                    ->schema(fn (SupplierRef $record): array => [
                        Select::make('local_id')
                            ->label(__('admin.supplier_ref.local'))
                            ->options(fn (): array => self::options($record))
                            ->searchable()
                            ->native(false)
                            ->required(),
                    ])
                    ->fillForm(fn (SupplierRef $record): array => ['local_id' => $record->local_id])
                    ->action(function (SupplierRef $record, array $data, CatalogCache $cache): void {
                        $record->forceFill(['local_id' => (int) $data['local_id']])->save();
                        self::$names = [];
                        $cache->bump();

                        Notification::make()->title(__('admin.supplier_ref.matched'))->success()->send();
                    }),

                Action::make('toggle_ignored')
                    ->label(fn (SupplierRef $record): string => $record->is_ignored
                        ? __('admin.supplier_ref.unignore')
                        : __('admin.supplier_ref.ignore'))
                    ->requiresConfirmation()
                    ->action(function (SupplierRef $record, CatalogCache $cache): void {
                        $record->forceFill(['is_ignored' => ! $record->is_ignored])->save();
                        $cache->bump();
                    }),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function options(SupplierRef $ref): array
    {
        return match ($ref->entity) {
            SupplierRefEntity::Category => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
            SupplierRefEntity::Brand => Brand::query()->orderBy('name')->pluck('name', 'id')->all(),
            SupplierRefEntity::Warehouse => Warehouse::query()->where('supplier_id', $ref->supplier_id)->orderBy('name')->pluck('name', 'id')->all(),
            SupplierRefEntity::Attribute => Attribute::query()->orderBy('name')->pluck('name', 'id')->all(),
        };
    }

    private static function localName(SupplierRef $ref): ?string
    {
        if ($ref->local_id === null) {
            return null;
        }

        $key = $ref->entity->value.':'.$ref->supplier_id;
        self::$names[$key] ??= self::options($ref);

        return self::$names[$key][$ref->local_id] ?? null;
    }
}
