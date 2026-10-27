<?php

namespace App\Filament\Pages;

use App\Filament\Exports\RaporWorkbook;
use App\Models\AsesmenFormatif;
use App\Models\ERapor;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use App\Models\PresensiMurid;
use App\Models\RaporKepribadian;
use App\Models\SesiMengajar;
use App\Models\SikapKeaktifan;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

class RaporAdmin extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Rapor Siswa';

    protected static ?string $title = 'Rapor Siswa';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'rapor';

    protected string $view = 'filament.pages.rapor-admin';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $muridId = null;

    /** @var array<string, string> */
    public array $kepribadian = [
        'kelakuan' => '',
        'kerajinan' => '',
        'kebersihan' => '',
        'kerapian' => '',
        'kedisiplinan' => '',
    ];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', config('filament-shield.super_admin.name', 'super_admin')])
            || $user->can('ViewAny:Murid');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function pilihKelas(int $kelasId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(Kelas::query()->whereKey($kelasId)->exists(), 404);

        $this->kelasId = $kelasId;
        $this->muridId = null;
        $this->resetKepribadian();
    }

    public function pilihMurid(int $muridId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($this->kelasId === null, 404);

        $murid = Murid::query()
            ->where('kelas_id', $this->kelasId)
            ->findOrFail($muridId);

        $this->muridId = $murid->id;
        $this->muatKepribadian();
    }

    public function kembaliKeDaftarMurid(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->muridId = null;
        $this->resetKepribadian();
    }

    public function kembaliKeDaftarKelas(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->kelasId = null;
        $this->muridId = null;
        $this->resetKepribadian();
    }

    public function simpanKepribadian(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($this->kelasId === null || $this->muridId === null, 404);

        $murid = Murid::query()
            ->where('kelas_id', $this->kelasId)
            ->findOrFail($this->muridId);

        $attributes = [];
        $rules = [];

        foreach (RaporKepribadian::ASPEK as $key => $label) {
            $attributes['kepribadian.'.$key] = $label;
            $rules['kepribadian.'.$key] = ['required', 'integer', 'between:0,100'];
        }

        $validated = $this->validate($rules, [
            'kepribadian.*.required' => 'Nilai :attribute wajib diisi.',
            'kepribadian.*.integer' => 'Nilai :attribute harus berupa angka bulat.',
            'kepribadian.*.between' => 'Nilai :attribute harus berada antara 0 dan 100.',
        ], $attributes);

        $period = JadwalPelajaran::activePeriod();

        RaporKepribadian::query()->updateOrCreate(
            [
                'murid_id' => $murid->id,
                'semester' => $period['semester'],
                'tahun_ajaran' => $period['tahun_ajaran'],
            ],
            array_map('intval', $validated['kepribadian']),
        );

        Notification::make()
            ->success()
            ->title('Kepribadian tersimpan')
            ->body('Huruf kepribadian dibuat otomatis dari angka yang diisi.')
            ->send();
    }

    public function export(): mixed
    {
        abort_unless(static::canAccess(), 403);

        $report = $this->laporanSiswa();
        abort_if($report === null, 404);

        return app(RaporWorkbook::class)->download($report);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $period = JadwalPelajaran::activePeriod();
        $view = [
            'langkah' => 'kelas',
            'period' => $period,
            'aspekKepribadian' => RaporKepribadian::ASPEK,
            'hurufKepribadian' => $this->hurufKepribadian(),
        ];

        if ($this->kelasId === null) {
            $view['kelasList'] = $this->kelasList($period);

            return $view;
        }

        $kelas = Kelas::query()->find($this->kelasId);
        abort_if($kelas === null, 404);

        $matrix = $this->scoreMatrix($kelas->id, $period);
        $ranked = $this->rankedStudents($kelas->id, $matrix);

        $view['kelas'] = $kelas;
        $view['daftarMurid'] = $ranked;

        if ($this->muridId === null) {
            $view['langkah'] = 'murid';

            return $view;
        }

        $murid = Murid::query()
            ->where('kelas_id', $kelas->id)
            ->findOrFail($this->muridId);

        $view['langkah'] = 'rapor';
        $view['rapor'] = $this->laporanSiswa($kelas, $murid, $period, $matrix, $ranked);

        return $view;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return Collection<int, array{kelas: Kelas, jumlah_murid: int, jumlah_mapel: int}>
     */
    private function kelasList(array $period): Collection
    {
        $muridCounts = Murid::query()
            ->whereNotNull('kelas_id')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $mapelCounts = ERapor::query()
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereNotNull('kelas_id')
            ->whereNotNull('mata_pelajaran_id')
            ->whereHas('nilais', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereNotNull('asts')->orWhereNotNull('asas'),
            ))
            ->selectRaw('kelas_id, count(distinct mata_pelajaran_id) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        return Kelas::query()
            ->get()
            ->sortBy(fn (Kelas $kelas): array => [self::tingkatKelas($kelas->nama_kelas), $kelas->nama_kelas])
            ->values()
            ->map(fn (Kelas $kelas): array => [
                'kelas' => $kelas,
                'jumlah_murid' => (int) ($muridCounts[$kelas->id] ?? 0),
                'jumlah_mapel' => (int) ($mapelCounts[$kelas->id] ?? 0),
            ]);
    }

    /**
     * @param  array<int, array<int, array{angka: int, kkm: int}>>  $matrix
     * @return Collection<int, array{murid: Murid, rata_rata: int|null, jumlah_mapel: int, peringkat: int|null}>
     */
    private function rankedStudents(int $kelasId, array $matrix): Collection
    {
        $rows = Murid::query()
            ->where('kelas_id', $kelasId)
            ->orderBy('nama')
            ->orderBy('id')
            ->get()
            ->map(function (Murid $murid) use ($matrix): array {
                $scores = $matrix[$murid->id] ?? [];

                return [
                    'murid' => $murid,
                    'rata_rata' => $scores === []
                        ? null
                        : (int) round(array_sum(array_column($scores, 'angka')) / count($scores)),
                    'jumlah_mapel' => count($scores),
                    'peringkat' => null,
                ];
            });

        $sorted = $rows->sort(function (array $a, array $b): int {
            if ($a['rata_rata'] === null && $b['rata_rata'] === null) {
                return strcasecmp($a['murid']->nama, $b['murid']->nama);
            }

            if ($a['rata_rata'] === null) {
                return 1;
            }

            if ($b['rata_rata'] === null) {
                return -1;
            }

            $byScore = $b['rata_rata'] <=> $a['rata_rata'];

            return $byScore !== 0 ? $byScore : strcasecmp($a['murid']->nama, $b['murid']->nama);
        })->values();

        $previous = null;
        $rank = 0;

        return $sorted->map(function (array $row, int $index) use (&$previous, &$rank): array {
            if ($row['rata_rata'] === null) {
                return $row;
            }

            if ($previous === null || $row['rata_rata'] !== $previous) {
                $rank = $index + 1;
                $previous = $row['rata_rata'];
            }

            $row['peringkat'] = $rank;

            return $row;
        })->values();
    }

    /**
     * Hitung nilai akhir setiap murid per mata pelajaran dari e-Rapor yang sudah dikirim.
     *
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<int, array<int, array{angka: int, kkm: int}>>
     */
    private function scoreMatrix(int $kelasId, array $period): array
    {
        $rapors = ERapor::query()
            ->where('kelas_id', $kelasId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereNotNull('mata_pelajaran_id')
            ->with('nilais')
            ->orderByDesc('dicatat_pada')
            ->orderByDesc('id')
            ->get();

        $matrix = [];

        // Satu nilai akhir per mata pelajaran memakai e-Rapor terbaru.
        foreach ($rapors->unique('mata_pelajaran_id') as $rapor) {
            $formatif = $this->formatifScores($rapor, $period);
            $sumatif = $this->sumatifScores($rapor, $period);
            $kehadiran = $this->attendanceScores($rapor, $period);
            $sikap = $this->sikapScores($rapor, $period);
            $bobot = [
                'formatif' => (int) $rapor->bobot_formatif,
                'sumatif' => (int) $rapor->bobot_sumatif,
                'asts' => (int) $rapor->bobot_asts,
                'asas' => (int) $rapor->bobot_asas,
                'kehadiran' => (int) $rapor->bobot_kehadiran,
                'sikap' => (int) $rapor->bobot_sikap,
                'keaktifan' => (int) $rapor->bobot_keaktifan,
            ];
            $kkm = (int) ($rapor->kkm ?? 75);

            foreach ($rapor->nilais as $nilai) {
                if ($nilai->murid_id === null) {
                    continue;
                }

                if ($nilai->asts === null && $nilai->asas === null) {
                    continue;
                }

                $matrix[$nilai->murid_id][$rapor->mata_pelajaran_id] = [
                    'angka' => $this->nilaiAkhir([
                        'formatif' => $formatif[$nilai->murid_id] ?? null,
                        'sumatif' => $sumatif[$nilai->murid_id] ?? null,
                        'asts' => $nilai->asts,
                        'asas' => $nilai->asas,
                        'kehadiran' => $kehadiran[$nilai->murid_id] ?? null,
                        'sikap' => $sikap['sikap'][$nilai->murid_id] ?? null,
                        'keaktifan' => $sikap['keaktifan'][$nilai->murid_id] ?? null,
                    ], $bobot),
                    'kkm' => $kkm,
                ];
            }
        }

        return $matrix;
    }

    /**
     * @param  array<int, array<int, array{angka: int, kkm: int}>>|null  $matrix
     * @param  Collection<int, array{murid: Murid, rata_rata: int|null, jumlah_mapel: int, peringkat: int|null}>|null  $ranked
     * @param  array{semester: string, tahun_ajaran: string}|null  $period
     * @return array<string, mixed>|null
     */
    private function laporanSiswa(
        ?Kelas $kelas = null,
        ?Murid $murid = null,
        ?array $period = null,
        ?array $matrix = null,
        ?Collection $ranked = null,
    ): ?array {
        $period ??= JadwalPelajaran::activePeriod();
        $kelas ??= $this->kelasId !== null ? Kelas::query()->find($this->kelasId) : null;

        if ($kelas === null) {
            return null;
        }

        $murid ??= $this->muridId !== null
            ? Murid::query()->where('kelas_id', $kelas->id)->find($this->muridId)
            : null;

        if ($murid === null) {
            return null;
        }

        $matrix ??= $this->scoreMatrix($kelas->id, $period);
        $ranked ??= $this->rankedStudents($kelas->id, $matrix);
        $scores = $matrix[$murid->id] ?? [];

        $subjects = $this->subjectsForClass($kelas->id, $period)
            ->map(fn (MataPelajaran $subject): array => [
                'nama' => $subject->nama_mapel,
                'kkm' => $scores[$subject->id]['kkm'] ?? null,
                'angka' => $scores[$subject->id]['angka'] ?? null,
                'huruf' => RaporKepribadian::hurufUntuk($scores[$subject->id]['angka'] ?? null),
            ])
            ->values();

        $angka = $subjects->pluck('angka')->filter(fn (mixed $value): bool => $value !== null);
        $jumlah = $angka->isEmpty() ? null : (int) $angka->sum();
        $rataRata = $angka->isEmpty() ? null : (int) round((float) $angka->avg());
        $rankedRow = $ranked->first(fn (array $row): bool => $row['murid']->id === $murid->id);
        $kepribadian = RaporKepribadian::query()
            ->where('murid_id', $murid->id)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->first();

        $kepribadianRows = collect(RaporKepribadian::ASPEK)
            ->map(fn (string $label, string $key): array => [
                'label' => $label,
                'angka' => $kepribadian?->{$key},
                'huruf' => RaporKepribadian::hurufUntuk($kepribadian?->{$key}),
            ])
            ->values();

        $waliKelas = Guru::query()
            ->where('kelas_id', $kelas->id)
            ->with('user')
            ->first();
        $kepalaMadrasah = Guru::query()
            ->whereHas('jabatans', fn (Builder $query): Builder => $query->where('nama_jabatan', 'Kepala Tim Manajemen'))
            ->with('user')
            ->first();
        $school = SiteSetting::current();
        $noAbsen = Murid::query()
            ->where('kelas_id', $kelas->id)
            ->orderBy('nama')
            ->orderBy('id')
            ->pluck('id')
            ->search($murid->id);

        return [
            'school_name' => (string) $school->school_name,
            'school_address' => (string) ($school->address ?? ''),
            'semester' => $period['semester'],
            'tahun_ajaran' => $period['tahun_ajaran'],
            'class' => (string) $kelas->nama_kelas,
            'student' => [
                'nama' => (string) $murid->nama,
                'nis' => $murid->nis,
                'nisn' => $murid->nisn,
                'no_absen' => (int) $noAbsen + 1,
            ],
            'subjects' => $subjects->all(),
            'jumlah' => $jumlah,
            'rata_rata' => $rataRata,
            'peringkat' => $rankedRow['peringkat'] ?? null,
            'jumlah_siswa' => $ranked->count(),
            'peminatan' => $murid->jenisPeminatans->pluck('nama_peminatan')->implode(', '),
            'kepribadian' => $kepribadianRows->all(),
            'ketidakhadiran' => $this->ketidakhadiran($murid, $kelas->id, $period),
            'wali_kelas' => $waliKelas?->user?->name,
            'kepala_madrasah' => $kepalaMadrasah?->user?->name,
            'orang_tua' => $this->namaOrangTua($murid),
            'catatan' => 'Nilai KKM adalah batas minimal ketuntasan. Sel angka dan huruf yang kosong berarti guru belum mengirim e-Rapor untuk mata pelajaran tersebut.',
        ];
    }

    /**
     * Nama orang tua untuk tanda tangan: ayah yang masih hidup lebih dahulu,
     * lalu ibu, lalu nama orang tua yang tercatat.
     */
    private function namaOrangTua(Murid $murid): ?string
    {
        if (filled($murid->nama_ayah) && $murid->status_ayah !== 'Meninggal') {
            return $murid->nama_ayah;
        }

        if (filled($murid->nama_ibu) && $murid->status_ibu !== 'Meninggal') {
            return $murid->nama_ibu;
        }

        return $murid->nama_ayah ?: $murid->nama_ibu;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return Collection<int, MataPelajaran>
     */
    private function subjectsForClass(int $kelasId, array $period): Collection
    {
        $scheduled = JadwalPelajaran::query()
            ->where('kelas_id', $kelasId)
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->with('mataPelajaran')
            ->get()
            ->pluck('mataPelajaran')
            ->filter()
            ->pluck('id');

        $submitted = ERapor::query()
            ->where('kelas_id', $kelasId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereNotNull('mata_pelajaran_id')
            ->pluck('mata_pelajaran_id');

        $ids = $scheduled->merge($submitted)->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return MataPelajaran::query()
            ->whereIn('id', $ids)
            ->orderBy('nama_mapel')
            ->get();
    }

    /**
     * Kehadiran hanya dari sesi pada kelas dan periode berjalan.
     *
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<int, int>
     */
    private function attendanceScores(ERapor $rapor, array $period): array
    {
        $sessions = SesiMengajar::query()
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereHas('jadwalPelajaran', function (Builder $query) use ($rapor, $period): void {
                $query->where('kelas_id', $rapor->kelas_id)
                    ->where('mata_pelajaran', $rapor->mata_pelajaran_id)
                    ->where('semester', $period['semester'])
                    ->where('tahun_ajaran', $period['tahun_ajaran']);
            })
            ->with('presensiMurids')
            ->get();

        $present = [];
        $total = [];

        foreach ($sessions as $session) {
            foreach ($session->presensiMurids as $attendance) {
                if ($attendance->murid_id === null) {
                    continue;
                }

                $total[$attendance->murid_id] = ($total[$attendance->murid_id] ?? 0) + 1;

                if ($attendance->status === 'Hadir') {
                    $present[$attendance->murid_id] = ($present[$attendance->murid_id] ?? 0) + 1;
                }
            }
        }

        $result = [];

        foreach ($total as $muridId => $count) {
            if ($count > 0) {
                $result[$muridId] = (int) round((($present[$muridId] ?? 0) / $count) * 100);
            }
        }

        return $result;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<int, int>
     */
    private function formatifScores(ERapor $rapor, array $period): array
    {
        $assessment = AsesmenFormatif::query()
            ->where('guru_id', $rapor->guru_id)
            ->where('kelas_id', $rapor->kelas_id)
            ->where('mata_pelajaran_id', $rapor->mata_pelajaran_id)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->with('nilais')
            ->first();
        $scores = [];

        foreach ($assessment?->nilais ?? [] as $row) {
            if ($row->murid_id !== null && $row->nilai !== null) {
                $scores[$row->murid_id][] = $row->nilai;
            }
        }

        $result = [];

        foreach ($scores as $muridId => $values) {
            $result[$muridId] = (int) round(array_sum($values) / count($values));
        }

        return $result;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<int, int>
     */
    private function sumatifScores(ERapor $rapor, array $period): array
    {
        $exam = NilaiUjian::query()
            ->where('guru_id', $rapor->guru_id)
            ->where('kelas_id', $rapor->kelas_id)
            ->where('mata_pelajaran_id', $rapor->mata_pelajaran_id)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->with('komponens.nilais')
            ->first();
        $scores = [];

        foreach ($exam?->komponens ?? [] as $component) {
            foreach ($component->nilais as $row) {
                if ($row->murid_id !== null) {
                    $scores[$row->murid_id][] = $row->nilai;
                }
            }
        }

        $result = [];

        foreach ($scores as $muridId => $values) {
            $filled = array_filter($values, fn (?int $value): bool => $value !== null);

            if ($filled !== []) {
                $result[$muridId] = (int) round(array_sum($filled) / count($filled));
            }
        }

        return $result;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array{sikap: array<int, int|null>, keaktifan: array<int, int|null>}
     */
    private function sikapScores(ERapor $rapor, array $period): array
    {
        $entry = SikapKeaktifan::query()
            ->where('guru_id', $rapor->guru_id)
            ->where('kelas_id', $rapor->kelas_id)
            ->where('mata_pelajaran_id', $rapor->mata_pelajaran_id)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->with('nilais')
            ->first();
        $sikap = [];
        $keaktifan = [];

        foreach ($entry?->nilais ?? [] as $row) {
            if ($row->murid_id === null) {
                continue;
            }

            $sikap[$row->murid_id] = $row->sikapAngka();
            $keaktifan[$row->murid_id] = $row->keaktifanAngka();
        }

        return ['sikap' => $sikap, 'keaktifan' => $keaktifan];
    }

    /**
     * @param  array<string, int|null>  $values
     * @param  array<string, int>  $bobot
     */
    private function nilaiAkhir(array $values, array $bobot): int
    {
        $total = 0;

        foreach ($bobot as $key => $weight) {
            $total += $weight * ($values[$key] ?? 0);
        }

        return (int) round($total / 100);
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array{sakit: int, izin: int, alpa: int}
     */
    private function ketidakhadiran(Murid $murid, int $kelasId, array $period): array
    {
        $counts = PresensiMurid::query()
            ->where('murid_id', $murid->id)
            ->whereIn('status', ['Sakit', 'Izin', 'Alpa'])
            ->whereHas('sesiMengajar', function (Builder $query) use ($kelasId, $period): void {
                $query->where('semester', $period['semester'])
                    ->where('tahun_ajaran', $period['tahun_ajaran'])
                    ->where(function (Builder $query) use ($kelasId): void {
                        $query->where('kelas_id', $kelasId)
                            ->orWhereHas('jadwalPelajaran', fn (Builder $jadwal): Builder => $jadwal->where('kelas_id', $kelasId));
                    });
            })
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'sakit' => (int) ($counts['Sakit'] ?? 0),
            'izin' => (int) ($counts['Izin'] ?? 0),
            'alpa' => (int) ($counts['Alpa'] ?? 0),
        ];
    }

    private function resetKepribadian(): void
    {
        $this->kepribadian = array_fill_keys(array_keys(RaporKepribadian::ASPEK), '');
    }

    private function muatKepribadian(): void
    {
        $this->resetKepribadian();

        if ($this->muridId === null) {
            return;
        }

        $period = JadwalPelajaran::activePeriod();
        $record = RaporKepribadian::query()
            ->where('murid_id', $this->muridId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->first();

        if ($record === null) {
            return;
        }

        foreach (array_keys(RaporKepribadian::ASPEK) as $key) {
            $this->kepribadian[$key] = $record->{$key} === null ? '' : (string) $record->{$key};
        }
    }

    /** @return array<string, string|null> */
    private function hurufKepribadian(): array
    {
        $result = [];

        foreach (array_keys(RaporKepribadian::ASPEK) as $key) {
            $value = trim((string) ($this->kepribadian[$key] ?? ''));
            $result[$key] = $value !== '' && ctype_digit($value)
                ? RaporKepribadian::hurufUntuk((int) $value)
                : null;
        }

        return $result;
    }

    private static function tingkatKelas(string $nama): int
    {
        $nama = mb_strtoupper(trim($nama));
        $tingkat = [
            'XII' => 12,
            'XI' => 11,
            'X' => 10,
            'IX' => 9,
            'VIII' => 8,
            'VII' => 7,
            'VI' => 6,
            'V' => 5,
            'IV' => 4,
            'III' => 3,
            'II' => 2,
            'I' => 1,
        ];

        foreach ($tingkat as $romawi => $level) {
            if (preg_match('/^'.$romawi.'(?:\s|$)/', $nama) === 1) {
                return $level;
            }
        }

        return 99;
    }
}
