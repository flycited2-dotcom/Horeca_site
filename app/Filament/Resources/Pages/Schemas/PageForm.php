<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use App\Rules\FreePageSlug;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Страница: название, адрес, включение, текст Markdown и SEO. Адрес обязательной страницы
 * не меняется — на него ссылаются подвал, формы и заявка на опт. Включить страницу без
 * текста нельзя: покупатель увидел бы пустую страницу.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('admin.page.title'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label(__('admin.page.slug'))
                            ->helperText(fn (?Page $record): string => $record?->isRequired() ? __('admin.page.slug_locked') : __('admin.page.slug_hint'))
                            ->required()
                            ->maxLength(160)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true)
                            ->rule(new FreePageSlug)
                            ->disabled(fn (?Page $record): bool => (bool) $record?->isRequired())
                            ->dehydrated(fn (?Page $record): bool => ! $record?->isRequired())
                            ->extraInputAttributes(['class' => 'font-mono']),

                        MarkdownEditor::make('content')
                            ->label(__('admin.page.content'))
                            ->helperText(__('admin.page.content_hint'))
                            ->fileAttachments(false)
                            ->required(fn (Get $get): bool => (bool) $get('is_active'))
                            ->validationMessages(['required' => __('admin.page.content_required')])
                            ->columnSpanFull(),
                    ]),

                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('admin.page.is_active'))
                            ->helperText(__('admin.page.is_active_hint'))
                            ->live(),

                        TextInput::make('sort')
                            ->label(__('admin.page.sort'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(32767)
                            ->default(0),

                        TextInput::make('meta_title')
                            ->label(__('admin.page.meta_title'))
                            ->helperText(__('admin.page.meta_title_hint'))
                            ->maxLength(255),

                        Textarea::make('meta_description')
                            ->label(__('admin.page.meta_description'))
                            ->helperText(__('admin.page.meta_description_hint'))
                            ->rows(4)
                            ->maxLength(500),
                    ]),
            ]);
    }
}
