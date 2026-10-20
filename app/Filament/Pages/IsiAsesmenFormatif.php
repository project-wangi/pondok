<?php

namespace App\Filament\Pages;

use App\Models\AsesmenFormatif;
use App\Models\AsesmenFormatifNilai;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\Murid;
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

class IsiAsesmenFormatif extends Page
{
    private const KKM = 75;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'Input Asesmen Formatif';

    protected string $view = 'filament.pages.isi-asesmen-formatif';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    #[Locked]
    public ?int $asesmenId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<int, array<int, int|string|null>> */
    public array $nilai = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/isi-asesmen-formatif/{kelas}/{mapel}';
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
        $this->loadNilai();
    }

    public function simpan(): void
    {
        $this->assertTeachesClassSubject();

        DB::transaction(function (): void {
            $schedule = $this->scheduleQuery()->lockForUpdate()->firstOrFail();
            $students = Murid::query()->where('kelas_id', $this->kelasId)
                ->orderBy('nama')->orderBy('id')->lockForUpdate()->get();
            $materials = $this->materials(lock: true);

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['nilai' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas.']);
            }

            if ($materials->isEmpty()) {
                throw ValidationException::withMessages(['nilai' => 'Belum ada tujuan pembelajaran untuk mapel ini. Isi lingkup materi terlebih dahulu.']);
            }

            $assessment = $this->assessmentFor(lock: true);
            abort_if($assessment !== null && $assessment->pencatat_id !== Filament::auth()->id(), 403);

            if ($assessment?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['nilai' => 'Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.']);
            }

            $normalized = $this->normalizedScores($students, $materials);
            $data = Validator::make(['nilai' => $normalized], [
                'nilai' => ['array'],
                'nilai.*' => ['array'],
                'nilai.*.*' => ['nullable', 'integer', 'between:0,100'],
            ], [
                'nilai.*.*.integer' => 'Nilai harus berupa bilangan bulat.',
                'nilai.*.*.between' => 'Nilai harus berada antara 0 dan 100.',
            ])->validate();

            if ($assessment === null) {
                $assessment = AsesmenFormatif::query()->create([
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
                    'dicatat_pada' => now(),
                ]);
            } else {
                $assessment->increment('revisi');
            }

            $studentIds = $students->pluck('id')->all();
            $assessment->nilais()->whereIn('murid_id', $studentIds)->delete();
            $now = now();
            $records = [];

            foreach ($students as $student) {
                foreach ($materials as $material) {
                    foreach ($material->tujuanPembelajarans as $purpose) {
                        $score = $data['nilai'][$student->id][$purpose->id] ?? null;

                        if ($score === null) {
                            continue;
                        }

                        $records[] = [
                            'asesmen_formatif_id' => $assessment->id,
                            'murid_id' => $student->id,
                            'lingkup_materi_id' => $material->id,
                            'tujuan_pembelajaran_id' => $purpose->id,
                            'nama_murid' => $student->nama,
                            'nis' => $student->nis,
                            'jenis_kelamin' => $student->jenis_kelamin,
                            'lingkup_materi' => $material->materi,
                            'tujuan_pembelajaran' => $purpose->teks,
                            'nilai' => $score,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            if ($records !== []) {
                AsesmenFormatifNilai::query()->insert($records);
            }
        }, attempts: 3);

        $this->loadNilai();
        Notification::make()->success()->title('Nilai formatif tersimpan')
            ->body('Nilai tersimpan untuk kelas dan mata pelajaran ini.')->send();
        $this->redirect(InputAsesmenFormatif::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $class = Kelas::query()->find($this->kelasId);
        $subject = MataPelajaran::query()->find($this->mataPelajaranId);
        $students = $this->students();
        $materials = $this->materials();
        $period = JadwalPelajaran::activePeriod();
        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'class' => $class,
            'subject' => $subject,
            'students' => $students,
            'materials' => $materials,
            'summaries' => $this->summaries($students, $materials),
            'maleCount' => $male,
            'femaleCount' => $female,
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'backUrl' => InputAsesmenFormatif::getUrl(),
            'lingkupUrl' => IsiLingkupMateri::getUrl(['mapel' => $this->mataPelajaranId]),
        ];
    }

    /** @return Collection<int, Murid> */
    private function students(): Collection
    {
        return Murid::query()->where('kelas_id', $this->kelasId)
            ->orderBy('nama')->orderBy('id')->get();
    }

    /** @return Collection<int, LingkupMateri> */
    private function materials(bool $lock = false): Collection
    {
        $period = JadwalPelajaran::activePeriod();

        return LingkupMateri::query()
            ->where('guru_id', Filament::auth()->user()?->guru()->value('id'))
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereHas('tujuanPembelajarans')
            ->with('tujuanPembelajarans')
            ->orderBy('urutan')
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get();
    }

    private function scheduleQuery(): Builder
    {
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->where('guru_id', Filament::auth()->user()?->guru()->value('id'))
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran', $this->mataPelajaranId)
            ->with(['kelas', 'mataPelajaran', 'guru.user']);
    }

    private function assessmentFor(bool $lock = false): ?AsesmenFormatif
    {
        $period = JadwalPelajaran::activePeriod();

        return AsesmenFormatif::query()
            ->where('guru_id', Filament::auth()->user()?->guru()->value('id'))
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

    private function loadNilai(): void
    {
        $students = $this->students();
        $materials = $this->materials();
        $assessment = $this->assessmentFor();
        abort_if($assessment !== null && $assessment->pencatat_id !== Filament::auth()->id(), 403);
        $this->asesmenId = $assessment?->id;
        $this->revisi = $assessment?->revisi;
        $nilai = [];

        foreach ($students as $student) {
            foreach ($materials as $material) {
                foreach ($material->tujuanPembelajarans as $purpose) {
                    $nilai[$student->id][$purpose->id] = null;
                }
            }
        }

        foreach ($assessment?->nilais ?? [] as $score) {
            if (array_key_exists($score->tujuan_pembelajaran_id, $nilai[$score->murid_id] ?? [])) {
                $nilai[$score->murid_id][$score->tujuan_pembelajaran_id] = $score->nilai;
            }
        }

        $this->nilai = $nilai;
    }

    /** @return array<int, array<int, int|string|null>> */
    private function normalizedScores(Collection $students, Collection $materials): array
    {
        $normalized = [];

        foreach ($students as $student) {
            foreach ($materials as $material) {
                foreach ($material->tujuanPembelajarans as $purpose) {
                    $value = $this->nilai[$student->id][$purpose->id] ?? null;

                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    if ($value === '') {
                        $value = null;
                    } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                        $value = (int) $value;
                    }

                    $normalized[$student->id][$purpose->id] = $value;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{material_averages: array<int, float|null>, average: float|null, rank: int|null, status: string}>
     */
    private function summaries(Collection $students, Collection $materials): array
    {
        $summaries = [];
        $ranked = [];

        foreach ($students as $student) {
            $allScores = [];
            $materialAverages = [];

            foreach ($materials as $material) {
                $scores = [];

                foreach ($material->tujuanPembelajarans as $purpose) {
                    $value = $this->nilai[$student->id][$purpose->id] ?? null;

                    if ($value !== null && $value !== '' && is_numeric($value)) {
                        $scores[] = (int) $value;
                        $allScores[] = (int) $value;
                    }
                }

                $materialAverages[$material->id] = $scores === [] ? null : round(array_sum($scores) / count($scores), 2);
            }

            $average = $allScores === [] ? null : round(array_sum($allScores) / count($allScores), 2);
            $summaries[$student->id] = [
                'material_averages' => $materialAverages,
                'average' => $average,
                'rank' => null,
                'status' => $average !== null && $average >= self::KKM ? 'Tercapai' : 'Belum',
            ];

            if ($average !== null) {
                $ranked[$student->id] = $average;
            }
        }

        arsort($ranked);
        $rank = 0;
        $position = 0;
        $previous = null;

        foreach ($ranked as $studentId => $average) {
            $position++;

            if ($average !== $previous) {
                $rank = $position;
                $previous = $average;
            }

            $summaries[$studentId]['rank'] = $rank;
        }

        return $summaries;
    }
}
