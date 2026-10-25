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
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('semester')
                    ->badge(),
                TextColumn::make('tahun_ajaran')
                    ->label('Tahun ajaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hari')
                    ->searchable(),
                TextColumn::make('kelas.nama_kelas')
                    ->searchable(),
                TextColumn::make('guru.nama')
                    ->label('Guru')
                    ->searchable(),
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
                SelectFilter::make('semester')
                    ->options(JadwalPelajaran::SEMESTERS),
                SelectFilter::make('tahun_ajaran')
                    ->label('Tahun ajaran')
                    ->options(fn (): array => JadwalPelajaran::query()
                        ->distinct()->orderByDesc('tahun_ajaran')
                        ->pluck('tahun_ajaran', 'tahun_ajaran')->all()),
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
                    ->modalDescription('Satu sheet per kelas. Data disaring berdasarkan semester dan tahun ajaran yang dipilih, serta pencarian tabel yang sedang aktif.')
                    ->modalSubmitActionLabel('Unduh Excel')
                    ->schema([
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->placeholder('Semua kelas dalam hasil tabel')
                            ->options(fn (): array => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                            ->searchable()
                            ->rules(['nullable', 'integer', 'exists:kelas,id']),
                        Select::make('semester')
                            ->options(JadwalPelajaran::SEMESTERS)
                            ->default(fn (): string => JadwalPelajaran::activePeriod()['semester'])
                            ->required(),
                        TextInput::make('tahun_ajaran')
                            ->label('Tahun ajaran')
                            ->placeholder('2025/2026')
                            ->default(fn (): string => JadwalPelajaran::activePeriod()['tahun_ajaran'])
                            ->required()
                            ->rules([
                                'regex:/^[0-9]{4}\/[0-9]{4}$/',
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $years = explode('/', (string) $value);

                                    if (count($years) !== 2 || (int) $years[1] !== (int) $years[0] + 1) {
                                        $fail('Tahun ajaran harus berurutan, misalnya 2025/2026.');
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
