<?php

namespace App\Filament\Resources\Donasis\Tables;

use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Table;

class DonasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rowIndex')
                    ->label('No.')
                    ->rowIndex(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return '-';
                        }

                    // Mengatur bahasa Carbon ke Indonesia dan memformat tanggal
                    return Carbon::parse($state)
                        ->locale('id')
                        ->translatedFormat('l, d F Y'); // Contoh hasil: Kamis, 22 Oktober 2026
                }),
                TextColumn::make('guru.user.name')
                    ->label('Guru')
                    ->visible(fn (): bool => Filament::auth()->user()?->can('ViewAny:Donasi') ?? false),
                TextColumn::make('jumlah_anak')
                    ->label('Jumlah Anak')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->formatStateUsing(fn (mixed $state): string => 'Rp '.number_format((int) $state, 0, ',', '.')),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('bulan')
                ->label('Filter Bulan')
                ->options([
                    '01' => 'Januari',
                    '02' => 'Februari',
                    '03' => 'Maret',
                    '04' => 'April',
                    '05' => 'Mei',
                    '06' => 'Juni',
                    '07' => 'Juli',
                    '08' => 'Agustus',
                    '09' => 'September',
                    '10' => 'Oktober',
                    '11' => 'November',
                    '12' => 'Desember',
                ])
                ->query(function ($query, array $data) {
                    if (!empty($data['value'])) {
                        // Memfilter berdasarkan bulan dari kolom tanggal
                        // Menggunakan fungsi MONTH() di database MySQL
                        $query->whereMonth('tanggal', $data['value']);
                    }
                }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
