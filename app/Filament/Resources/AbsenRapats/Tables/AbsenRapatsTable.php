<?php

namespace App\Filament\Resources\AbsenRapats\Tables;

use App\Models\AbsenRapat;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AbsenRapatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('agenda')
                    ->label('Agenda')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('nama_guru')
                    ->label('Guru')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('jam_mulai')
                    ->label('Jam Mulai')
                    ->time('H:i'),
                TextColumn::make('jam_selesai')
                    ->label('Jam Selesai')
                    ->time('H:i'),
                ImageColumn::make('foto')
                    ->label('Foto Rapat')
                    ->getStateUsing(fn (AbsenRapat $record): ?string => self::fotoUrl($record->foto))
                    ->url(fn (AbsenRapat $record): ?string => self::fotoUrl($record->foto))
                    ->openUrlInNewTab()
                    ->square()
                    ->height(48)
                    ->width(48)
                    ->placeholder('Tidak ada'),
                TextColumn::make('dicatat_pada')
                    ->label('Dicatat')
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
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            $query->whereMonth('tanggal', $data['value']);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                Action::make('lihatFoto')
                    ->label('Lihat Foto')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->url(fn (AbsenRapat $record): ?string => self::fotoUrl($record->foto))
                    ->openUrlInNewTab()
                    ->visible(fn (AbsenRapat $record): bool => filled($record->foto)),
            ])
            ->toolbarActions([]);
    }

    public static function fotoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            return Storage::disk(AbsenRapat::FOTO_DISK)->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            try {
                return Storage::disk(AbsenRapat::FOTO_DISK)->url($path);
            } catch (Throwable) {
                return null;
            }
        }
    }
}
