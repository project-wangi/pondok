<?php

namespace App\Filament\Resources\JadwalPeminatans\Pages;

use App\Filament\Resources\JadwalPeminatans\JadwalPeminatanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJadwalPeminatan extends EditRecord
{
    protected static string $resource = JadwalPeminatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
