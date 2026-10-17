<?php

namespace App\Filament\Resources\JadwalPelajarans\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JadwalPelajaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('hari')
                    ->searchable(),
                TextColumn::make('kelas.nama_kelas')
                    ->searchable(),
                TextColumn::make('guru.nama')
                    ->label('Guru')
                    ->sortable(),
                TextColumn::make('mataPelajaran.nama_mapel')
                    ->searchable(),
                TextColumn::make('jam_mulai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('jam_selesai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([

            ]);
    }
}
