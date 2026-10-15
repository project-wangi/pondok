<?php

namespace App\Filament\Resources\JadwalRapats\Pages;

use App\Filament\Resources\JadwalRapats\JadwalRapatResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJadwalRapat extends EditRecord
{
    protected static string $resource = JadwalRapatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
