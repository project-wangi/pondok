<?php

namespace App\Filament\Resources\JadwalRapats\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Carbon\Carbon;
use Filament\Schemas\Schema;

class JadwalRapatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state) {
                            $days = [
                                'Sunday' => 'Minggu',
                                'Monday' => 'Senin',
                                'Tuesday' => 'Selasa',
                                'Wednesday' => 'Rabu',
                                'Thursday' => 'Kamis',
                                'Friday' => 'Jumat',
                                'Saturday' => 'Sabtu',
                            ];

                            $englishDay = Carbon::parse($state)->format('l');
                            // Mengisi nilai hari secara otomatis di background ke database
                            $set('hari', $days[$englishDay] ?? '');
                        }
                    }),
                Hidden::make('hari')
                    ->required(),

                TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),

                TextInput::make('agenda')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->required(),

                Select::make('gurus')
                    ->label('Anggota Rapat')
                    ->relationship('gurus', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nama ?? $record->id)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->required(),
            ]);
    }
}
