<?php

namespace App\Filament\Resources\MataPelajarans\Schemas;

use App\Models\Kelas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MataPelajaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_mapel')
                    ->label('Kode Mata Pelajaran'),
                TextInput::make('nama_mapel')
                    ->label('Nama Mata Pelajaran')
                    ->required(),
                Select::make('unit')
                    ->label('Unit')
                    ->options(Kelas::UNITS)
                    ->placeholder('Semua unit')
                    ->native(false),
            ]);
    }
}
