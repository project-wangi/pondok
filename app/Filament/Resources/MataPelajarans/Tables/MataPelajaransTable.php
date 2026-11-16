<?php

namespace App\Filament\Resources\MataPelajarans\Tables;

use App\Models\ERapor;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MataPelajaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('erapors:id,mata_pelajaran_id,kkm'))
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('kode_mapel')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('nama_mapel')
                    ->label('Nama Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('kkm_rapor')
                    ->label('KKM')
                    ->tooltip('Diambil dari KKM yang diisi guru pada e-Rapor. Angka bertanda (default) berarti guru belum menyimpan e-Rapor.')
                    ->getStateUsing(function (MataPelajaran $record): string {
                        $kkms = $record->erapors
                            ->pluck('kkm')
                            ->filter(fn (?int $kkm): bool => $kkm !== null)
                            ->unique()
                            ->sort()
                            ->values();

                        return $kkms->isEmpty()
                            ? ERapor::DEFAULT_KKM.' (default)'
                            : $kkms->implode(', ');
                    }),
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
