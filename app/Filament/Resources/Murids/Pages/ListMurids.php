<?php

namespace App\Filament\Resources\Murids\Pages;

use App\Filament\Resources\Murids\MuridResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMurids extends ListRecords
{
    protected static string $resource = MuridResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
