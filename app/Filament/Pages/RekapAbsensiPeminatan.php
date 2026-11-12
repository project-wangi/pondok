<?php

namespace App\Filament\Pages;

use App\Models\AbsenPeminatan;
use App\Models\JadwalPelajaran;
use App\Models\JenisPeminatan;
use App\Models\Murid;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

class RekapAbsensiPeminatan extends Page
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan Kehadiran';

    protected static ?string $navigationLabel = 'Rekap Absensi Peminatan';

    protected static ?string $title = 'Rekap Absensi Peminatan';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'rekap-absensi-peminatan';

    protected string $view = 'filament.pages.rekap-absensi-peminatan';

    #[Locked]
    public ?int $jenisPeminatanId = null;

    #[Locked]
    public int $bulan = 1;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:RekapAbsensiPeminatan') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->bulan = $this->defaultBulan(JadwalPelajaran::activePeriod());
        $this->jenisPeminatanId = JenisPeminatan::query()->orderBy('nama_peminatan')->orderBy('id')->value('id');
    }

    public function pilihBulan(int $bulan): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(array_key_exists($bulan, $this->monthOptions(JadwalPelajaran::activePeriod())), 404);
        $this->bulan = $bulan;
    }

    public function pilihPeminatan(int $jenisPeminatanId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if(! JenisPeminatan::query()->whereKey($jenisPeminatanId)->exists(), 404);
        $this->jenisPeminatanId = $jenisPeminatanId;
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
        $peminatans = JenisPeminatan::query()->orderBy('nama_peminatan')->orderBy('id')->get();
        $peminatan = $this->jenisPeminatanId !== null
            ? $peminatans->firstWhere('id', $this->jenisPeminatanId)
            : null;
        $students = $peminatan !== null ? $this->students($peminatan->id) : new Collection;
        $sessions = $peminatan !== null ? $this->sessions($peminatan->id, $period) : new Collection;
        $statuses = [];

        foreach ($sessions as $session) {
            foreach ($session->murids as $record) {
                if ($record->murid_id !== null) {
                    $statuses[$record->murid_id][$session->id] = $record->status;
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
            $counts = ['H' => 0, 'I' => 0, 'S' => 0, 'A' => 0];
            $codes = [];

            foreach ($sessions as $session) {
                $code = $this->statusCode($statuses[$student->id][$session->id] ?? null);
                $codes[$session->id] = $code;

                if ($code !== null) {
                    $counts[$code]++;
                }
            }

            $recorded = array_sum($counts);

            $rows[] = [
                'student' => $student,
                'codes' => $codes,
                'counts' => $counts,
                'percent' => $recorded > 0 ? (int) round(($counts['H'] / $recorded) * 100) : null,
            ];
        }

        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'peminatans' => $peminatans,
            'peminatan' => $peminatan,
            'students' => $students,
            'columns' => $columns,
            'rows' => $rows,
            'maleCount' => $male,
            'femaleCount' => $female,
            'bulanOptions' => $bulanOptions,
            'bulanLabel' => $bulanLabel,
            'period' => $period,
            'peminatanUrl' => AbsensiPeminatan::getUrl(),
            'canOpenPeminatan' => AbsensiPeminatan::canAccess(),
        ];
    }

    /** @return Collection<int, Murid> */
    private function students(int $jenisPeminatanId): Collection
    {
        return Murid::query()
            ->whereHas('jenisPeminatans', fn (Builder $query): Builder => $query->where('jenis_peminatans.id', $jenisPeminatanId))
            ->with('kelas')
            ->orderBy('nama')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return Collection<int, AbsenPeminatan>
     */
    private function sessions(int $jenisPeminatanId, array $period): Collection
    {
        [$from, $until] = $this->monthBounds($period);

        return AbsenPeminatan::query()
            ->where('jenis_peminatan_id', $jenisPeminatanId)
            ->whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<', $until->toDateString())
            ->with('murids')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
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

    private function sessionLabel(AbsenPeminatan $session): string
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
            'Izin' => 'I',
            'Sakit' => 'S',
            'Alpa' => 'A',
            default => null,
        };
    }
}
