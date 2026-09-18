<?php

namespace App\Filament\Resources\ImportProfiles\Schemas;

use App\Models\ImportProfile;
use App\Models\Supplier;
use App\Services\Supplier\Import\OwnedFieldsGuard;
use App\Services\Supplier\Import\SourceRegistry;
use Cron\CronExpression;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A source of one supplier: where to read it, how often and how carefully (TZ §6.2, §6.3).
 *
 * The tag map of a feed is not edited here: it lives in config/suppliers, because a wrong
 * tag breaks the whole catalog and belongs in review, not in a form (TZ §6.1, fact 2).
 */
class ImportProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('admin.import_profile.supplier'))
                            ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->native(false),

                        TextInput::make('name')
                            ->label(__('admin.import_profile.name'))
                            ->required()
                            ->maxLength(150),

                        Select::make('source')
                            ->label(__('admin.import_profile.source'))
                            ->options(fn (SourceRegistry $sources): array => $sources->options())
                            ->required()
                            ->native(false)
                            ->live(),

                        TextInput::make('url')
                            ->label(__('admin.import_profile.url'))
                            ->url()
                            ->maxLength(500)
                            ->required(),

                        TextInput::make('schedule')
                            ->label(__('admin.import_profile.schedule'))
                            ->helperText(__('admin.import_profile.schedule_hint'))
                            ->maxLength(64)
                            ->rule(fn (): callable => function (string $attribute, mixed $value, callable $fail): void {
                                if (filled($value) && ! CronExpression::isValidExpression((string) $value)) {
                                    $fail(__('import.errors.invalid_schedule'));
                                }
                            }),

                        Toggle::make('is_active')
                            ->label(__('admin.import_profile.is_active'))
                            ->helperText(fn (callable $get, OwnedFieldsGuard $guard): string => implode(
                                ', ',
                                $guard->fieldsOf((string) $get('source')),
                            ))
                            ->rule(fn (?ImportProfile $record, callable $get): callable => function (
                                string $attribute,
                                mixed $value,
                                callable $fail,
                            ) use ($record, $get): void {
                                if (! $value) {
                                    return;
                                }

                                $profile = $record ?? new ImportProfile;
                                $profile->supplier_id = (int) $get('supplier_id');
                                $profile->source = (string) $get('source');

                                $conflict = app(OwnedFieldsGuard::class)->conflict($profile);

                                if ($conflict !== null) {
                                    $fail($conflict);
                                }
                            }),
                    ]),

                Section::make(__('admin.import_profile.thresholds'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('settings.invalid_rows_max_percent')
                            ->label(__('admin.import_profile.invalid_rows_max_percent'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default((int) config('import.thresholds.invalid_rows_max_percent'))
                            ->required(),

                        TextInput::make('settings.min_records_percent_of_previous')
                            ->label(__('admin.import_profile.min_records_percent_of_previous'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default((int) config('import.thresholds.min_records_percent_of_previous'))
                            ->required(),
                    ]),
            ]);
    }
}
