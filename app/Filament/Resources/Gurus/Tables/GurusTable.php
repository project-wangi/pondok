<?php

namespace App\Filament\Resources\Gurus\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GurusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                ImageColumn::make('foto')
                    ->label('Foto')
                    ->circular(),
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('jabatans.nama_jabatan')
                    ->label('Jabatan')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('jarak_rumah')
                    ->label('Jarak Rumah')
                    ->formatStateUsing(fn ($state) => round($state) . ' KM')
                    ->sortable(),
                TextColumn::make('agama')
                    ->label('Agama')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->date('d-m-Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->toggleable(isToggledHiddenByDefault: true),
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
