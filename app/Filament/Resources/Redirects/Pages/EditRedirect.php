<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Actions\Storefront\SaveRedirect;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Redirect;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  Redirect  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveRedirect::class)->handle($record, (string) $data['from_path'], (string) $data['to_path'], (int) $data['status_code']);
    }
}
