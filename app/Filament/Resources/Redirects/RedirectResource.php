<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Редиректы (ТЗ §12, §14): 301 со старых адресов — своих, переехавших, и прежнего сайта.
 * Смена адреса товара, раздела, бренда или страницы ставит их сама; здесь — остальные и
 * счётчик переходов. Сохраняет SaveRedirect: без цепочек и петель.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('admin.redirect.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.redirect.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('from_path')
                    ->label(__('admin.redirect.from_path'))
                    ->helperText(__('admin.redirect.from_hint'))
                    ->required()
                    ->maxLength(500)
                    ->regex('/^(\/|https?:\/\/)\S*$/')
                    ->extraInputAttributes(['class' => 'font-mono']),

                TextInput::make('to_path')
                    ->label(__('admin.redirect.to_path'))
                    ->helperText(__('admin.redirect.to_hint'))
                    ->required()
                    ->maxLength(500)
                    ->regex('/^(\/|https?:\/\/)\S*$/')
                    ->extraInputAttributes(['class' => 'font-mono']),

                Select::make('status_code')
                    ->label(__('admin.redirect.status_code'))
                    ->options([301 => __('admin.redirect.permanent'), 302 => __('admin.redirect.temporary')])
                    ->default(301)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label(__('admin.redirect.from_path'))->fontFamily('mono')->searchable()->limit(60),
                TextColumn::make('to_path')->label(__('admin.redirect.to_path'))->fontFamily('mono')->searchable()->limit(60),
                TextColumn::make('status_code')->label(__('admin.redirect.status_code'))->badge()->color('gray'),
                TextColumn::make('hits')->label(__('admin.redirect.hits'))->sortable()->alignEnd(),
                TextColumn::make('updated_at')->label(__('admin.redirect.updated_at'))->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
