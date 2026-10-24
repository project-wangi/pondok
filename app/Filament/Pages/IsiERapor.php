<?php

namespace App\Filament\Pages;

use App\Models\AsesmenFormatif;
use App\Models\ERapor;
use App\Models\ERaporNilai;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use App\Models\NilaiUjianKomponen;
use App\Models\SesiMengajar;
use App\Models\SikapKeaktifan;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class IsiERapor extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'e-Rapor';

    protected string $view = 'filament.pages.isi-e-rapor';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    #[Locked]
    public ?int $eRaporId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<int, array{asts: int|string|null, asas: int|string|null}> */
    public array $nilai = [];

    public string $bobotFormatif = '10';

    public string $bobotSumatif = '15';

    public string $bobotAsts = '20';

    public string $bobotAsas = '35';

    public string $bobotKehadiran = '5';

    public string $bobotSikap = '5';

    public string $bobotKeaktifan = '10';

    public string $kkm = '75';

    public static function getRoutePath(Panel $panel): string
    {
        return '/isi-e-rapor/{kelas}/{mapel}';
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function mount(int $kelas, int $mapel): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($kelas <= 0 || $mapel <= 0, 404);
        $this->kelasId = $kelas;
        $this->mataPelajaranId = $mapel;
        $this->assertTeachesClassSubject();
        $this->loadRapor();
    }

    public function simpan(): void
    {
        $this->assertTeachesClassSubject();

        $bobot = Validator::make($this->normalizedBobot(), [
            'bobotFormatif' => ['required', 'integer', 'between:0,100'],
            'bobotSumatif' => ['required', 'integer', 'between:0,100'],
            'bobotAsts' => ['required', 'integer', 'between:0,100'],
            'bobotAsas' => ['required', 'integer', 'between:0,100'],
            'bobotKehadiran' => ['required', 'integer', 'between:0,100'],
            'bobotSikap' => ['required', 'integer', 'between:0,100'],
            'bobotKeaktifan' => ['required', 'integer', 'between:0,100'],
        ], [
            'bobotFormatif.required' => 'Bobot nilai formatif wajib diisi.',
            'bobotSumatif.required' => 'Bobot sumatif lingkup materi wajib diisi.',
            'bobotAsts.required' => 'Bobot ASTS wajib diisi.',
            'bobotAsas.required' => 'Bobot ASAS wajib diisi.',
            'bobotKehadiran.required' => 'Bobot kehadiran wajib diisi.',
            'bobotSikap.required' => 'Bobot sikap wajib diisi.',
            'bobotKeaktifan.required' => 'Bobot keaktifan wajib diisi.',
            'bobotFormatif.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotSumatif.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotAsts.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotAsas.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotKehadiran.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotSikap.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotKeaktifan.between' => 'Bobot harus berada antara 0 dan 100.',
            'bobotFormatif.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotSumatif.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotAsts.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotAsas.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotKehadiran.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotSikap.integer' => 'Bobot harus berupa bilangan bulat.',
            'bobotKeaktifan.integer' => 'Bobot harus berupa bilangan bulat.',
        ])->validate();

        $kkm = Validator::make(['kkm' => $this->normalizeIntegerInput($this->kkm)], [
            'kkm' => ['required', 'integer', 'between:0,100'],
        ], [
            'kkm.required' => 'Batas nilai ketuntasan wajib diisi.',
            'kkm.integer' => 'Batas nilai ketuntasan harus berupa bilangan bulat.',
            'kkm.between' => 'Batas nilai ketuntasan harus berada antara 0 dan 100.',
        ])->validate()['kkm'];

        $total = array_sum($bobot);

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'bobot' => 'Total bobot harus 100%. Saat ini '.$total.'%.',
            ]);
        }

        DB::transaction(function () use ($bobot, $kkm): void {
            $schedule = $this->scheduleQuery()->lockForUpdate()->firstOrFail();
            $students = Murid::query()->where('kelas_id', $this->kelasId)
                ->orderBy('nama')->orderBy('id')->lockForUpdate()->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['nilai' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas.']);
            }

            $rapor = $this->raporFor(lock: true);
            abort_if($rapor !== null && $rapor->pencatat_id !== Filament::auth()->id(), 403);

            if ($rapor?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['nilai' => 'Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.']);
            }

            $data = Validator::make(['nilai' => $this->normalizedNilai($students)], [
                'nilai' => ['array'],
                'nilai.*' => ['array'],
                'nilai.*.asts' => ['nullable', 'integer', 'between:0,100'],
                'nilai.*.asas' => ['nullable', 'integer', 'between:0,100'],
            ], [
                'nilai.*.asts.integer' => 'Nilai ASTS harus berupa bilangan bulat.',
                'nilai.*.asts.between' => 'Nilai ASTS harus berada antara 0 dan 100.',
                'nilai.*.asas.integer' => 'Nilai ASAS harus berupa bilangan bulat.',
                'nilai.*.asas.between' => 'Nilai ASAS harus berada antara 0 dan 100.',
            ])->validate();

            $settings = [
                'bobot_formatif' => $bobot['bobotFormatif'],
                'bobot_sumatif' => $bobot['bobotSumatif'],
                'bobot_asts' => $bobot['bobotAsts'],
                'bobot_asas' => $bobot['bobotAsas'],
                'bobot_kehadiran' => $bobot['bobotKehadiran'],
                'bobot_sikap' => $bobot['bobotSikap'],
                'bobot_keaktifan' => $bobot['bobotKeaktifan'],
                'kkm' => $kkm,
            ];

            if ($rapor === null) {
                $rapor = ERapor::query()->create([
                    'guru_id' => $schedule->guru_id,
                    'kelas_id' => $schedule->kelas_id,
                    'mata_pelajaran_id' => (int) $schedule->mata_pelajaran,
                    'pencatat_id' => Filament::auth()->id(),
                    'revisi' => 1,
                    'semester' => $schedule->semester,
                    'tahun_ajaran' => $schedule->tahun_ajaran,
                    'nama_guru' => $schedule->guru->user->name,
                    'nama_kelas' => $schedule->kelas->nama_kelas,
                    'nama_mapel' => $schedule->mataPelajaran->nama_mapel,
                    ...$settings,
                    'dicatat_pada' => now(),
                ]);
            } else {
                $rapor->update($settings);
                $rapor->increment('revisi');
            }

            $rapor->nilais()->delete();
            $now = now();
            $records = [];

            foreach ($students as $student) {
                $asts = $data['nilai'][$student->id]['asts'] ?? null;
                $asas = $data['nilai'][$student->id]['asas'] ?? null;

                if ($asts === null && $asas === null) {
                    continue;
                }

                $records[] = [
                    'e_rapor_id' => $rapor->id,
                    'murid_id' => $student->id,
                    'nama_murid' => $student->nama,
                    'nis' => $student->nis,
                    'asts' => $asts,
                    'asas' => $asas,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($records !== []) {
                ERaporNilai::query()->insert($records);
            }
        }, attempts: 3);

        $this->loadRapor();
        Notification::make()->success()->title('e-Rapor tersimpan')
            ->body('Nilai ASTS, ASAS, bobot, dan batas ketuntasan tersimpan.')->send();
        $this->redirect(InputERapor::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $class = Kelas::query()->find($this->kelasId);
        $subject = MataPelajaran::query()->find($this->mataPelajaranId);
        $students = $this->students();
        $period = JadwalPelajaran::activePeriod();
        $guruId = $this->guruId();
        $formatif = $this->formativeAverages($guruId, $period);
        $ujian = $this->examScores($guruId, $period);
        $kehadiran = $this->attendance($period);
        $sikap = $this->sikapScores($guruId, $period);
        $bobot = $this->currentBobot();
        $totalBobot = array_sum($bobot);
        $kkm = $this->toInteger($this->kkm) ?? 75;
        $rows = [];

        foreach ($students as $student) {
            $asts = $this->normalizeScore($this->nilai[$student->id]['asts'] ?? null);
            $asas = $this->normalizeScore($this->nilai[$student->id]['asas'] ?? null);
            $nilaiAkhir = $this->nilaiAkhir([
                'formatif' => $formatif[$student->id] ?? null,
                'sumatif' => $ujian['averages'][$student->id] ?? null,
                'asts' => $this->numericOrNull($asts),
                'asas' => $this->numericOrNull($asas),
                'kehadiran' => $kehadiran[$student->id] ?? null,
                'sikap' => $sikap['sikap'][$student->id] ?? null,
                'keaktifan' => $sikap['keaktifan'][$student->id] ?? null,
            ], $bobot);

            $rows[] = [
                'student' => $student,
                'formatif' => $formatif[$student->id] ?? null,
                'uh' => $ujian['scores'][$student->id] ?? [],
                'sumatif' => $ujian['averages'][$student->id] ?? null,
                'asts' => $asts,
                'asas' => $asas,
                'kehadiran' => $kehadiran[$student->id] ?? null,
                'sikap' => $sikap['sikap'][$student->id] ?? null,
                'keaktifan' => $sikap['keaktifan'][$student->id] ?? null,
                'nilai_akhir' => $nilaiAkhir,
                'ket' => $nilaiAkhir >= $kkm ? 'Tuntas' : 'Belum',
                'peringkat' => null,
            ];
        }

        $ranked = [];

        foreach ($rows as $row) {
            if ($row['nilai_akhir'] > 0) {
                $ranked[$row['nilai_akhir']] = true;
            }
        }

        $scores = array_keys($ranked);
        rsort($scores);
        $rankByScore = [];

        foreach ($scores as $index => $score) {
            $rankByScore[$score] = $index + 1;
        }

        foreach ($rows as $index => $row) {
            $rows[$index]['peringkat'] = $row['nilai_akhir'] > 0 ? ($rankByScore[$row['nilai_akhir']] ?? null) : null;
        }

        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'class' => $class,
            'subject' => $subject,
            'students' => $students,
            'rows' => $rows,
            'components' => $ujian['components'],
            'bobot' => $bobot,
            'totalBobot' => $totalBobot,
            'kkm' => $kkm,
            'maleCount' => $male,
            'femaleCount' => $female,
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'backUrl' => InputERapor::getUrl(),
            'nilaiUjianUrl' => InputNilaiUjian::getUrl(),
            'asesmenUrl' => InputAsesmenFormatif::getUrl(),
            'sikapUrl' => InputSikapKeaktifan::getUrl(),
            'presensiUrl' => PresensiSiswa::getUrl(),
        ];
    }

    /** @return Collection<int, Murid> */
    private function students(): Collection
    {
        return Murid::query()->where('kelas_id', $this->kelasId)
            ->orderBy('nama')->orderBy('id')->get();
    }

    private function guruId(): ?int
    {
        return Filament::auth()->user()?->guru()->value('id');
    }

    private function scheduleQuery(): Builder
    {
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->where('guru_id', $this->guruId())
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran', $this->mataPelajaranId)
            ->with(['kelas', 'mataPelajaran', 'guru.user']);
    }

    private function raporFor(bool $lock = false): ?ERapor
    {
        $period = JadwalPelajaran::activePeriod();

        return ERapor::query()
            ->where('guru_id', $this->guruId())
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with('nilais')
            ->first();
    }

    private function assertTeachesClassSubject(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if(! $this->scheduleQuery()->exists(), 404);
    }

    private function loadRapor(): void
    {
        $students = $this->students();
        $rapor = $this->raporFor();
        abort_if($rapor !== null && $rapor->pencatat_id !== Filament::auth()->id(), 403);
        $this->eRaporId = $rapor?->id;
        $this->revisi = $rapor?->revisi;

        if ($rapor !== null) {
            $this->bobotFormatif = (string) $rapor->bobot_formatif;
            $this->bobotSumatif = (string) $rapor->bobot_sumatif;
            $this->bobotAsts = (string) $rapor->bobot_asts;
            $this->bobotAsas = (string) $rapor->bobot_asas;
            $this->bobotKehadiran = (string) $rapor->bobot_kehadiran;
            $this->bobotSikap = (string) $rapor->bobot_sikap;
            $this->bobotKeaktifan = (string) $rapor->bobot_keaktifan;
            $this->kkm = (string) $rapor->kkm;
        }

        $this->nilai = [];

        foreach ($students as $student) {
            $this->nilai[$student->id] = ['asts' => null, 'asas' => null];
        }

        foreach ($rapor?->nilais ?? [] as $row) {
            if (array_key_exists($row->murid_id, $this->nilai)) {
                $this->nilai[$row->murid_id] = ['asts' => $row->asts, 'asas' => $row->asas];
            }
        }
    }

    /** @return array<string, mixed> */
    private function normalizedBobot(): array
    {
        return [
            'bobotFormatif' => $this->normalizeIntegerInput($this->bobotFormatif),
            'bobotSumatif' => $this->normalizeIntegerInput($this->bobotSumatif),
            'bobotAsts' => $this->normalizeIntegerInput($this->bobotAsts),
            'bobotAsas' => $this->normalizeIntegerInput($this->bobotAsas),
            'bobotKehadiran' => $this->normalizeIntegerInput($this->bobotKehadiran),
            'bobotSikap' => $this->normalizeIntegerInput($this->bobotSikap),
            'bobotKeaktifan' => $this->normalizeIntegerInput($this->bobotKeaktifan),
        ];
    }

    /** @return array<string, int> */
    private function currentBobot(): array
    {
        return [
            'formatif' => $this->toInteger($this->bobotFormatif) ?? 0,
            'sumatif' => $this->toInteger($this->bobotSumatif) ?? 0,
            'asts' => $this->toInteger($this->bobotAsts) ?? 0,
            'asas' => $this->toInteger($this->bobotAsas) ?? 0,
            'kehadiran' => $this->toInteger($this->bobotKehadiran) ?? 0,
            'sikap' => $this->toInteger($this->bobotSikap) ?? 0,
            'keaktifan' => $this->toInteger($this->bobotKeaktifan) ?? 0,
        ];
    }

    /**
     * @param  Collection<int, Murid>  $students
     * @return array<int, array{asts: mixed, asas: mixed}>
     */
    private function normalizedNilai(Collection $students): array
    {
        $normalized = [];

        foreach ($students as $student) {
            $normalized[$student->id] = [
                'asts' => $this->normalizeScore($this->nilai[$student->id]['asts'] ?? null),
                'asas' => $this->normalizeScore($this->nilai[$student->id]['asas'] ?? null),
            ];
        }

        return $normalized;
    }

    private function normalizeScore(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private function toInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeIntegerInput(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private function numericOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
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

    /** @param  array{semester: string, tahun_ajaran: string}  $period */
    private function formativeAverages(?int $guruId, array $period): array
    {
        $assessment = AsesmenFormatif::query()
            ->where('guru_id', $guruId)
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
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

        $averages = [];

        foreach ($scores as $muridId => $values) {
            $averages[$muridId] = (int) round(array_sum($values) / count($values));
        }

        return $averages;
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array{components: Collection<int, NilaiUjianKomponen>, scores: array<int, array<int, int|null>>, averages: array<int, int>}
     */
    private function examScores(?int $guruId, array $period): array
    {
        $exam = NilaiUjian::query()
            ->where('guru_id', $guruId)
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->with('komponens.nilais')
            ->first();
        $components = $exam?->komponens ?? new Collection;
        $scores = [];

        foreach ($components as $index => $component) {
            foreach ($component->nilais as $row) {
                if ($row->murid_id !== null) {
                    $scores[$row->murid_id][$index] = $row->nilai;
                }
            }
        }

        $averages = [];

        foreach ($scores as $muridId => $values) {
            $filled = array_filter($values, fn (?int $value): bool => $value !== null);

            if ($filled !== []) {
                $averages[$muridId] = (int) round(array_sum($filled) / count($filled));
            }
        }

        return ['components' => $components, 'scores' => $scores, 'averages' => $averages];
    }

    /** @param  array{semester: string, tahun_ajaran: string}  $period */
    private function attendance(array $period): array
    {
        $sessions = SesiMengajar::query()
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereHas('jadwalPelajaran', function (Builder $query) use ($period): void {
                $query->where('kelas_id', $this->kelasId)
                    ->where('mata_pelajaran', $this->mataPelajaranId)
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
     * @return array{sikap: array<int, int|null>, keaktifan: array<int, int|null>}
     */
    private function sikapScores(?int $guruId, array $period): array
    {
        $entry = SikapKeaktifan::query()
            ->where('guru_id', $guruId)
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
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
}
