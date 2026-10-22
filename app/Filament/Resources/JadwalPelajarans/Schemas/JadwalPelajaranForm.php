<?php

namespace App\Filament\Resources\JadwalPelajarans\Schemas;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class JadwalPelajaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('semester')
                    ->options(JadwalPelajaran::SEMESTERS)
                    ->default(fn (): string => JadwalPelajaran::activePeriod()['semester'])
                    ->required()
                    ->live()
                    ->rules(['required', Rule::in(array_keys(JadwalPelajaran::SEMESTERS))]),
                TextInput::make('tahun_ajaran')
                    ->label('Tahun ajaran')
                    ->placeholder('2025/2026')
                    ->default(fn (): string => JadwalPelajaran::activePeriod()['tahun_ajaran'])
                    ->required()
                    ->live(onBlur: true)
                    ->rules([
                        'regex:/^[0-9]{4}\/[0-9]{4}$/',
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $years = explode('/', (string) $value);

                            if (count($years) !== 2 || (int) $years[1] !== (int) $years[0] + 1) {
                                $fail('Tahun ajaran harus berurutan, misalnya 2025/2026.');
                            }
                        },
                    ]),
                Select::make('hari')
                    ->options([
                        'Senin' => 'Senin',
                        'Selasa' => 'Selasa',
                        'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis',
                        'Jumat' => 'Jumat',
                        'Sabtu' => 'Sabtu',
                        'Minggu' => 'Minggu',
                    ])
                    ->required()
                    ->live(),
                Select::make('mata_pelajaran')
                    ->relationship('mataPelajaran', 'nama_mapel')
                    ->required()
                    ->searchable()
                    ->preload(),
                TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required()
                    ->live(),
                TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required()
                    ->live(),
                Select::make('guru_id')
                    ->label('Guru')
                    ->options(fn (Get $get, ?Model $record): array => self::teacherOptions(
                        $get('semester'),
                        $get('tahun_ajaran'),
                        $get('hari'),
                        $get('jam_mulai'),
                        $get('jam_selesai'),
                        $record?->getKey(),
                    ))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Guru yang sudah mengajar pada hari dan jam yang sama tidak ditampilkan.')
                    ->rules([
                        fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $conflicts = self::conflictingTeacherIds(
                                $get('semester'),
                                $get('tahun_ajaran'),
                                $get('hari'),
                                $get('jam_mulai'),
                                $get('jam_selesai'),
                                $record?->getKey(),
                            );

                            if (in_array((int) $value, $conflicts, true)) {
                                $fail('Guru ini sudah memiliki jadwal lain pada hari dan jam yang sama.');
                            }
                        },
                    ]),
                Select::make('kelas_id')
                    ->relationship('kelas', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Kelas $record) => $record->nama_kelas ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }

    /**
     * Pilihan guru tanpa benturan jadwal pada hari dan jam yang sama.
     *
     * @return array<int, string>
     */
    public static function teacherOptions(
        mixed $semester,
        mixed $tahunAjaran,
        mixed $hari,
        mixed $jamMulai,
        mixed $jamSelesai,
        ?int $ignoreId = null,
    ): array {
        $conflictingIds = self::conflictingTeacherIds($semester, $tahunAjaran, $hari, $jamMulai, $jamSelesai, $ignoreId);

        return Guru::query()
            ->with('user')
            ->when($conflictingIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $conflictingIds))
            ->get()
            ->mapWithKeys(fn (Guru $guru): array => [$guru->id => $guru->nama ?? (string) $guru->id])
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function conflictingTeacherIds(
        mixed $semester,
        mixed $tahunAjaran,
        mixed $hari,
        mixed $jamMulai,
        mixed $jamSelesai,
        ?int $ignoreId = null,
    ): array {
        if (blank($semester) || blank($tahunAjaran) || blank($hari) || blank($jamMulai) || blank($jamSelesai)) {
            return [];
        }

        $start = CarbonImmutable::parse((string) $jamMulai)->format('H:i:s');
        $end = CarbonImmutable::parse((string) $jamSelesai)->format('H:i:s');

        return JadwalPelajaran::query()
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $end)
            ->where('jam_selesai', '>', $start)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->pluck('guru_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
