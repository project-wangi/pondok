<?php

namespace App\Filament\Resources\Kelas\Tables;

use App\Models\Kelas;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KelasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('nama_kelas')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('murid_count')
                    ->getStateUsing(fn ($record) => $record->murids()->count())
                    ->label('Jumlah Murid')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('unit')
                    ->label('Unit')
                    ->options(Kelas::UNITS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([

            ]);
    }
}
