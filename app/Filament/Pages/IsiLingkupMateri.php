<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use UnitEnum;

class IsiLingkupMateri extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $title = 'Isi Lingkup Materi dan Tujuan Pembelajaran';

    protected string $view = 'filament.pages.isi-lingkup-materi';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    /** @var array<int, array{materi: string, tujuan: array<int, string>}> */
    public array $lingkup = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/isi-lingkup-materi/{kelas}/{mapel}';
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
        $this->loadLingkup();
    }

    public function tambahLingkup(): void
    {
        $this->assertTeachesClassSubject();
        $this->lingkup[] = $this->emptyRow();
    }

    public function hapusLingkup(int $index): void
    {
        $this->assertTeachesClassSubject();
        unset($this->lingkup[$index]);
        $this->lingkup = array_values($this->lingkup);

        if ($this->lingkup === []) {
            $this->lingkup = [$this->emptyRow()];
        }

        $this->resetValidation();
    }

    public function tambahTujuan(int $index): void
    {
        $this->assertTeachesClassSubject();

        if (! isset($this->lingkup[$index]) || ! is_array($this->lingkup[$index]['tujuan'] ?? null)) {
            return;
        }

        $this->lingkup[$index]['tujuan'][] = '';
        $this->resetValidation();
    }

    public function hapusTujuan(int $index, int $goalIndex): void
    {
        $this->assertTeachesClassSubject();

        if (! isset($this->lingkup[$index]) || ! is_array($this->lingkup[$index]['tujuan'] ?? null)) {
            return;
        }

        $purposes = array_values($this->lingkup[$index]['tujuan']);

        if (count($purposes) <= 1) {
            $purposes = [''];
        } else {
            unset($purposes[$goalIndex]);
            $purposes = array_values($purposes);
        }

        $this->lingkup[$index]['tujuan'] = $purposes;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $this->assertTeachesClassSubject();
        $guru = Filament::auth()->user()->guru()->firstOrFail();
        $subject = $this->selectedSubject();
        $class = $this->selectedClass();
        $period = JadwalPelajaran::activePeriod();

        $this->lingkup = array_values(array_map(function (mixed $row): array {
            if (! is_array($row)) {
                return ['materi' => '', 'tujuan' => []];
            }

            return [
                'materi' => is_scalar($row['materi'] ?? null) ? trim((string) $row['materi']) : $row['materi'],
                'tujuan' => is_array($row['tujuan'] ?? null) ? array_map(
                    fn (mixed $tujuan): mixed => is_scalar($tujuan) ? trim((string) $tujuan) : $tujuan,
                    array_values($row['tujuan']),
                ) : ($row['tujuan'] ?? null),
            ];
        }, $this->lingkup));

        $validator = Validator::make(['lingkup' => $this->lingkup], [
            'lingkup' => ['required', 'array', 'min:1', 'max:50'],
            'lingkup.*.materi' => ['required', 'string', 'max:500'],
            'lingkup.*.tujuan' => ['required', 'array'],
            'lingkup.*.tujuan.*' => ['nullable', 'string', 'max:100'],
        ], [
            'lingkup.required' => 'Tambahkan minimal satu lingkup materi.',
            'lingkup.max' => 'Lingkup materi maksimal 50 baris dalam sekali simpan.',
            'lingkup.*.materi.required' => 'Lingkup materi wajib diisi.',
            'lingkup.*.materi.max' => 'Lingkup materi maksimal 500 karakter.',
            'lingkup.*.tujuan.*.max' => 'Tujuan pembelajaran maksimal 100 karakter.',
        ]);

        $validator->after(function ($validator): void {
            foreach ($this->lingkup as $index => $row) {
                $purposes = array_filter(is_array($row['tujuan']) ? $row['tujuan'] : [], filled(...));

                if ($purposes === []) {
                    $validator->errors()->add("lingkup.$index.tujuan", 'Isi minimal satu tujuan pembelajaran untuk setiap lingkup materi.');

                    continue;
                }

                foreach ($row['tujuan'] as $purposeIndex => $purpose) {
                    if (! is_string($purpose) || $purpose === '' || mb_strlen($purpose) > 100) {
                        continue;
                    }

                    if (preg_match('/^[a-z]/u', $purpose) !== 1) {
                        $validator->errors()->add("lingkup.$index.tujuan.$purposeIndex", 'Tujuan pembelajaran harus diawali dengan huruf kecil.');
                    }

                    if (str_ends_with($purpose, '.')) {
                        $validator->errors()->add("lingkup.$index.tujuan.$purposeIndex", 'Tujuan pembelajaran tidak boleh diakhiri tanda titik.');
                    }
                }
            }
        });

        $data = $validator->validate();

        DB::transaction(function () use ($guru, $subject, $class, $period, $data): void {
            LingkupMateri::query()
                ->where('guru_id', $guru->id)
                ->where('kelas_id', $class->id)
                ->where('mata_pelajaran_id', $subject->id)
                ->where('semester', $period['semester'])
                ->where('tahun_ajaran', $period['tahun_ajaran'])
                ->lockForUpdate()
                ->get()
                ->each->delete();

            foreach ($data['lingkup'] as $index => $row) {
                $material = LingkupMateri::query()->create([
                    'guru_id' => $guru->id,
                    'kelas_id' => $class->id,
                    'mata_pelajaran_id' => $subject->id,
                    'semester' => $period['semester'],
                    'tahun_ajaran' => $period['tahun_ajaran'],
                    'urutan' => $index + 1,
                    'materi' => $row['materi'],
                ]);

                $purposeNumber = 0;

                foreach ($row['tujuan'] as $purpose) {
                    if (! filled($purpose)) {
                        continue;
                    }

                    $material->tujuanPembelajarans()->create([
                        'urutan' => ++$purposeNumber,
                        'teks' => $purpose,
                    ]);
                }
            }
        }, attempts: 3);

        $this->loadLingkup();
        Notification::make()->success()->title('Lingkup materi tersimpan')
            ->body('Data tersimpan untuk '.$subject->nama_mapel.' kelas '.$class->nama_kelas.' pada periode berjalan.')->send();
        $this->redirect(InputLingkupMateri::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'guru' => Filament::auth()->user()?->guru()->with('user')->first(),
            'class' => $this->selectedClass(),
            'subject' => $this->selectedSubject(),
            'period' => JadwalPelajaran::activePeriod(),
            'maxKarakterTujuan' => 100,
            'overviewUrl' => InputLingkupMateri::getUrl(),
        ];
    }

    private function selectedSubject(): MataPelajaran
    {
        $subject = MataPelajaran::query()->find($this->mataPelajaranId);
        abort_if($subject === null, 404);

        return $subject;
    }

    private function selectedClass(): Kelas
    {
        $class = Kelas::query()->find($this->kelasId);
        abort_if($class === null, 404);

        return $class;
    }

    private function assertTeachesClassSubject(): void
    {
        abort_unless(static::canAccess(), 403);
        $guruId = Filament::auth()->user()?->guru()->value('id');
        $period = JadwalPelajaran::activePeriod();
        $teaches = JadwalPelajaran::query()
            ->where('guru_id', $guruId)
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran', $this->mataPelajaranId)
            ->exists();
        abort_if(! $teaches, 404);
    }

    private function loadLingkup(): void
    {
        $guruId = Filament::auth()->user()?->guru()->value('id');
        $period = JadwalPelajaran::activePeriod();
        $saved = LingkupMateri::query()
            ->where('guru_id', $guruId)
            ->where('kelas_id', $this->kelasId)
            ->where('mata_pelajaran_id', $this->mataPelajaranId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->with('tujuanPembelajarans')
            ->orderBy('urutan')
            ->get();

        $this->lingkup = $saved->map(function (LingkupMateri $material): array {
            $purposes = array_values($material->tujuanPembelajarans->pluck('teks')->all());

            return [
                'materi' => $material->materi,
                'tujuan' => $purposes === [] ? [''] : $purposes,
            ];
        })->values()->all();

        if ($this->lingkup === []) {
            $this->lingkup = [$this->emptyRow()];
        }
    }

    /** @return array{materi: string, tujuan: array<int, string>} */
    private function emptyRow(): array
    {
        return ['materi' => '', 'tujuan' => ['', '', '', '']];
    }
}
