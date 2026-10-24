<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SesiMengajar;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

class RekapAbsensi extends Page
{
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** @var array<int, string> */
    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan Kehadiran';

    protected static ?string $navigationLabel = 'Rekap Absensi';

    protected static ?string $title = 'Rekap Absensi';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'rekap-absensi';

    protected string $view = 'filament.pages.rekap-absensi';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $mataPelajaranId = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $first = $this->scheduleOptions()
            ->first(fn (JadwalPelajaran $schedule): bool => $schedule->kelas_id !== null && $schedule->mata_pelajaran !== null);

        if ($first !== null) {
            $this->kelasId = (int) $first->kelas_id;
            $this->mataPelajaranId = (int) $first->mata_pelajaran;
        }
    }

    public function pilihKelas(int $kelasId): void
    {
        abort_unless(static::canAccess(), 403);
        $subjects = $this->subjectOptions($kelasId);
        abort_if($subjects->isEmpty(), 404);
        $this->kelasId = $kelasId;

        if (! $subjects->contains('id', $this->mataPelajaranId)) {
            $this->mataPelajaranId = (int) $subjects->first()->id;
        }
    }

    public function pilihMapel(int $mapelId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($this->kelasId === null, 404);
        abort_unless($this->subjectOptions($this->kelasId)->contains('id', $mapelId), 404);
        $this->mataPelajaranId = $mapelId;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $period = JadwalPelajaran::activePeriod();
        $classes = $this->classOptions();
        $subjects = $this->kelasId !== null ? $this->subjectOptions($this->kelasId) : new Collection;
        $class = $this->kelasId !== null ? Kelas::query()->find($this->kelasId) : null;
        $subject = $this->mataPelajaranId !== null ? MataPelajaran::query()->find($this->mataPelajaranId) : null;

        if ($class !== null && $subject !== null) {
            abort_unless($subjects->contains('id', $subject->id), 404);
        }

        $students = $class !== null
            ? Murid::query()->where('kelas_id', $class->id)->orderBy('nama')->orderBy('id')->get()
            : new Collection;
        $sessions = $class !== null && $subject !== null
            ? $this->sessions($class->id, $subject->id, $period)
            : new Collection;
        $statuses = [];

        foreach ($sessions as $session) {
            foreach ($session->presensiMurids as $attendance) {
                if ($attendance->murid_id !== null) {
                    $statuses[$attendance->murid_id][$session->id] = $attendance->status;
                }
            }
        }

        $columns = [];

        foreach ($sessions as $index => $session) {
            $columns[] = [
                'id' => $session->id,
                'nomor' => $index + 1,
                'label' => $this->sessionLabel($session),
                'tanggal' => $session->tanggal,
            ];
        }

        $rows = [];

        foreach ($students as $student) {
            $counts = ['S' => 0, 'I' => 0, 'A' => 0, 'H' => 0];
            $studentStatuses = [];

            foreach ($sessions as $session) {
                $code = $this->statusCode($statuses[$student->id][$session->id] ?? null);
                $studentStatuses[$session->id] = $code;

                if ($code !== null) {
                    $counts[$code]++;
                }
            }

            $recorded = array_sum($counts);

            $rows[] = [
                'student' => $student,
                'statuses' => $studentStatuses,
                'counts' => $counts,
                'percent' => $recorded > 0 ? (int) round(($counts['H'] / $recorded) * 100) : null,
            ];
        }

        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'classes' => $classes,
            'subjects' => $subjects,
            'class' => $class,
            'subject' => $subject,
            'students' => $students,
            'columns' => $columns,
            'rows' => $rows,
            'maleCount' => $male,
            'femaleCount' => $female,
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'presensiUrl' => PresensiSiswa::getUrl(),
        ];
    }

    private function guruId(): ?int
    {
        return Filament::auth()->user()?->guru()->value('id');
    }

    /** @return Collection<int, JadwalPelajaran> */
    private function scheduleOptions(): Collection
    {
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->where('guru_id', $this->guruId())
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->with(['kelas', 'mataPelajaran'])
            ->get()
            ->sortBy(fn (JadwalPelajaran $schedule): string => ($schedule->kelas?->nama_kelas ?? '').' '.($schedule->mataPelajaran?->nama_mapel ?? ''))
            ->values();
    }

    /** @return Collection<int, Kelas> */
    private function classOptions(): Collection
    {
        return $this->scheduleOptions()
            ->unique('kelas_id')
            ->map(fn (JadwalPelajaran $schedule): ?Kelas => $schedule->kelas)
            ->filter()
            ->values();
    }

    /** @return Collection<int, MataPelajaran> */
    private function subjectOptions(int $kelasId): Collection
    {
        return $this->scheduleOptions()
            ->where('kelas_id', $kelasId)
            ->unique('mata_pelajaran')
            ->map(fn (JadwalPelajaran $schedule): ?MataPelajaran => $schedule->mataPelajaran)
            ->filter()
            ->values();
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return Collection<int, SesiMengajar>
     */
    private function sessions(int $kelasId, int $mapelId, array $period): Collection
    {
        return SesiMengajar::query()
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereHas('jadwalPelajaran', function (Builder $query) use ($kelasId, $mapelId, $period): void {
                $query->where('kelas_id', $kelasId)
                    ->where('mata_pelajaran', $mapelId)
                    ->where('semester', $period['semester'])
                    ->where('tahun_ajaran', $period['tahun_ajaran']);
            })
            ->with('presensiMurids')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();
    }

    private function sessionLabel(SesiMengajar $session): string
    {
        $date = $session->tanggal;

        if ($date === null) {
            return '-';
        }

        return self::HARI[$date->dayOfWeek].', '.$date->day.' '.self::BULAN[$date->month].' '.$date->year;
    }

    private function statusCode(?string $status): ?string
    {
        return match ($status) {
            'Hadir' => 'H',
            'Sakit' => 'S',
            'Izin' => 'I',
            'Alpa' => 'A',
            default => null,
        };
    }
}
