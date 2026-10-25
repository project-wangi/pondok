<?php

namespace App\Filament\Resources\JadwalPeminatans\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JadwalPeminatansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hari')
                    ->label('Hari'),

                TextColumn::make('jam_mulai')
                    ->label('Jam Mulai')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? substr($state, 0, 5) : '-'),

                TextColumn::make('jam_selesai')
                    ->label('Jam Selesai')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? substr($state, 0, 5) : '-'),

                TextColumn::make('guru.nama')
                    ->label('Pembina')
                    ->searchable(query: function ($query, string $search) {
                        return $query->orWhereHas('guru.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('jenisPeminatan.nama_peminatan')
                    ->label('Jenis Peminatan'),

            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
            ]);
    }
}
