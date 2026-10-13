<?php

namespace App\Filament\Resources\Gurus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GuruForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('foto')
                    ->label('Foto Guru')
                    ->image()
                    ->disk('local')
                    ->maxSize(1024 * 10)
                    ->directory('guru-fotos')
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('Nama')
                    ->relationship('user', 'name', function ($query, $livewire) {
                        $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))

                        ->when($livewire instanceof \Filament\Resources\Pages\EditRecord, function ($q) use ($livewire) {
                            $q->where(function ($subQ) use ($livewire) {
                                $subQ->whereDoesntHave('guru')
                                     ->orWhere('id', $livewire->record->user_id);
                            });
                        }, function ($q) {
                            $q->whereDoesntHave('guru');
                        });
                    })
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('agama')
                    ->label('Agama')
                    ->options([
                        'Islam' => 'Islam',
                        'Kristen' => 'Kristen',
                        'Katolik' => 'Katolik',
                        'Hindu' => 'Hindu',
                        'Buddha' => 'Buddha',
                        'Konghucu' => 'Konghucu',
                    ])
                    ->required(),

                TextInput::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->maxLength(255),

                DatePicker::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->required(),

                TextInput::make('jarak_rumah')
                    ->label('Jarak Rumah (KM)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->suffix('KM'),

                Textarea::make('alamat')
                    ->label('Alamat Lengkap')
                    ->columnSpanFull(),

                Select::make('jabatans')
                    ->label('Jabatan')
                    ->relationship('jabatans', 'nama_jabatan')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'laki-laki' => 'laki-laki',
                        'perempuan' => 'perempuan',
                    ])
                    ->required(),
            ]);
    }
}
