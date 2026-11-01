<?php

namespace App\Filament\Resources\CalonSiswas\Tables;

use App\Mail\KataSandiBaruCalonSiswa;
use App\Models\CalonSiswa;
use App\Models\JenisPeminatan;
use App\Models\Kelas;
use App\Models\Murid;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CalonSiswasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_pendaftaran')
                    ->label('No. Pendaftaran')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn (string $state): string => CalonSiswa::JENIS_KELAMIN[$state] ?? $state),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('nohp')
                    ->label('WhatsApp'),
                TextColumn::make('nilai_tes')
                    ->label('Nilai Tes')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => CalonSiswa::statusColors()[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('dilengkapi_pada')
                    ->label('Data Pendaftaran')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? 'Belum lengkap' : 'Lengkap')
                    ->color(fn (mixed $state): string => $state === null ? 'gray' : 'success')
                    ->sortable(),
                TextColumn::make('murid.nis')
                    ->label('NIS')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('murid.kelas.nama_kelas')
                    ->label('Kelas')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(CalonSiswa::STATUS),
                SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(CalonSiswa::JENIS_KELAMIN),
                SelectFilter::make('kelengkapan')
                    ->label('Kelengkapan Data')
                    ->options([
                        'lengkap' => 'Lengkap',
                        'belum' => 'Belum lengkap',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'lengkap' => $query->whereNotNull('dilengkapi_pada'),
                        'belum' => $query->whereNull('dilengkapi_pada'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('jadikanSiswa')
                    ->label('Jadikan siswa')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (CalonSiswa $record): bool => $record->murid_id === null && $record->status === CalonSiswa::STATUS_LULUS)
                    ->authorize(fn (CalonSiswa $record): bool => Filament::auth()->user()?->can('update', $record) ?? false)
                    ->modalHeading('Jadikan siswa')
                    ->modalDescription('NISN diambil dari data yang diisi calon siswa. Isi NIS dari sekolah, lalu pilih kelas dan peminatan. Data administrasi, orang tua, dan dokumen ikut dipindahkan ke data siswa.')
                    ->modalSubmitActionLabel('Jadikan siswa')
                    ->schema([
                        TextInput::make('nis')
                            ->label('NIS')
                            ->required()
                            ->maxLength(30)
                            ->unique('murids', 'nis', ignoreRecord: false),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn (): array => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules(['integer', 'exists:kelas,id']),
                        Select::make('jenis_peminatans')
                            ->label('Peminatan')
                            ->options(fn (): array => JenisPeminatan::query()->orderBy('nama_peminatan')->pluck('nama_peminatan', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Boleh memilih lebih dari satu.'),
                    ])
                    ->action(function (CalonSiswa $record, array $data): void {
                        if ($record->murid_id !== null) {
                            Notification::make()
                                ->warning()
                                ->title('Calon siswa sudah menjadi siswa.')
                                ->send();

                            return;
                        }

                        if (! $record->dataLengkap()) {
                            Notification::make()
                                ->danger()
                                ->title('Data calon siswa belum lengkap')
                                ->body('Lengkapi '.implode(', ', $record->dataBelumLengkap()).' sebelum menjadikan siswa.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        if (Murid::query()->where('email', $record->email)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Email sudah dipakai siswa lain')
                                ->body('Periksa data siswa sebelum mengonversi pendaftaran ini.')
                                ->send();

                            return;
                        }

                        if (Murid::query()->where('nisn', $record->nisn)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('NISN sudah dipakai siswa lain')
                                ->body('Minta calon siswa memperbarui NISN di panel siswa sebelum konversi.')
                                ->send();

                            return;
                        }

                        $murid = DB::transaction(function () use ($record, $data): Murid {
                            $murid = Murid::query()->create([
                                ...$record->atributSiswa(),
                                'nis' => $data['nis'],
                                'kelas_id' => $data['kelas_id'],
                            ]);

                            $murid->jenisPeminatans()->sync($data['jenis_peminatans'] ?? []);

                            $record->update([
                                'murid_id' => $murid->id,
                                'status' => CalonSiswa::STATUS_DITERIMA,
                                'diterima_pada' => now(),
                            ]);

                            return $murid;
                        });

                        Notification::make()
                            ->success()
                            ->title('Siswa berhasil dibuat')
                            ->body($murid->nama.' tercatat dengan NIS '.$murid->nis.'.')
                            ->send();
                    }),
                Action::make('resetSandi')
                    ->label('Reset kata sandi')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->authorize(fn (CalonSiswa $record): bool => Filament::auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Reset kata sandi calon siswa')
                    ->modalDescription('Sistem akan membuat kata sandi sementara baru dan mengirimkannya ke email pendaftar. Kata sandi lama langsung tidak berlaku.')
                    ->modalSubmitActionLabel('Reset dan kirim email')
                    ->action(function (CalonSiswa $record): void {
                        $user = $record->user;

                        if ($user === null) {
                            Notification::make()
                                ->danger()
                                ->title('Akun login tidak ditemukan')
                                ->body('Pendaftaran ini belum memiliki akun login.')
                                ->send();

                            return;
                        }

                        $password = Str::password(10, symbols: false);

                        $user->update(['password' => $password]);

                        try {
                            Mail::to($user->email)->send(new KataSandiBaruCalonSiswa($user, $record, $password));
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->warning()
                                ->title('Kata sandi direset, email gagal dikirim')
                                ->body('Kata sandi sementara: '.$password.'. Sampaikan langsung ke calon siswa.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Kata sandi baru sudah dikirim')
                            ->body('Email tujuan: '.$user->email.'. Kata sandi sementara: '.$password)
                            ->persistent()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
