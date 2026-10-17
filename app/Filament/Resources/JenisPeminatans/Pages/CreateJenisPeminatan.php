<?php

namespace App\Filament\Resources\JenisPeminatans\Pages;

use App\Filament\Resources\JenisPeminatans\JenisPeminatanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJenisPeminatan extends CreateRecord
{
    protected static string $resource = JenisPeminatanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
