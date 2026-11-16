<?php

namespace App\Filament\Resources\JadwalRapats\Tables;

use App\Models\JadwalRapat;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextColumn::make('notulensi')
                    ->label('Notulensi')
                    ->badge()
                    ->state(fn (JadwalRapat $record): string => filled($record->notulensi) ? 'Ada' : 'Belum ada')
                    ->color(fn (string $state): string => $state === 'Ada' ? 'success' : 'gray'),
                TagsColumn::make('gurus.nama')
                    ->label('Guru')
                    ->searchable(query: function ($query, string $search) {
                        return $query->orWhereHas('gurus.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
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
