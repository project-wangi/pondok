<?php

namespace App\Filament\Resources\JadwalRapats\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TagsColumn;

class JadwalRapatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('tanggal')
                    ->date('d-m-Y'),
                TextColumn::make('hari')
                    ->sortable(),
                TextColumn::make('jam_mulai')
                    ->time('H:i'),
                TextColumn::make('jam_selesai')
                    ->time('H:i'),
                TextColumn::make('agenda')
                    ->sortable(),
                TagsColumn::make('gurus.nama')
                    ->label('Guru')
                    ->sortable(),
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
