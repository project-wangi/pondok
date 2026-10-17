<?php

namespace App\Filament\Resources\JadwalPelajarans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use App\Models\Guru;
use App\Models\Kelas;


class JadwalPelajaranForm
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
                Select::make('guru_id')
                    ->relationship('guru', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Guru $record) => $record->nama ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('mata_pelajaran')
                    ->relationship('mataPelajaran', 'nama_mapel')
                    ->required()
                    ->searchable()
                    ->preload(),
                TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                Select::make('kelas_id')
                    ->relationship('kelas', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Kelas $record) => $record->nama_kelas ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }
}
