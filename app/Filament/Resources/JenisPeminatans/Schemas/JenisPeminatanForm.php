<?php

namespace App\Filament\Resources\JenisPeminatans\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;


class JenisPeminatanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_peminatan')
                    ->required(),
                TextInput::make('nominal')
                    ->numeric()
                    ->prefix('Rp. ')
                    ->required(),
            ]);
    }
}
