<?php

namespace App\Filament\Resources\Murids\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;


class MuridsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->label('Foto')
                    ->circular(),
                TextColumn::make('nama')
                    ->label('Nama Murid')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nisn')
                    ->label('NISN'),
                TextColumn::make('nis')
                    ->label('NIS'),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('nohp_siswa')
                    ->label('No. HP'),
                TextColumn::make('jenis_kelamin'),
                TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->badge()
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
