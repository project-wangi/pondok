<?php

namespace App\Filament\Resources\Murids\Tables;

use App\Filament\Exports\MuridWorkbook;
use App\Filament\Imports\MuridImporter;
use App\Filament\Resources\Murids\Pages\ListMurids;
use App\Models\Kelas;
use App\Models\Murid;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->headerActions([
                Action::make('unduhDataSiswa')
                    ->label('Unduh data')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('ViewAny:Murid') ?? false)
                    ->action(function (ListMurids $livewire): StreamedResponse {
                        $query = $livewire->getFilteredTableQuery();
                        $murids = $query === null ? new Collection : $query->orderBy('nama')->orderBy('id')->get();

                        return app(MuridWorkbook::class)->download($murids);
                    }),
                Action::make('unduhTemplateSiswa')
                    ->label('Template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->tooltip('Unduh berkas contoh berisi judul kolom untuk unggah massal')
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('ViewAny:Murid') ?? false)
                    ->action(fn (): StreamedResponse => app(MuridWorkbook::class)->template()),
                Action::make('unggahDataSiswa')
                    ->label('Unggah data')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('Create:Murid') ?? false)
                    ->modalHeading('Unggah data siswa massal')
                    ->modalDescription('Klik "Template" untuk mengunduh berkas contoh, lalu unggah kembali dalam format XLSX/CSV. Baris dicocokkan berdasarkan NIS (atau email bila NIS kosong). Baris yang salah akan dilewati tanpa membatalkan baris lain, dan daftar kesalahannya ditampilkan setelah proses selesai.')
                    ->modalSubmitActionLabel('Proses berkas')
                    ->schema([
                        FileUpload::make('berkas')
                            ->label('Berkas data siswa')
                            ->disk('local')
                            ->directory('import-siswa')
                            ->rules(['extensions:xlsx,csv'])
                            ->helperText('Format XLSX atau CSV, maksimal 2 MB.')
                            ->maxSize(2048)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $berkas = (string) $data['berkas'];
                        $hasil = app(MuridImporter::class)->import(Storage::disk('local')->path($berkas));

                        Storage::disk('local')->delete($berkas);

                        $ringkasan = $hasil['dibuat'].' siswa baru, '.$hasil['diperbarui'].' siswa diperbarui.';
                        $ditampilkan = array_slice($hasil['gagal'], 0, 10);
                        $body = e($ringkasan);

                        if ($hasil['jumlah_gagal'] > 0) {
                            $body .= '<br><br>'.e($hasil['jumlah_gagal'].' baris dilewati dan tidak mengubah data. Perbaiki baris berikut lalu unggah ulang:')
                                .'<br>'.implode('<br>', array_map(
                                    fn (string $galat): string => e($galat),
                                    $ditampilkan,
                                ));

                            if ($hasil['jumlah_gagal'] > count($ditampilkan)) {
                                $body .= '<br>'.e('… dan '.($hasil['jumlah_gagal'] - count($ditampilkan)).' baris lainnya.');
                            }
                        }

                        $notification = Notification::make()
                            ->title($hasil['jumlah_gagal'] === 0 ? 'Unggah data selesai' : 'Unggah data selesai dengan catatan')
                            ->body(new HtmlString($body))
                            ->persistent();

                        $hasil['jumlah_gagal'] === 0 ? $notification->success() : $notification->warning();

                        $notification->send();
                    }),
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
