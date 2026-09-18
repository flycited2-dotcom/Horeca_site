<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Actions\Catalog\RestoreSupplierValue;
use App\Actions\Catalog\SaveProductByManager;
use App\Enums\Availability;
use App\Models\PriceTier;
use App\Models\Product;
use App\Services\Catalog\CategoryTree;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The product card of the admin panel (TZ §12): Основное · Цены · Параметры · SEO.
 * Warehouse stocks and characteristics are the relation managers below the form.
 *
 * A field the manager has changed is marked with a lock, and the lock offers to give the
 * field back to the supplier (TZ §6.6).
 */
class ProductForm
{
    /**
     * A price in rubles with kopecks: "48605.80".
     */
    private const string MONEY_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('admin.product.tabs.main'))->schema(self::main()),
                        Tab::make(__('admin.product.tabs.prices'))->schema(self::prices()),
                        Tab::make(__('admin.product.tabs.details'))->schema(self::details()),
                        Tab::make(__('admin.product.tabs.seo'))->schema(self::seo()),
                    ]),
            ]);
    }

    /**
     * @return list<mixed>
     */
    private static function main(): array
    {
        return [
            Grid::make(4)->schema([
                TextEntry::make('availability')
                    ->label(__('admin.product.availability'))
                    ->badge()
                    ->color(fn (Availability $state): string => self::availabilityColor($state)),
                TextEntry::make('supplier.name')
                    ->label(__('admin.supplier.label')),
                TextEntry::make('external_id')
                    ->label(__('admin.product.external_id'))
                    ->copyable(),
                TextEntry::make('last_synced_at')
                    ->label(__('admin.product.last_synced_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ]),

            Grid::make(2)->schema([
                self::lockable(
                    TextInput::make('name')
                        ->label(__('admin.product.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ),

                TextInput::make('slug')
                    ->label(__('admin.product.slug'))
                    ->helperText(__('admin.product.slug_hint'))
                    ->required()
                    ->maxLength(160)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),

                self::lockable(
                    TextInput::make('model')
                        ->label(__('admin.product.model'))
                        ->maxLength(255),
                ),

                self::lockable(
                    Select::make('category_id')
                        ->label(__('admin.product.category'))
                        ->options(fn (CategoryTree $tree): array => $tree->paths())
                        ->searchable(),
                ),

                self::lockable(
                    Select::make('brand_id')
                        ->label(__('admin.product.brand'))
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload(),
                ),

                self::lockable(
                    TextInput::make('sku')
                        ->label(__('admin.product.sku'))
                        ->maxLength(64),
                ),

                self::lockable(
                    TextInput::make('supplier_code')
                        ->label(__('admin.product.supplier_code'))
                        ->maxLength(64),
                ),

                self::lockable(
                    TextInput::make('unit')
                        ->label(__('admin.product.unit'))
                        ->required()
                        ->maxLength(16),
                ),
            ]),

            Textarea::make('short_description')
                ->label(__('admin.product.short_description'))
                ->rows(2),

            self::lockable(
                Textarea::make('description')
                    ->label(__('admin.product.description'))
                    ->rows(8),
            ),

            Grid::make(3)->schema([
                Toggle::make('is_visible')->label(__('admin.product.is_visible')),
                Toggle::make('is_hit')->label(__('admin.product.is_hit')),
                Toggle::make('is_new')->label(__('admin.product.is_new')),
            ]),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function prices(): array
    {
        return [
            Grid::make(2)->schema([
                self::lockable(self::money('rrp_price', __('admin.product.rrp_price'))),
                self::lockable(self::money('purchase_price', __('admin.product.purchase_price'))),
                self::lockable(
                    self::money('retail_price', __('admin.product.retail_price'))
                        ->helperText(__('admin.product.retail_price_hint')),
                ),
                self::money('old_price', __('admin.product.old_price')),
            ]),

            Repeater::make('prices')
                ->label(__('admin.product.tier_prices'))
                ->relationship()
                ->columns(2)
                ->defaultItems(0)
                ->schema([
                    Select::make('price_tier_id')
                        ->label(__('admin.product.price_tier'))
                        ->options(fn (): array => PriceTier::query()->orderBy('sort')->pluck('name', 'id')->all())
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->required(),
                    self::money('price', __('admin.product.tier_price'))->required(),
                ]),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function details(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('warranty_months')->label(__('admin.product.warranty_months'))->integer()->minValue(0)->maxValue(600),
                TextInput::make('weight_kg')->label(__('admin.product.weight_kg'))->numeric()->minValue(0)->maxValue(99999),
                TextInput::make('length_mm')->label(__('admin.product.length_mm'))->integer()->minValue(0)->maxValue(100000),
                TextInput::make('width_mm')->label(__('admin.product.width_mm'))->integer()->minValue(0)->maxValue(100000),
                TextInput::make('height_mm')->label(__('admin.product.height_mm'))->integer()->minValue(0)->maxValue(100000),
            ]),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function seo(): array
    {
        return [
            TextInput::make('meta_title')->label(__('admin.product.meta_title'))->maxLength(255),
            Textarea::make('meta_description')->label(__('admin.product.meta_description'))->maxLength(500)->rows(2),
            TextInput::make('h1')->label(__('admin.product.h1'))->maxLength(255),
            Textarea::make('seo_text')->label(__('admin.product.seo_text'))->rows(6),
        ];
    }

    /**
     * Money is kept in kopecks (TZ §7): the field shows "48605.80" and gives back Money.
     */
    private static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->inputMode('decimal')
            ->regex(self::MONEY_PATTERN)
            ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                $state instanceof Money => $state->toDecimal(),
                blank($state) => null,
                default => (string) $state,
            })
            ->dehydrateStateUsing(fn (mixed $state): ?Money => blank($state) ? null : Money::fromDecimal((string) $state));
    }

    /**
     * Marks a field the import writes: a lock when the manager has changed it, and the way
     * back to the supplier value.
     *
     * @template T of Field
     *
     * @param  T  $field
     * @return T
     */
    private static function lockable(Field $field): Field
    {
        $name = $field->getName();

        return $field
            ->hintIcon(
                fn (?Product $record): ?Heroicon => self::isLocked($record, $name) ? Heroicon::LockClosed : null,
                __('admin.product.locked'),
            )
            ->hintAction(
                Action::make("restore_{$name}")
                    ->label(__('admin.product.restore'))
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->visible(fn (?Product $record): bool => self::isLocked($record, $name))
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.product.restore_confirm'))
                    ->action(function (Product $record, RestoreSupplierValue $restore) use ($name): void {
                        $restore->handle($record, $name);

                        Notification::make()->title(__('admin.product.restored'))->success()->send();
                    }),
            );
    }

    private static function isLocked(?Product $record, string $field): bool
    {
        return $record !== null
            && in_array($field, SaveProductByManager::LOCKABLE_FIELDS, true)
            && in_array($field, $record->locked_fields ?? [], true);
    }

    public static function availabilityColor(Availability $availability): string
    {
        return match ($availability) {
            Availability::InStock => 'success',
            Availability::Low => 'success',
            Availability::Incoming => 'warning',
            Availability::OnOrder => 'gray',
            Availability::Discontinued => 'danger',
        };
    }
}
