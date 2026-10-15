<?php

namespace App\Filament\Resources\JadwalPikets\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use App\Models\Guru;
use Filament\Forms\Components\DatePicker;


class JadwalPiketForm
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
                Textarea::make('ket')
                    ->columnSpanFull(),
            ]);
    }
}
