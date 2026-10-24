<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
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

class IsiNilaiUjian extends Page
{
    private const MAX_KOMPONEN = 10;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $title = 'Nilai Ujian';

    protected string $view = 'filament.pages.isi-nilai-ujian';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    #[Locked]
    public ?int $nilaiUjianId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<int, array{nama: string}> */
    public array $komponens = [];

    /** @var array<int, array<int, int|string|null>> */
    public array $nilai = [];

    /** @var array<int, array<int, string|null>> */
    public array $catatan = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/isi-nilai-ujian/{kelas}/{mapel}';
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

    public function tambahKomponen(): void
    {
        $this->assertTeachesClassSubject();

        if (count($this->komponens) >= self::MAX_KOMPONEN) {
            return;
        }

        $index = count($this->komponens);
        $this->komponens[$index] = ['nama' => ''];

        foreach ($this->students() as $student) {
            $this->nilai[$index][$student->id] = null;
            $this->catatan[$index][$student->id] = null;
        }

        $this->resetValidation();
    }

    public function hapusKomponen(int $index): void
    {
        $this->assertTeachesClassSubject();
        unset($this->komponens[$index], $this->nilai[$index], $this->catatan[$index]);
        $this->komponens = array_values($this->komponens);
        $this->nilai = array_values($this->nilai);
        $this->catatan = array_values($this->catatan);

        if ($this->komponens === []) {
            $students = $this->students();
            $this->komponens = [['nama' => '']];

            foreach ($students as $student) {
                $this->nilai[0][$student->id] = null;
                $this->catatan[0][$student->id] = null;
            }
        }

        $this->resetValidation();
    }

    public function simpan(): void
    {
        $this->assertTeachesClassSubject();

        DB::transaction(function (): void {
            $schedule = $this->scheduleQuery()->lockForUpdate()->firstOrFail();
            $students = Murid::query()->where('kelas_id', $this->kelasId)
                ->orderBy('nama')->orderBy('id')->lockForUpdate()->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['komponens' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas.']);
            }

            $exam = $this->examFor(lock: true);
            abort_if($exam !== null && $exam->pencatat_id !== Filament::auth()->id(), 403);

            if ($exam?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['komponens' => 'Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.']);
            }

            $components = Validator::make(['komponens' => $this->normalizedKomponens()], [
                'komponens' => ['required', 'array', 'min:1', 'max:'.self::MAX_KOMPONEN],
                'komponens.*.nama' => ['required', 'string', 'max:100'],
            ], [
                'komponens.required' => 'Tambahkan minimal satu ujian.',
                'komponens.min' => 'Tambahkan minimal satu ujian.',
                'komponens.max' => 'Nilai ujian maksimal '.self::MAX_KOMPONEN.' komponen dalam sekali simpan.',
                'komponens.*.nama.required' => 'Nama ujian wajib diisi.',
                'komponens.*.nama.max' => 'Nama ujian maksimal 100 karakter.',
            ])->validate();

            $scores = Validator::make([
                'nilai' => $this->normalizedMatrix($students, $this->nilai, numeric: true),
                'catatan' => $this->normalizedMatrix($students, $this->catatan, numeric: false),
            ], [
                'nilai' => ['array'],
                'nilai.*' => ['array'],
                'nilai.*.*' => ['nullable', 'integer', 'between:0,100'],
                'catatan' => ['array'],
                'catatan.*' => ['array'],
                'catatan.*.*' => ['nullable', 'string', 'max:500'],
            ], [
                'nilai.*.*.integer' => 'Nilai harus berupa bilangan bulat.',
                'nilai.*.*.between' => 'Nilai harus berada antara 0 dan 100.',
                'catatan.*.*.string' => 'Catatan harus berupa teks.',
                'catatan.*.*.max' => 'Catatan maksimal 500 karakter.',
            ])->validate();

            if ($exam === null) {
                $exam = NilaiUjian::query()->create([
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
                $exam->increment('revisi');
            }

            $exam->komponens()->delete();
            $now = now();

            foreach ($components['komponens'] as $index => $component) {
                $komponen = $exam->komponens()->create([
                    'nama' => $component['nama'],
                    'urutan' => $index + 1,
                ]);

                $records = [];

                foreach ($students as $student) {
                    $score = $scores['nilai'][$index][$student->id] ?? null;
                    $note = $scores['catatan'][$index][$student->id] ?? null;

                    if ($score === null && $note === null) {
                        continue;
                    }

                    $records[] = [
                        'nilai_ujian_komponen_id' => $komponen->id,
                        'murid_id' => $student->id,
                        'nama_murid' => $student->nama,
                        'nis' => $student->nis,
                        'nilai' => $score,
                        'catatan' => $note,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($records !== []) {
                    $komponen->nilais()->insert($records);
                }
            }
        }, attempts: 3);

        $this->loadNilai();
        Notification::make()->success()->title('Nilai ujian tersimpan')
            ->body('Nilai dan catatan ujian tersimpan untuk kelas dan mata pelajaran ini.')->send();
        $this->redirect(InputNilaiUjian::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $class = Kelas::query()->find($this->kelasId);
        $subject = MataPelajaran::query()->find($this->mataPelajaranId);
        $students = $this->students();
        $period = JadwalPelajaran::activePeriod();
        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'class' => $class,
            'subject' => $subject,
            'students' => $students,
            'averages' => $this->averages($students),
            'maleCount' => $male,
            'femaleCount' => $female,
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'componentCount' => count($this->komponens),
            'maxKomponen' => self::MAX_KOMPONEN,
            'backUrl' => InputNilaiUjian::getUrl(),
        ];
    }

    /** @return Collection<int, Murid> */
    private function students(): Collection
    {
        return Murid::query()->where('kelas_id', $this->kelasId)
            ->orderBy('nama')->orderBy('id')->get();
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

    private function examFor(bool $lock = false): ?NilaiUjian
    {
        $period = JadwalPelajaran::activePeriod();

        return NilaiUjian::query()
            ->where('guru_id', Filament::auth()->user()?->guru()->value('id'))
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with('komponens.nilais')
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
        $exam = $this->examFor();
        abort_if($exam !== null && $exam->pencatat_id !== Filament::auth()->id(), 403);
        $this->nilaiUjianId = $exam?->id;
        $this->revisi = $exam?->revisi;
        $this->komponens = [];
        $this->nilai = [];
        $this->catatan = [];

        foreach ($exam?->komponens ?? [] as $komponen) {
            $index = count($this->komponens);
            $this->komponens[$index] = ['nama' => $komponen->nama];

            foreach ($students as $student) {
                $this->nilai[$index][$student->id] = null;
                $this->catatan[$index][$student->id] = null;
            }

            foreach ($komponen->nilais as $row) {
                if (array_key_exists($row->murid_id, $this->nilai[$index] ?? [])) {
                    $this->nilai[$index][$row->murid_id] = $row->nilai;
                    $this->catatan[$index][$row->murid_id] = $row->catatan;
                }
            }
        }

        if ($this->komponens === []) {
            $this->komponens = [['nama' => '']];

            foreach ($students as $student) {
                $this->nilai[0][$student->id] = null;
                $this->catatan[0][$student->id] = null;
            }
        }
    }

    /** @return array<int, array{nama: mixed}> */
    private function normalizedKomponens(): array
    {
        $normalized = [];

        foreach ($this->komponens as $komponen) {
            $name = is_array($komponen) ? ($komponen['nama'] ?? null) : null;
            $normalized[] = ['nama' => is_string($name) ? trim($name) : $name];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array<int, array<int, int|string|null>>
     */
    private function normalizedMatrix(Collection $students, array $matrix, bool $numeric): array
    {
        $normalized = [];

        foreach (array_keys($this->komponens) as $index) {
            foreach ($students as $student) {
                $value = $matrix[$index][$student->id] ?? null;

                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value === '') {
                    $value = null;
                } elseif ($numeric && is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    $value = (int) $value;
                }

                $normalized[$index][$student->id] = $value;
            }
        }

        return $normalized;
    }

    /** @return array<int, float|null> */
    private function averages(Collection $students): array
    {
        $averages = [];

        foreach ($students as $student) {
            $scores = [];

            foreach (array_keys($this->komponens) as $index) {
                $value = $this->nilai[$index][$student->id] ?? null;

                if ($value !== null && $value !== '' && is_numeric($value)) {
                    $scores[] = (int) $value;
                }
            }

            $averages[$student->id] = $scores === [] ? null : round(array_sum($scores) / count($scores), 2);
        }

        return $averages;
    }
}
