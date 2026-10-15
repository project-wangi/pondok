<?php

namespace App\Filament\Resources\JenisPeminatans\Pages;

use App\Filament\Resources\JenisPeminatans\JenisPeminatanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisPeminatan extends EditRecord
{
    protected static string $resource = JenisPeminatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
