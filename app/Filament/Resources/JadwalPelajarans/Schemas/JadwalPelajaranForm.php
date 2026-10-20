<?php

namespace App\Filament\Resources\JadwalPelajarans\Schemas;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
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
                    ->rules(['required', Rule::in(array_keys(JadwalPelajaran::SEMESTERS))]),
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
                    ->required(),
                Select::make('guru_id')
                    ->relationship('guru', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Guru $record) => $record->nama ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('mata_pelajaran')
                    ->relationship('mataPelajaran', 'nama_mapel')
                    ->required()
                    ->searchable()
                    ->preload(),
                TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->required(),
                Select::make('kelas_id')
                    ->relationship('kelas', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Kelas $record) => $record->nama_kelas ?? $record->id)
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }
}
