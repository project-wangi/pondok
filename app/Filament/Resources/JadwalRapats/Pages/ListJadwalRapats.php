<?php

namespace App\Filament\Resources\JadwalRapats\Pages;

use App\Filament\Resources\JadwalRapats\JadwalRapatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJadwalRapats extends ListRecords
{
    protected static string $resource = JadwalRapatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
