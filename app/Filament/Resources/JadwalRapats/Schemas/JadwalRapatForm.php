<?php

namespace App\Filament\Resources\JadwalRapats\Schemas;

use App\Filament\Forms\Components\JamPicker;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

                JamPicker::make('jam_mulai')
                    ->label('Jam mulai')
                    ->required(),
                JamPicker::make('jam_selesai')
                    ->label('Jam selesai')
                    ->required(),

                TextInput::make('agenda')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->required(),

                MarkdownEditor::make('notulensi')
                    ->label('Notulensi')
                    ->helperText('Catatan hasil rapat. Mendukung Markdown: **tebal**, *miring*, daftar, tabel, dan tautan.')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('rapat/notulensi')
                    ->minHeight('12rem')
                    ->columnSpanFull(),

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
