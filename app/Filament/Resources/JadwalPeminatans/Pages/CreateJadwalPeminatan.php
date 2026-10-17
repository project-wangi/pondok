<?php

namespace App\Filament\Resources\JadwalPeminatans\Pages;

use App\Filament\Resources\JadwalPeminatans\JadwalPeminatanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJadwalPeminatan extends CreateRecord
{
    protected static string $resource = JadwalPeminatanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
