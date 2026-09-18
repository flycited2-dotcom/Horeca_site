<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\AttributeType;
use App\Enums\AttributeValueSource;
use App\Models\Attribute;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Characteristics of the product (TZ §5.2, §12). They will come from the supplier API;
 * a value set here is marked as manual, so the import never replaces it.
 */
class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeValues';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.product.attributes.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.product.attributes.attribute')),

                TextColumn::make('value')
                    ->label(__('admin.product.attributes.value'))
                    ->state(fn (Attribute $record): string => self::display($record)),

                TextColumn::make('pivot.raw_value')
                    ->label(__('admin.product.attributes.raw_value'))
                    ->placeholder('—'),

                TextColumn::make('pivot.source')
                    ->label(__('admin.product.attributes.source'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : AttributeValueSource::from($state)->getLabel())
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('sort')
            ->paginated(false)
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->live(),
                        ...self::valueFields(fn (Get $get): ?AttributeType => Attribute::query()->find($get('recordId'))?->type),
                    ])
                    ->mutateDataUsing(fn (array $data): array => self::manual($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Attribute $record): array => self::valueFields(fn (): AttributeType => $record->type))
                    ->mutateDataUsing(fn (array $data): array => self::manual($data)),
                DetachAction::make(),
            ]);
    }

    /**
     * One value field that matches the type of the characteristic.
     *
     * @param  callable(Get): ?AttributeType  $type
     * @return list<mixed>
     */
    private static function valueFields(callable $type): array
    {
        return [
            TextInput::make('value_string')
                ->label(__('admin.product.attributes.value_string'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $type($get) === AttributeType::Text),

            TextInput::make('value_number')
                ->label(__('admin.product.attributes.value_number'))
                ->numeric()
                ->visible(fn (Get $get): bool => $type($get) === AttributeType::Number),

            Toggle::make('value_bool')
                ->label(__('admin.product.attributes.value_bool'))
                ->visible(fn (Get $get): bool => $type($get) === AttributeType::Boolean),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function manual(array $data): array
    {
        return [...$data, 'source' => AttributeValueSource::Manual->value];
    }

    private static function display(Attribute $attribute): string
    {
        $pivot = $attribute->getRelationValue('pivot');

        $value = match ($attribute->type) {
            AttributeType::Number => $pivot?->value_number === null ? null : self::number((string) $pivot->value_number),
            AttributeType::Boolean => $pivot?->value_bool === null ? null : ($pivot->value_bool ? __('admin.yes') : __('admin.no')),
            AttributeType::Text => $pivot?->value_string,
        };

        if ($value === null) {
            return '—';
        }

        return $attribute->unit === null ? $value : $value.' '.$attribute->unit;
    }

    /**
     * decimal(14,3) without trailing zeros: "400.000" → "400", "7.500" → "7.5".
     */
    private static function number(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
