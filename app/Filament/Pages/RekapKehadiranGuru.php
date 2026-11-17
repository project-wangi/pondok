<?php

namespace App\Filament\Pages;

use App\Filament\Exports\RekapKehadiranGuruWorkbook;
use App\Models\Guru;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Rekap kehadiran guru per tanggal dari catatan Absensi Guru.
 *
 * Satu guru memiliki satu catatan per tanggal berisi jam masuk dan jam pulang.
 */
class RekapKehadiranGuru extends Page
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

    protected static ?string $navigationLabel = 'Rekap Kehadiran Guru';

    protected static ?string $title = 'Rekap Kehadiran Guru';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'rekap-kehadiran-guru';

    protected string $view = 'filament.pages.rekap-kehadiran-guru';

    #[Locked]
    public int $bulan = 1;

    #[Locked]
    public int $tahun = 2026;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:RekapKehadiranGuru') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $now = CarbonImmutable::now();
        $this->bulan = (int) $now->month;
        $this->tahun = (int) $now->year;
    }

    public function pilihBulan(int $bulan): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(array_key_exists($bulan, self::BULAN), 404);

        $this->bulan = $bulan;
    }

    public function pilihTahun(int $tahun): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($tahun, $this->yearOptions(), true), 404);

        $this->tahun = $tahun;
    }

    public function ekspor(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        return app(RekapKehadiranGuruWorkbook::class)->download($this->laporan());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'laporan' => $this->laporan(),
            'bulanOptions' => self::BULAN,
            'tahunOptions' => $this->yearOptions(),
        ];
    }

    /**
     * @return array{
     *     periode: string,
     *     columns: list<array{tanggal: string, nomor: int, hari: string, label: string}>,
     *     rows: list<array{guru_id: int, nama: string, cells: array<string, list<string>>, hadir: int}>,
     *     totals: array{hadir: int},
     *     guruTotal: int,
     *     guruHadir: int,
     *     hariTercatat: int
     * }
     */
    public function laporan(): array
    {
        abort_unless(static::canAccess(), 403);

        [$from, $until] = $this->monthBounds();
        $periode = self::BULAN[$this->bulan].' '.$this->tahun;
        $sources = GajiGuru::attendanceDateSources($from->toDateString(), $until->toDateString());

        $teachers = Guru::query()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Guru $guru): string => mb_strtolower((string) ($guru->user?->name ?? '')))
            ->values();

        $dates = [];

        foreach ($sources as $rows) {
            foreach (array_keys($rows) as $tanggal) {
                $dates[$tanggal] = true;
            }
        }

        ksort($dates);

        $columns = collect(array_keys($dates))
            ->map(function (string $tanggal, int $index): array {
                $day = CarbonImmutable::parse($tanggal);

                return [
                    'tanggal' => $tanggal,
                    'nomor' => $index + 1,
                    'hari' => self::HARI[$day->dayOfWeek],
                    'label' => $day->locale('id')->translatedFormat('j M'),
                ];
            })
            ->all();

        $rows = [];
        $totalHadir = 0;

        foreach ($teachers as $teacher) {
            $teacherSources = $sources[$teacher->id] ?? [];
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column['tanggal']] = array_values(array_unique($teacherSources[$column['tanggal']] ?? []));
            }

            $hadir = count($teacherSources);
            $totalHadir += $hadir;

            $rows[] = [
                'guru_id' => $teacher->id,
                'nama' => $teacher->user?->name ?? '-',
                'cells' => $cells,
                'hadir' => $hadir,
            ];
        }

        return [
            'periode' => $periode,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['hadir' => $totalHadir],
            'guruTotal' => count($rows),
            'guruHadir' => collect($rows)->where('hadir', '>', 0)->count(),
            'hariTercatat' => count($columns),
        ];
    }

    /** @return list<int> */
    private function yearOptions(): array
    {
        $current = (int) CarbonImmutable::now()->year;

        return range($current - 3, $current + 1);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function monthBounds(): array
    {
        $start = CarbonImmutable::create($this->tahun, $this->bulan, 1)->startOfMonth();

        return [$start, $start->addMonth()];
    }
}
