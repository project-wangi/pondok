<?php

namespace App\Filament\Resources\JadwalPeminatans\Pages;

use App\Filament\Resources\JadwalPeminatans\JadwalPeminatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJadwalPeminatans extends ListRecords
{
    protected static string $resource = JadwalPeminatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
