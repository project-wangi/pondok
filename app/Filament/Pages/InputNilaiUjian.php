<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Murid;
use App\Models\NilaiUjian;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

class InputNilaiUjian extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Nilai Ujian';

    protected static ?string $title = 'Nilai Ujian';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'nilai-ujian';

    protected string $view = 'filament.pages.input-nilai-ujian';

    #[Locked]
    public ?int $kelasId = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && $user->can('View:InputNilaiUjian')
            && ($user->isAdministrator() || $user->guru()->exists());
    }

    public function pilihKelas(int $kelasId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->kelasIds()->contains($kelasId), 403);

        $this->kelasId = $kelasId;
    }

    public function kembaliKeDaftarKelas(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->kelasId = null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $user = Filament::auth()->user();
        $isAdmin = (bool) $user?->isAdministrator();
        $period = JadwalPelajaran::activePeriod();
        $schedules = $this->schedules();
        $classIds = $schedules->pluck('kelas_id')->unique()->values();
        $studentCounts = Murid::query()
            ->aktif()
            ->whereIn('kelas_id', $classIds)
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');
        $exams = NilaiUjian::query()
            ->when(! $isAdmin, fn (Builder $query): Builder => $query->where('pencatat_id', $user?->id))
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount([
                'komponens',
                'nilais as filled_nilais_count' => fn (Builder $query): Builder => $query->whereNotNull('nilai_ujian_nilais.nilai'),
            ])
            ->get()
            ->keyBy(fn (NilaiUjian $exam): string => $exam->kelas_id.'-'.$exam->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($studentCounts, $exams): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $studentCount = (int) ($studentCounts->get($schedule->kelas_id) ?? 0);
            $exam = $exams->get($schedule->kelas_id.'-'.$subjectId);
            $componentCount = (int) ($exam?->komponens_count ?? 0);
            $possible = $studentCount * $componentCount;

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'student_count' => $studentCount,
                'component_count' => $componentCount,
                'filled_count' => min((int) ($exam?->filled_nilais_count ?? 0), $possible),
                'possible_count' => $possible,
                'url' => IsiNilaiUjian::getUrl(['kelas' => $schedule->kelas_id, 'mapel' => $subjectId]),
            ];
        });

        $kelasList = $rows
            ->groupBy(fn (array $row): string => (string) ($row['kelas']?->id ?? '0'))
            ->map(fn (Collection $group): array => [
                'kelas' => $group->first()['kelas'],
                'jumlah_mapel' => $group->count(),
                'jumlah_siswa' => (int) ($group->first()['student_count'] ?? 0),
            ])
            ->values();

        $view = [
            'langkah' => 'kelas',
            'kelasList' => $kelasList,
            'rows' => $rows,
            'period' => $period,
            'isAdmin' => $isAdmin,
        ];

        if ($this->kelasId === null) {
            return $view;
        }

        $kelas = Kelas::query()->find($this->kelasId);
        abort_if($kelas === null, 404);
        abort_unless($this->kelasIds()->contains($kelas->id), 403);

        $view['langkah'] = 'mapel';
        $view['kelas'] = $kelas;
        $view['rows'] = $rows
            ->filter(fn (array $row): bool => $row['kelas']?->id === $kelas->id)
            ->values();

        return $view;
    }

    /** @return Collection<int, JadwalPelajaran> */
    private function schedules(): Collection
    {
        $user = Filament::auth()->user();
        $guruId = $user?->guru()->value('id');
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->when(! $user?->isAdministrator(), fn (Builder $query): Builder => $query->where('guru_id', $guruId))
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->with(['kelas', 'mataPelajaran'])
            ->get()
            ->unique(fn (JadwalPelajaran $schedule): string => $schedule->kelas_id.'-'.$schedule->mata_pelajaran)
            ->sortBy(fn (JadwalPelajaran $schedule): string => ($schedule->kelas?->nama_kelas ?? '').' '.($schedule->mataPelajaran?->nama_mapel ?? ''))
            ->values();
    }

    /** @return Collection<int, int> */
    private function kelasIds(): Collection
    {
        return $this->schedules()
            ->pluck('kelas_id')
            ->filter()
            ->map(fn (mixed $kelasId): int => (int) $kelasId)
            ->unique()
            ->values();
    }
}
