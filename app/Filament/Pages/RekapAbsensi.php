<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SesiMengajar;
use BackedEnum;
use Carbon\CarbonImmutable;
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

    #[Locked]
    public int $bulan = 1;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:RekapAbsensi') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->bulan = $this->defaultBulan(JadwalPelajaran::activePeriod());
        $first = $this->scheduleOptions()
            ->first(fn (JadwalPelajaran $schedule): bool => $schedule->kelas_id !== null && $schedule->mata_pelajaran !== null);

        if ($first !== null) {
            $this->kelasId = (int) $first->kelas_id;
            $this->mataPelajaranId = (int) $first->mata_pelajaran;
        }
    }

    public function pilihBulan(int $bulan): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(array_key_exists($bulan, $this->monthOptions(JadwalPelajaran::activePeriod())), 404);
        $this->bulan = $bulan;
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
        $bulanOptions = $this->monthOptions($period);

        if (! array_key_exists($this->bulan, $bulanOptions)) {
            $this->bulan = $this->defaultBulan($period);
        }

        [$monthStart] = $this->monthBounds($period);
        $bulanLabel = self::BULAN[$this->bulan].' '.$monthStart->year;
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
            'guruName' => $class !== null && $subject !== null
                ? $this->teacherNames($class, $subject, $period)
                : '—',
            'bulanOptions' => $bulanOptions,
            'bulanLabel' => $bulanLabel,
            'period' => $period,
            'presensiUrl' => PresensiSiswa::getUrl(),
            'canOpenPresensi' => PresensiSiswa::canAccess(),
        ];
    }

    /**
     * Nama guru pengampu kelas dan mapel terpilih pada periode berjalan.
     * Satu mapel bisa diajar lebih dari satu guru, jadi semua nama unik digabung.
     *
     * @param  array{semester: string, tahun_ajaran: string}  $period
     */
    private function teacherNames(Kelas $class, MataPelajaran $subject, array $period): string
    {
        $names = JadwalPelajaran::query()
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('kelas_id', $class->id)
            ->where('mata_pelajaran', $subject->id)
            ->with('guru.user:id,name')
            ->get()
            ->map(fn (JadwalPelajaran $schedule): ?string => $schedule->guru?->user?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? '—' : $names->implode(', ');
    }

    private function guruId(): ?int
    {
        return Filament::auth()->user()?->guru()->value('id');
    }

    /** @return Collection<int, JadwalPelajaran> */
    private function scheduleOptions(): Collection
    {
        $period = JadwalPelajaran::activePeriod();
        $guruId = $this->guruId();

        return JadwalPelajaran::query()
            ->when($guruId !== null, fn (Builder $query): Builder => $query->where('guru_id', $guruId))
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
        [$from, $until] = $this->monthBounds($period);

        return SesiMengajar::query()
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->where('tanggal', '>=', $from->toDateString())
            ->where('tanggal', '<', $until->toDateString())
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

    /**
     * Bulan yang tersedia mengikuti semester berjalan: Ganjil Juli-Desember,
     * Genap Januari-Juni.
     *
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<int, string>
     */
    private function monthOptions(array $period): array
    {
        return $period['semester'] === 'Ganjil'
            ? array_slice(self::BULAN, 6, null, true)
            : array_slice(self::BULAN, 0, 6, true);
    }

    /** @param array{semester: string, tahun_ajaran: string} $period */
    private function defaultBulan(array $period): int
    {
        $options = $this->monthOptions($period);
        $bulan = (int) now()->month;

        return array_key_exists($bulan, $options) ? $bulan : (int) array_key_first($options);
    }

    /**
     * Rentang tanggal untuk bulan terpilih pada tahun ajaran periode berjalan.
     *
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthBounds(array $period): array
    {
        $startYear = (int) str($period['tahun_ajaran'])->before('/')->toString();
        $year = $period['semester'] === 'Ganjil' ? $startYear : $startYear + 1;
        $start = CarbonImmutable::create($year, $this->bulan, 1)->startOfMonth();

        return [$start, $start->addMonth()];
    }
}
