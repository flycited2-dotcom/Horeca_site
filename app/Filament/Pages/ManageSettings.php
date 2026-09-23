<?php

namespace App\Filament\Pages;

use App\Actions\Settings\SaveSettings;
use App\Models\User;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * «Настройки» (ТЗ §12, §5.5) — только администратору (§2.1). Поля — ключи из SaveSettings::KEYS;
 * точка в ключе заменена двумя подчёркиваниями, иначе форма сочла бы её вложенностью.
 * Пустое поле — «ещё не задано»: витрина тогда не показывает этот блок.
 */
final class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.title');
    }

    public function mount(SaveSettings $settings): void
    {
        $values = [];

        foreach ($settings->current() as $key => $value) {
            $values[self::field($key)] = is_array($value) ? implode("\n", array_map(strval(...), $value)) : $value;
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $text = fn (string $key): TextInput => TextInput::make(self::field($key))->label(__("admin.settings.fields.{$key}"));
        $hint = fn (string $key): string => __("admin.settings.hints.{$key}");

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.settings.sections.contacts'))
                    ->columns(2)
                    ->schema([
                        $text('site.name')->maxLength(100)->helperText($hint('site.name')),
                        $text('contacts.email')->email()->maxLength(150),
                        Textarea::make(self::field('contacts.phones'))->label(__('admin.settings.fields.contacts.phones'))->rows(3)->maxLength(500)->helperText($hint('contacts.phones')),
                        Textarea::make(self::field('contacts.schedule'))->label(__('admin.settings.fields.contacts.schedule'))->rows(3)->maxLength(300),
                        $text('contacts.address')->maxLength(300)->columnSpanFull(),
                    ]),
                Section::make(__('admin.settings.sections.seller'))
                    ->columns(2)
                    ->schema([
                        Textarea::make(self::field('seller.requisites'))->label(__('admin.settings.fields.seller.requisites'))->rows(4)->maxLength(2000)->helperText($hint('seller.requisites')),
                        Select::make(self::field('seller.vat_mode'))
                            ->label(__('admin.settings.fields.seller.vat_mode'))
                            ->options(['with_vat' => __('admin.settings.vat.with_vat'), 'without_vat' => __('admin.settings.vat.without_vat')])
                            ->helperText($hint('seller.vat_mode')),
                    ]),
                Section::make(__('admin.settings.sections.delivery'))
                    ->columns(2)
                    ->schema([
                        $text('pickup.address')->maxLength(300)->helperText($hint('pickup.address')),
                        $text('delivery.free_city_from')->integer()->minValue(0)->maxValue(100_000_000)->suffix('₽')->helperText($hint('delivery.free_city_from')),
                        Toggle::make(self::field('payments.online_enabled'))->label(__('admin.settings.fields.payments.online_enabled'))->helperText($hint('payments.online_enabled')),
                    ]),
                Section::make(__('admin.settings.sections.catalog'))
                    ->columns(3)
                    ->schema([
                        // Free text with hints: the stored name may belong to a warehouse the import has not brought yet.
                        $text('catalog.local_warehouse_name')
                            ->maxLength(150)
                            ->datalist(fn (): array => Warehouse::query()->orderBy('name')->pluck('name')->all())
                            ->helperText($hint('catalog.local_warehouse_name')),
                        $text('catalog.local_strip_min_products')->integer()->minValue(1)->maxValue(100)->helperText($hint('catalog.local_strip_min_products')),
                        $text('catalog.discontinued_after_runs')->integer()->minValue(1)->maxValue(30)->required()->helperText($hint('catalog.discontinued_after_runs')),
                    ]),
                Section::make(__('admin.settings.sections.pricing'))
                    ->description(__('admin.settings.pricing_note'))
                    ->columns(3)
                    ->schema([
                        $text('pricing.max_discount_without_purchase')->numeric()->minValue(0)->maxValue(90)->step(0.01)->suffix('%')->required()->helperText($hint('pricing.max_discount_without_purchase')),
                        $text('pricing.min_margin_percent')->numeric()->minValue(0)->maxValue(1000)->step(0.01)->suffix('%')->helperText($hint('pricing.min_margin_percent')),
                        Toggle::make(self::field('pricing.show_tier_name'))->label(__('admin.settings.fields.pricing.show_tier_name'))->helperText($hint('pricing.show_tier_name')),
                    ]),
                Section::make(__('admin.settings.sections.notify'))
                    ->columns(2)
                    ->schema([
                        Toggle::make(self::field('notify.telegram_include_contacts'))->label(__('admin.settings.fields.notify.telegram_include_contacts'))->helperText($hint('notify.telegram_include_contacts')),
                        $text('analytics.metrika_id')->regex('/^\d{5,12}$/')->helperText($hint('analytics.metrika_id')),
                    ]),
                Section::make(__('admin.settings.sections.seo'))
                    ->description(__('admin.settings.seo_note'))
                    ->schema([
                        $text('seo.product_title_template')->maxLength(255),
                        $text('seo.category_title_template')->maxLength(255),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('admin.settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(SaveSettings $settings): void
    {
        $state = $this->form->getState();
        $values = [];

        foreach (array_keys(SaveSettings::KEYS) as $key) {
            $values[$key] = $state[self::field($key)] ?? null;
        }

        $settings->handle($values);

        Notification::make()->success()->title(__('admin.settings.saved'))->send();
    }

    /**
     * «contacts.phones» → «contacts__phones».
     */
    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
