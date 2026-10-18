<?php

namespace App\Filament\Resources\JadwalPelajarans\Tables;

use App\Filament\Exports\JadwalPelajaranWorkbook;
use App\Filament\Resources\JadwalPelajarans\JadwalPelajaranResource;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JadwalPelajaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_index')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('hari')
                    ->searchable(),
                TextColumn::make('kelas.nama_kelas')
                    ->searchable(),
                TextColumn::make('guru.nama')
                    ->label('Guru')
                    ->sortable(),
                TextColumn::make('mataPelajaran.nama_mapel')
                    ->searchable(),
                TextColumn::make('jam_mulai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('jam_selesai')
                    ->time('H:i')
                    ->sortable(),
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

            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->authorize(fn (): bool => JadwalPelajaranResource::canViewAny())
                    ->modalHeading('Export jadwal pelajaran')
                    ->modalDescription('Satu sheet per kelas, mengikuti pencarian tabel. Semester dan tahun ajaran hanya untuk judul dokumen, bukan penyaring data jadwal.')
                    ->modalSubmitActionLabel('Unduh Excel')
                    ->schema([
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->placeholder('Semua kelas dalam hasil tabel')
                            ->options(fn (): array => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                            ->searchable()
                            ->rules(['nullable', 'integer', 'exists:kelas,id']),
                        Select::make('semester')
                            ->options(['Ganjil' => 'Ganjil', 'Genap' => 'Genap'])
                            ->default(fn (): string => now()->month <= 6 ? 'Genap' : 'Ganjil')
                            ->required(),
                        TextInput::make('tahun_ajaran')
                            ->label('Tahun ajaran')
                            ->placeholder('2026/2027')
                            ->default(function (): string {
                                $year = now()->year - (now()->month <= 6 ? 1 : 0);

                                return $year.'/'.($year + 1);
                            })
                            ->required()
                            ->rules([
                                'regex:/^[0-9]{4}\/[0-9]{4}$/',
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $years = explode('/', (string) $value);

                                    if (count($years) !== 2 || (int) $years[1] !== (int) $years[0] + 1) {
                                        $fail('Tahun ajaran harus berurutan, misalnya 2026/2027.');
                                    }
                                },
                            ]),
                    ])
                    ->action(function (array $data, HasTable $livewire): StreamedResponse {
                        Gate::authorize('viewAny', JadwalPelajaran::class);

                        return app(JadwalPelajaranWorkbook::class)->download(
                            $livewire->getFilteredSortedTableQuery(),
                            $data['semester'],
                            $data['tahun_ajaran'],
                            filled($data['kelas_id'] ?? null) ? (int) $data['kelas_id'] : null,
                        );
                    }),
            ]);
    }
}
