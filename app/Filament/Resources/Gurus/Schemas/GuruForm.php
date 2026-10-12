<?php

namespace App\Filament\Resources\Gurus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuruForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
