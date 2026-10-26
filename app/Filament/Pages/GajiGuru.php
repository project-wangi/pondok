<?php

namespace App\Filament\Pages;

use App\Models\AbsenPiket;
use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\SesiMengajar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GajiGuru extends Page
{
    public const HONOR_PER_JAM = 10000;

    public const TRANSPORT_PER_KM_PER_KEHADIRAN = 1000;

    public const HONOR_RAPAT = 20000;

    public const HONOR_PIKET = 10000;

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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Gaji Guru';

    protected static ?string $title = 'Gaji Guru';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'gaji-guru';

    protected string $view = 'filament.pages.gaji-guru';

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
     * Ringkasan gaji seluruh guru pada bulan terpilih.
     *
     * @return array{periode: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function rekap(): array
    {
        abort_unless(static::canAccess(), 403);

        $start = CarbonImmutable::create($this->tahun, $this->bulan, 1)->startOfMonth();
        $from = $start->toDateString();
        $until = $start->addMonth()->toDateString();

        $teachingMinutes = [];

        SesiMengajar::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->whereNotNull('guru_id')
            ->whereHas('presensiMurids')
            ->get(['guru_id', 'jam_mulai', 'jam_selesai'])
            ->each(function (SesiMengajar $session) use (&$teachingMinutes): void {
                $minutes = self::sessionMinutes($session->jam_mulai, $session->jam_selesai);

                if ($minutes > 0) {
                    $teachingMinutes[$session->guru_id] = ($teachingMinutes[$session->guru_id] ?? 0) + $minutes;
                }
            });

        $attendanceCounts = SesiMengajar::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->whereNotNull('guru_id')
            ->whereHas('presensiMurids')
            ->selectRaw('guru_id, count(distinct tanggal) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->all();

        $meetingCounts = AbsenRapat::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->whereNotNull('guru_id')
            ->selectRaw('guru_id, count(*) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->all();

        $picketCounts = AbsenPiket::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->whereNotNull('guru_id')
            ->selectRaw('guru_id, count(distinct tanggal) as total')
            ->groupBy('guru_id')
            ->pluck('total', 'guru_id')
            ->all();

        $positionTotals = DB::table('guru_jabatan')
            ->join('jabatans', 'jabatans.id', '=', 'guru_jabatan.jabatan_id')
            ->selectRaw('guru_jabatan.guru_id as guru_id, sum(jabatans.gaji) as total')
            ->groupBy('guru_jabatan.guru_id')
            ->pluck('total', 'guru_id')
            ->all();

        $specializationTotals = DB::table('absen_peminatans')
            ->join('jenis_peminatans', 'jenis_peminatans.id', '=', 'absen_peminatans.jenis_peminatan_id')
            ->where('absen_peminatans.tanggal', '>=', $from)
            ->where('absen_peminatans.tanggal', '<', $until)
            ->selectRaw('absen_peminatans.guru_id as guru_id, sum(jenis_peminatans.nominal) as total')
            ->groupBy('absen_peminatans.guru_id')
            ->pluck('total', 'guru_id')
            ->all();

        $teachers = Guru::query()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Guru $guru): string => mb_strtolower((string) $guru->user?->name))
            ->values();

        $rows = [];
        $totals = [
            'mengajar' => 0,
            'transport' => 0,
            'jabatan' => 0,
            'rapat' => 0,
            'peminatan' => 0,
            'piket' => 0,
            'total' => 0,
        ];

        foreach ($teachers as $guru) {
            $minutes = (int) ($teachingMinutes[$guru->id] ?? 0);
            $hours = $minutes / 60;
            $attendanceDays = (int) ($attendanceCounts[$guru->id] ?? 0);
            $distance = (float) ($guru->jarak_rumah ?? 0);

            $mengajar = (int) round($hours * self::HONOR_PER_JAM);
            $transport = (int) round($distance * self::TRANSPORT_PER_KM_PER_KEHADIRAN * $attendanceDays);
            $jabatan = (int) ($positionTotals[$guru->id] ?? 0);
            $rapat = (int) ($meetingCounts[$guru->id] ?? 0) * self::HONOR_RAPAT;
            $peminatan = (int) ($specializationTotals[$guru->id] ?? 0);
            $piket = (int) ($picketCounts[$guru->id] ?? 0) * self::HONOR_PIKET;
            $total = $mengajar + $transport + $jabatan + $rapat + $peminatan + $piket;

            $rows[] = [
                'guru_id' => $guru->id,
                'nama' => $guru->user?->name ?? '-',
                'jam' => round($hours, 2),
                'hari_hadir' => $attendanceDays,
                'mengajar' => $mengajar,
                'transport' => $transport,
                'jabatan' => $jabatan,
                'rapat' => $rapat,
                'peminatan' => $peminatan,
                'piket' => $piket,
                'total' => $total,
            ];

            $totals['mengajar'] += $mengajar;
            $totals['transport'] += $transport;
            $totals['jabatan'] += $jabatan;
            $totals['rapat'] += $rapat;
            $totals['peminatan'] += $peminatan;
            $totals['piket'] += $piket;
            $totals['total'] += $total;
        }

        return [
            'periode' => $start->locale('id')->translatedFormat('F Y'),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Rincian gaji satu guru untuk bulan tertentu.
     *
     * @return array<string, mixed>
     */
    public static function rincianGuru(Guru $guru, int $bulan, int $tahun): array
    {
        $start = CarbonImmutable::create($tahun, $bulan, 1)->startOfMonth();
        $from = $start->toDateString();
        $until = $start->addMonth()->toDateString();

        $sessions = SesiMengajar::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->where('guru_id', $guru->id)
            ->whereHas('presensiMurids')
            ->get(['tanggal', 'jam_mulai', 'jam_selesai']);

        $minutes = 0;
        $attendanceDays = [];

        foreach ($sessions as $session) {
            $duration = self::sessionMinutes($session->jam_mulai, $session->jam_selesai);

            if ($duration > 0) {
                $minutes += $duration;
                $attendanceDays[$session->tanggal->toDateString()] = true;
            }
        }

        $hours = $minutes / 60;
        $dayCount = count($attendanceDays);
        $distance = (float) ($guru->jarak_rumah ?? 0);

        $meetingCount = AbsenRapat::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->where('guru_id', $guru->id)
            ->count();

        $picketCount = AbsenPiket::query()
            ->where('tanggal', '>=', $from)
            ->where('tanggal', '<', $until)
            ->where('guru_id', $guru->id)
            ->distinct()
            ->count('tanggal');

        $positions = DB::table('guru_jabatan')
            ->join('jabatans', 'jabatans.id', '=', 'guru_jabatan.jabatan_id')
            ->where('guru_jabatan.guru_id', $guru->id)
            ->orderBy('jabatans.nama_jabatan')
            ->get(['jabatans.nama_jabatan', 'jabatans.gaji']);

        $specializationMeetings = DB::table('absen_peminatans')
            ->join('jenis_peminatans', 'jenis_peminatans.id', '=', 'absen_peminatans.jenis_peminatan_id')
            ->where('absen_peminatans.guru_id', $guru->id)
            ->where('absen_peminatans.tanggal', '>=', $from)
            ->where('absen_peminatans.tanggal', '<', $until)
            ->orderBy('jenis_peminatans.nama_peminatan')
            ->get(['jenis_peminatans.nama_peminatan', 'jenis_peminatans.nominal']);

        $specializationItems = $specializationMeetings
            ->groupBy('nama_peminatan')
            ->map(fn ($rows, string $nama): array => [
                'nama' => $nama,
                'nominal' => (int) $rows->first()->nominal,
                'jumlah' => $rows->count(),
                'total' => $rows->count() * (int) $rows->first()->nominal,
            ])
            ->values()
            ->all();

        $mengajar = (int) round($hours * self::HONOR_PER_JAM);
        $transport = (int) round($distance * self::TRANSPORT_PER_KM_PER_KEHADIRAN * $dayCount);
        $jabatan = (int) $positions->sum('gaji');
        $rapat = $meetingCount * self::HONOR_RAPAT;
        $peminatan = array_sum(array_column($specializationItems, 'total'));
        $piket = $picketCount * self::HONOR_PIKET;

        return [
            'guru_id' => $guru->id,
            'nama' => $guru->user?->name ?? '-',
            'periode' => $start->locale('id')->translatedFormat('F Y'),
            'jarak_rumah' => $distance,
            'jam' => round($hours, 2),
            'hari_hadir' => $dayCount,
            'mengajar' => $mengajar,
            'transport' => $transport,
            'jabatan' => $jabatan,
            'jabatan_items' => $positions->map(fn ($position): array => [
                'nama' => $position->nama_jabatan,
                'gaji' => (int) $position->gaji,
            ])->all(),
            'rapat_count' => $meetingCount,
            'rapat' => $rapat,
            'peminatan' => $peminatan,
            'peminatan_items' => $specializationItems,
            'peminatan_count' => $specializationMeetings->count(),
            'piket_count' => $picketCount,
            'piket' => $piket,
            'total' => $mengajar + $transport + $jabatan + $rapat + $peminatan + $piket,
        ];
    }

    /**
     * Durasi satu sesi mengajar dalam menit. Sesi tanpa jam yang lengkap diabaikan.
     */
    private static function sessionMinutes(?string $mulai, ?string $selesai): int
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
            'rupiah' => fn (int $value): string => 'Rp '.number_format($value, 0, ',', '.'),
        ];
    }
}
