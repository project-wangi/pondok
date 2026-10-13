<?php

namespace App\Filament\Resources\Murids\Pages;

use App\Filament\Resources\Murids\MuridResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMurid extends EditRecord
{
    protected static string $resource = MuridResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
