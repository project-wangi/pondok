<?php

namespace App\Filament\Resources\Kelas\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KelasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(),
                TextColumn::make('nama_kelas')
                    ->searchable(),
                TextColumn::make('murid_count')
                    ->getStateUsing(fn ($record) => $record->murids()->count())
                    ->label('Jumlah Murid')
                    ->badge(),
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
