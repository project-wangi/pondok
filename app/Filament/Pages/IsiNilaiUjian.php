<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use App\Models\NilaiUjianKomponen;
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

    private const BATAS_EDIT_JAM = 24;

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

    /** @var array<int, int|null> */
    #[Locked]
    public array $komponenIds = [];

    /** @var array<int, bool> */
    #[Locked]
    public array $komponenTerkunci = [];

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
        $user = Filament::auth()->user();

        return $user !== null
            && $user->can('View:InputNilaiUjian')
            && ($user->isAdministrator() || $user->guru()->exists());
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
        $this->komponenIds[$index] = null;
        $this->komponenTerkunci[$index] = false;

        foreach ($this->students() as $student) {
            $this->nilai[$index][$student->id] = null;
            $this->catatan[$index][$student->id] = null;
        }

        $this->resetValidation();
    }

    public function hapusKomponen(int $index): void
    {
        $this->assertTeachesClassSubject();

        if ($this->komponenTerkunci[$index] ?? false) {
            throw ValidationException::withMessages([
                'komponens' => $this->namaKomponen($index).' sudah terkunci karena melewati '.self::BATAS_EDIT_JAM.' jam sehingga tidak dapat dihapus.',
            ]);
        }

        unset($this->komponens[$index], $this->komponenIds[$index], $this->komponenTerkunci[$index], $this->nilai[$index], $this->catatan[$index]);
        $this->komponens = array_values($this->komponens);
        $this->komponenIds = array_values($this->komponenIds);
        $this->komponenTerkunci = array_values($this->komponenTerkunci);
        $this->nilai = array_values($this->nilai);
        $this->catatan = array_values($this->catatan);

        if ($this->komponens === []) {
            $students = $this->students();
            $this->komponens = [['nama' => '']];
            $this->komponenIds = [null];
            $this->komponenTerkunci = [false];

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
            $students = Murid::query()->aktif()->where('kelas_id', $this->kelasId)
                ->orderBy('nama')->orderBy('id')->lockForUpdate()->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['komponens' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas.']);
            }

            $exam = $this->examFor(lock: true);
            abort_if(! $this->isAdmin() && $exam !== null && $exam->pencatat_id !== Filament::auth()->id(), 403);

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
                    'pencatat_id' => $this->isAdmin()
                        ? $schedule->guru->user_id
                        : Filament::auth()->id(),
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

            $this->simpanKomponen($exam, $students, $components['komponens'], $scores['nilai'], $scores['catatan']);
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
            'guruName' => $this->namaGuruPenilai(),
            'period' => $period,
            'componentCount' => count($this->komponens),
            'maxKomponen' => self::MAX_KOMPONEN,
            'batasEdit' => $this->batasEdit(),
            'backUrl' => InputNilaiUjian::getUrl(),
        ];
    }

    /** @return Collection<int, Murid> */
    private function students(): Collection
    {
        return Murid::query()->aktif()->where('kelas_id', $this->kelasId)
            ->orderBy('nama')->orderBy('id')->get();
    }

    private function scheduleQuery(): Builder
    {
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->when(! $this->isAdmin(), fn (Builder $query): Builder => $query->where(
                'guru_id', Filament::auth()->user()?->guru()->value('id'),
            ))
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran', $this->mataPelajaranId)
            ->with(['kelas', 'mataPelajaran', 'guru.user']);
    }

    private function guruUjianId(): ?int
    {
        if (! $this->isAdmin()) {
            return Filament::auth()->user()?->guru()->value('id');
        }

        $guruId = $this->scheduleQuery()->value('guru_id');

        return $guruId === null ? null : (int) $guruId;
    }

    private function examFor(bool $lock = false): ?NilaiUjian
    {
        $period = JadwalPelajaran::activePeriod();

        return NilaiUjian::query()
            ->where('guru_id', $this->guruUjianId())
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
        abort_if(! $this->isAdmin() && $exam !== null && $exam->pencatat_id !== Filament::auth()->id(), 403);
        $this->nilaiUjianId = $exam?->id;
        $this->revisi = $exam?->revisi;
        $this->komponens = [];
        $this->komponenIds = [];
        $this->komponenTerkunci = [];
        $this->nilai = [];
        $this->catatan = [];

        foreach ($exam?->komponens ?? [] as $komponen) {
            $index = count($this->komponens);
            $this->komponens[$index] = ['nama' => $komponen->nama];
            $this->komponenIds[$index] = $komponen->id;
            $this->komponenTerkunci[$index] = $this->sudahTerkunci($komponen);

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
            $this->komponenIds = [null];
            $this->komponenTerkunci = [false];

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

    /**
     * Simpan komponen sesuai batas 24 jam: komponen terkunci tidak disentuh,
     * komponen baru tetap boleh ditambahkan.
     *
     * @param  array<int, array{nama: string}>  $komponens
     * @param  array<int, array<int, int|null>>  $nilai
     * @param  array<int, array<int, string|null>>  $catatan
     */
    private function simpanKomponen(
        NilaiUjian $exam,
        Collection $students,
        array $komponens,
        array $nilai,
        array $catatan,
    ): void {
        $existing = $exam->komponens()->with('nilais')->get()->keyBy('id');
        $ids = [];

        foreach (array_keys($komponens) as $index) {
            $id = $this->komponenIds[$index] ?? null;
            abort_unless($id === null || $existing->has((int) $id), 403);
            $ids[$index] = $id === null ? null : (int) $id;
        }

        $submitted = array_filter($ids);

        // Komponen terkunci tidak boleh hilang dari kiriman halaman.
        foreach ($existing as $komponen) {
            if ($this->sudahTerkunci($komponen) && ! in_array($komponen->id, $submitted, true)) {
                throw ValidationException::withMessages([
                    'komponens' => $komponen->nama.' sudah terkunci karena melewati '.self::BATAS_EDIT_JAM.' jam sehingga tidak dapat dihapus.',
                ]);
            }
        }

        // Hapus komponen yang dilepas pada halaman, lalu rapikan urutannya.
        foreach ($existing as $komponen) {
            if (! in_array($komponen->id, $submitted, true)) {
                $komponen->delete();
            }
        }

        $now = now();

        foreach ($komponens as $index => $component) {
            $record = $ids[$index] === null ? null : $existing->get($ids[$index]);

            if ($record !== null && $this->sudahTerkunci($record)) {
                $this->pastikanKomponenTerkunciTetap($record, $index, $component['nama'], $students, $nilai, $catatan);
                $record->update(['urutan' => $index + 1]);

                continue;
            }

            if ($record === null) {
                $record = $exam->komponens()->create([
                    'nama' => $component['nama'],
                    'urutan' => $index + 1,
                    'dicatat_pada' => $now,
                ]);
            } else {
                $record->update(['nama' => $component['nama'], 'urutan' => $index + 1]);
                $record->nilais()->delete();
            }

            $records = [];

            foreach ($students as $student) {
                $score = $nilai[$index][$student->id] ?? null;
                $note = $catatan[$index][$student->id] ?? null;

                if ($score === null && $note === null) {
                    continue;
                }

                $records[] = [
                    'nilai_ujian_komponen_id' => $record->id,
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
                $record->nilais()->insert($records);
            }
        }
    }

    /**
     * @param  array<int, array<int, int|null>>  $nilai
     * @param  array<int, array<int, string|null>>  $catatan
     */
    private function pastikanKomponenTerkunciTetap(
        NilaiUjianKomponen $komponen,
        int $index,
        string $nama,
        Collection $students,
        array $nilai,
        array $catatan,
    ): void {
        $tersimpan = $komponen->nilais->keyBy('murid_id');
        $berubah = trim($nama) !== $komponen->nama;

        foreach ($students as $student) {
            if ($berubah) {
                break;
            }

            $row = $tersimpan->get($student->id);
            $score = $nilai[$index][$student->id] ?? null;
            $note = $catatan[$index][$student->id] ?? null;

            $berubah = $row === null
                ? $score !== null || $note !== null
                : $row->nilai !== $score || $row->catatan !== $note;
        }

        if ($berubah) {
            throw ValidationException::withMessages([
                'komponens' => $komponen->nama.' sudah terkunci karena melewati '.self::BATAS_EDIT_JAM.' jam. Nilai, catatan, dan namanya tidak dapat diubah. Tambahkan ujian baru bila perlu.',
            ]);
        }
    }

    private function sudahTerkunci(NilaiUjianKomponen $komponen): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $komponen->dicatat_pada !== null
            && $komponen->dicatat_pada->copy()->addHours(self::BATAS_EDIT_JAM)->lessThanOrEqualTo(now());
    }

    private function isAdmin(): bool
    {
        return (bool) Filament::auth()->user()?->isAdministrator();
    }

    /**
     * Nama guru pemilik jadwal, bukan nama admin yang sedang mengoreksi.
     */
    private function namaGuruPenilai(): ?string
    {
        if (! $this->isAdmin()) {
            return Filament::auth()->user()?->name;
        }

        return $this->scheduleQuery()->first()?->guru?->user?->name;
    }

    private function namaKomponen(int $index): string
    {
        $nama = trim((string) ($this->komponens[$index]['nama'] ?? ''));

        return $nama !== '' ? $nama : 'Ujian '.($index + 1);
    }

    /** @return array<int, string|null> */
    private function batasEdit(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        $exam = $this->examFor();
        $komponenById = $exam?->komponens->keyBy('id') ?? collect();
        $batas = [];

        foreach (array_keys($this->komponens) as $index) {
            $id = $this->komponenIds[$index] ?? null;
            $komponen = $id === null ? null : $komponenById->get($id);
            $terkunci = $this->komponenTerkunci[$index] ?? false;

            $batas[$index] = $komponen?->dicatat_pada === null || $terkunci
                ? null
                : $komponen->dicatat_pada->copy()
                    ->addHours(self::BATAS_EDIT_JAM)
                    ->locale('id')
                    ->translatedFormat('d F Y, H.i');
        }

        return $batas;
    }
}
