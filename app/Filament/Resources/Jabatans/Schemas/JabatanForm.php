<?php

namespace App\Filament\Resources\Jabatans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JabatanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_jabatan')
                    ->required(),
                TextInput::make('gaji')
                    ->label('Gaji / Tunjangan')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->default(0),
            ]);
    }
}
