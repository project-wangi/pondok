<?php

namespace App\Filament\Resources\Donasis\Pages;

use App\Filament\Resources\Donasis\DonasiResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDonasi extends CreateRecord
{
    protected static string $resource = DonasiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();

        if (! ($user?->can('Create:Donasi') ?? false)) {
            $data['guru_id'] = $user?->guru()->value('id');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
