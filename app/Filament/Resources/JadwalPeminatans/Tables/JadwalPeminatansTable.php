<?php

namespace App\Filament\Resources\JadwalPeminatans\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class JadwalPeminatansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hari')
                    ->label('Hari'),

                TextColumn::make('guru.nama')
                    ->label('Pembina'),
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
