<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SikapKeaktifan;
use App\Models\SikapKeaktifanNilai;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class IsiSikapKeaktifan extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $title = 'Sikap dan Keaktifan';

    protected string $view = 'filament.pages.isi-sikap-keaktifan';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    #[Locked]
    public ?int $sikapId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<int, string|null> */
    public array $sikap = [];

    /** @var array<int, string|null> */
    public array $keaktifan = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/isi-sikap-keaktifan/{kelas}/{mapel}';
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

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['sikap' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas.']);
            }

            $entry = $this->entryFor(lock: true);
            abort_if($entry !== null && $entry->pencatat_id !== Filament::auth()->id(), 403);

            if ($entry?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['sikap' => 'Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.']);
            }

            $data = Validator::make($this->normalizedValues($students), [
                'sikap' => ['array'],
                'sikap.*' => ['nullable', Rule::in(array_keys(SikapKeaktifan::KATEGORI))],
                'keaktifan' => ['array'],
                'keaktifan.*' => ['nullable', Rule::in(array_keys(SikapKeaktifan::KATEGORI))],
            ], [
                'sikap.*.in' => 'Kategori sikap harus salah satu dari A, B, C, D, atau E.',
                'keaktifan.*.in' => 'Kategori keaktifan harus salah satu dari A, B, C, D, atau E.',
            ])->validate();

            if ($entry === null) {
                $entry = SikapKeaktifan::query()->create([
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
                $entry->increment('revisi');
            }

            $studentIds = $students->pluck('id')->all();
            $entry->nilais()->whereIn('murid_id', $studentIds)->delete();
            $now = now();
            $records = [];

            foreach ($students as $student) {
                $sikap = $data['sikap'][$student->id] ?? null;
                $keaktifan = $data['keaktifan'][$student->id] ?? null;

                if ($sikap === null && $keaktifan === null) {
                    continue;
                }

                $records[] = [
                    'sikap_keaktifan_id' => $entry->id,
                    'murid_id' => $student->id,
                    'nama_murid' => $student->nama,
                    'nis' => $student->nis,
                    'jenis_kelamin' => $student->jenis_kelamin,
                    'sikap' => $sikap,
                    'keaktifan' => $keaktifan,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($records !== []) {
                SikapKeaktifanNilai::query()->insert($records);
            }
        }, attempts: 3);

        $this->loadNilai();
        Notification::make()->success()->title('Nilai sikap dan keaktifan tersimpan')
            ->body('Nilai tersimpan untuk kelas dan mata pelajaran ini.')->send();
        $this->redirect(InputSikapKeaktifan::getUrl());
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
            'kategori' => SikapKeaktifan::KATEGORI,
            'labelSikap' => SikapKeaktifan::LABEL_SIKAP,
            'labelKeaktifan' => SikapKeaktifan::LABEL_KEAKTIFAN,
            'maleCount' => $male,
            'femaleCount' => $female,
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'backUrl' => InputSikapKeaktifan::getUrl(),
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

    private function entryFor(bool $lock = false): ?SikapKeaktifan
    {
        $period = JadwalPelajaran::activePeriod();

        return SikapKeaktifan::query()
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
        $entry = $this->entryFor();
        abort_if($entry !== null && $entry->pencatat_id !== Filament::auth()->id(), 403);
        $this->sikapId = $entry?->id;
        $this->revisi = $entry?->revisi;
        $sikap = [];
        $keaktifan = [];

        foreach ($students as $student) {
            $sikap[$student->id] = null;
            $keaktifan[$student->id] = null;
        }

        foreach ($entry?->nilais ?? [] as $score) {
            if (array_key_exists($score->murid_id, $sikap)) {
                $sikap[$score->murid_id] = $score->sikap;
                $keaktifan[$score->murid_id] = $score->keaktifan;
            }
        }

        $this->sikap = $sikap;
        $this->keaktifan = $keaktifan;
    }

    /**
     * @return array{sikap: array<int, string|null>, keaktifan: array<int, string|null>}
     */
    private function normalizedValues(Collection $students): array
    {
        $sikap = [];
        $keaktifan = [];

        foreach ($students as $student) {
            $sikap[$student->id] = $this->normalizeKategori($this->sikap[$student->id] ?? null);
            $keaktifan[$student->id] = $this->normalizeKategori($this->keaktifan[$student->id] ?? null);
        }

        return ['sikap' => $sikap, 'keaktifan' => $keaktifan];
    }

    private function normalizeKategori(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtoupper(trim($value));

        return $value === '' ? null : $value;
    }
}
