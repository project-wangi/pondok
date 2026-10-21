<?php

namespace App\Filament\Resources\JadwalPeminatans\Schemas;

use App\Models\Guru;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class JadwalPeminatanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('hari')
                    ->options([
                        'Senin' => 'Senin',
                        'Selasa' => 'Selasa',
                        'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis',
                        'Jumat' => 'Jumat',
                        'Sabtu' => 'Sabtu',
                        'Minggu' => 'Minggu',
                    ])
                    ->required(),
                TimePicker::make('jam_mulai')
                    ->label('Jam Mulai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                TimePicker::make('jam_selesai')
                    ->label('Jam Selesai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                Select::make('guru_id')
                    ->relationship('guru', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Guru $record) => $record->nama ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('jenis_peminatan_id')
                    ->label('Jenis Peminatan')
                    ->relationship('jenisPeminatan', 'nama_peminatan')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
