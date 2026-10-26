<?php

namespace App\Filament\Pages;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\SesiMengajar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RekapMengajarGuru extends Page
{
    /** @var array<string, int> */
    public const TINGKAT = [
        'VII' => 7,
        'VIII' => 8,
        'IX' => 9,
        'X' => 10,
        'XI' => 11,
        'XII' => 12,
    ];

    /** @var array<int, string> */
    public const BULAN = [
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

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Rekap Mengajar Guru';

    protected static ?string $title = 'Rekap Mengajar Guru';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'rekap-mengajar-guru';

    protected string $view = 'filament.pages.rekap-mengajar-guru';

    public int $bulan = 1;

    public int $tahun = 2026;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', config('filament-shield.super_admin.name', 'super_admin')])
            || $user->can('ViewAny:Jabatan');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $now = CarbonImmutable::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
    }

    public function updatedBulan(mixed $value): void
    {
        $this->bulan = min(12, max(1, (int) $value));
    }

    public function updatedTahun(mixed $value): void
    {
        $this->tahun = min(2100, max(2000, (int) $value));
    }

    /**
     * Jumlah JP (jam) mengajar setiap guru per kelas pada bulan terpilih.
     *
     * @return array{
     *     periode: string,
     *     columns: list<array{id: int, nama: string}>,
     *     rows: list<array{guru_id: int, nama: string, cells: array<int, array{scheduled: bool, hours: float}>, total: float}>,
     *     totals: array{kelas: array<int, float>, total: float}
     * }
     */
    public function rekap(): array
    {
        abort_unless(static::canAccess(), 403);

        $start = CarbonImmutable::create($this->tahun, $this->bulan, 1)->startOfMonth();
        $period = JadwalPelajaran::activePeriod($start);
        $from = $start->toDateString();
        $until = $start->addMonth()->toDateString();

        $classes = Kelas::query()
            ->get()
            ->sortBy(fn (Kelas $kelas): string => sprintf(
                '%03d-%s',
                self::TINGKAT[$kelas->nama_kelas] ?? 999,
                mb_strtolower($kelas->nama_kelas),
            ))
            ->values();

        $teachers = Guru::query()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Guru $guru): string => mb_strtolower((string) $guru->user?->name))
            ->values();

        $minutes = [];

        SesiMengajar::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->whereNotNull('guru_id')
            ->whereNotNull('kelas_id')
            ->get(['guru_id', 'kelas_id', 'jam_mulai', 'jam_selesai'])
            ->each(function (SesiMengajar $session) use (&$minutes): void {
                $duration = $this->sessionMinutes($session->jam_mulai, $session->jam_selesai);

                if ($duration > 0) {
                    $key = $session->guru_id.'-'.$session->kelas_id;
                    $minutes[$key] = ($minutes[$key] ?? 0) + $duration;
                }
            });

        $scheduled = JadwalPelajaran::query()
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->whereNotNull('guru_id')
            ->whereNotNull('kelas_id')
            ->get(['guru_id', 'kelas_id'])
            ->map(fn (JadwalPelajaran $schedule): string => $schedule->guru_id.'-'.$schedule->kelas_id)
            ->unique()
            ->flip();

        $rows = [];
        $classTotals = array_fill_keys($classes->pluck('id')->all(), 0.0);
        $grandTotal = 0.0;

        foreach ($teachers as $guru) {
            $cells = [];
            $rowTotal = 0.0;

            foreach ($classes as $class) {
                $key = $guru->id.'-'.$class->id;
                $hours = round(($minutes[$key] ?? 0) / 60, 2);

                $cells[$class->id] = [
                    'scheduled' => $scheduled->has($key),
                    'hours' => $hours,
                ];

                $rowTotal += $hours;
                $classTotals[$class->id] += $hours;
            }

            $rowTotal = round($rowTotal, 2);

            $rows[] = [
                'guru_id' => $guru->id,
                'nama' => $guru->user?->name ?? '-',
                'cells' => $cells,
                'total' => $rowTotal,
            ];

            $grandTotal += $rowTotal;
        }

        return [
            'periode' => $start->locale('id')->translatedFormat('F Y'),
            'columns' => $classes->map(fn (Kelas $kelas): array => [
                'id' => $kelas->id,
                'nama' => $kelas->nama_kelas,
            ])->all(),
            'rows' => $rows,
            'totals' => [
                'kelas' => array_map(fn (float $total): float => round($total, 2), $classTotals),
                'total' => round($grandTotal, 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $currentYear = (int) CarbonImmutable::now()->year;
        $years = range($currentYear - 3, $currentYear + 1);

        if (! in_array($this->tahun, $years, true)) {
            $years[] = $this->tahun;
            sort($years);
        }

        return [
            'rekap' => $this->rekap(),
            'bulanOptions' => self::BULAN,
            'tahunOptions' => $years,
            'formatJp' => function (float $hours): string {
                $formatted = rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ',');

                return $formatted === '' ? '0' : $formatted;
            },
        ];
    }

    private function sessionMinutes(?string $mulai, ?string $selesai): int
    {
        if (blank($mulai) || blank($selesai)) {
            return 0;
        }

        $start = CarbonImmutable::parse($mulai);
        $end = CarbonImmutable::parse($selesai);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) round($start->diffInMinutes($end));
    }
}
