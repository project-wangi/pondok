<?php

namespace App\Filament\Resources\Murids\Tables;

use App\Models\Kelas;
use App\Models\Murid;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MuridsTable
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
                    ->disk('r2')
                    ->circular(),
                TextColumn::make('nama')
                    ->label('Nama Siswa')
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
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => Murid::statusColors()[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('tanggal_lulus')
                    ->label('Tanggal Lulus')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('tanggal_keluar')
                    ->label('Tanggal Keluar')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Siswa')
                    ->options(Murid::STATUS)
                    ->default(Murid::STATUS_AKTIF),
                SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn (): array => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pindahKelas')
                        ->label('Pindah kelas')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->modalHeading('Pindahkan siswa ke kelas lain')
                        ->modalDescription('Semua siswa yang dipilih akan dipindahkan ke kelas tujuan. Gunakan filter kelas untuk memilih satu kelas sekaligus.')
                        ->modalSubmitActionLabel('Pindahkan')
                        ->schema([
                            Select::make('kelas_id')
                                ->label('Kelas tujuan')
                                ->options(fn (): array => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->rules(['integer', 'exists:kelas,id']),
                        ])
                        ->authorize(fn (): bool => Filament::auth()->user()?->can('Update:Murid') ?? false)
                        ->action(function (Collection $records, array $data): void {
                            $ids = $records->pluck('id')->all();
                            $kelas = Kelas::query()->find((int) $data['kelas_id']);
                            $count = Murid::query()->whereKey($ids)->update([
                                'kelas_id' => (int) $data['kelas_id'],
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Kelas siswa diperbarui')
                                ->body($count.' siswa dipindahkan ke kelas '.($kelas?->nama_kelas ?? '-').'.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ubahStatus')
                        ->label('Ubah status')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->modalHeading('Ubah status siswa')
                        ->modalDescription('Tandai siswa sebagai Aktif, Lulus, atau Keluar. Daftar siswa bawaan hanya menampilkan status Aktif.')
                        ->modalSubmitActionLabel('Simpan status')
                        ->schema([
                            Select::make('status')
                                ->label('Status baru')
                                ->options(Murid::STATUS)
                                ->required()
                                ->rules(['in:'.implode(',', array_keys(Murid::STATUS))]),
                        ])
                        ->authorize(fn (): bool => Filament::auth()->user()?->can('Update:Murid') ?? false)
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Murid $murid) => $murid->update(['status' => $data['status']]));

                            $count = $records->count();

                            Notification::make()
                                ->success()
                                ->title('Status siswa diperbarui')
                                ->body($count.' siswa diubah menjadi '.$data['status'].'.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
