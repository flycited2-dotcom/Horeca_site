<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('admin.page.title'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.page.slug'))->fontFamily('mono')->searchable(),
                IconColumn::make('is_active')->label(__('admin.page.is_active'))->boolean()->sortable(),
                TextColumn::make('updated_at')->label(__('admin.page.updated_at'))->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('sort')->label(__('admin.page.sort'))->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.page.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record): bool => $record->is_active),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort');
    }
}
