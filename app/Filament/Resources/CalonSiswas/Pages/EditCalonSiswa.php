<?php

namespace App\Filament\Resources\CalonSiswas\Pages;

use App\Filament\Resources\CalonSiswas\CalonSiswaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalonSiswa extends EditRecord
{
    protected static string $resource = CalonSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //DeleteAction::make(),//
        ];
    }
}
