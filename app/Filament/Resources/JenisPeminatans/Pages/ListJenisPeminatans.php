<?php

namespace App\Filament\Resources\JenisPeminatans\Pages;

use App\Filament\Resources\JenisPeminatans\JenisPeminatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisPeminatans extends ListRecords
{
    protected static string $resource = JenisPeminatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
