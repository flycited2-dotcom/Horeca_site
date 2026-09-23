<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Actions\Storefront\SaveRedirect;
use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRedirect extends CreateRecord
{
    protected static string $resource = RedirectResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveRedirect::class)->handle(null, (string) $data['from_path'], (string) $data['to_path'], (int) $data['status_code']);
    }
}
