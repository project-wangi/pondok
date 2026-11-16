<?php

namespace App\Filament\Resources\Kelas\Schemas;

use App\Models\Kelas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KelasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kelas')
                    ->label('Nama Kelas')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Contoh: "VII A" untuk MTs, "10 IPA" untuk MA.'),
                Select::make('unit')
                    ->label('Unit')
                    ->options(Kelas::UNITS)
                    ->required()
                    ->native(false),
            ]);
    }
}
