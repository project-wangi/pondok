<?php

namespace App\Filament\Resources\Kelas\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class KelasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kelas')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
