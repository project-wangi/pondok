<?php

namespace App\Filament\Pages;

use App\Models\AbsenPiket;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Murid;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

class RekapAbsensiPiket extends Page
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan Kehadiran';

    protected static ?string $navigationLabel = 'Rekap Absensi Piket';

    protected static ?string $title = 'Rekap Absensi Piket';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'rekap-absensi-piket';

    protected string $view = 'filament.pages.rekap-absensi-piket';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public int $bulan = 1;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:RekapAbsensiPiket') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->bulan = $this->defaultBulan(JadwalPelajaran::activePeriod());
        $this->kelasId = Kelas::query()->orderBy('nama_kelas')->orderBy('id')->value('id');
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
        abort_if(! Kelas::query()->whereKey($kelasId)->exists(), 404);
        $this->kelasId = $kelasId;
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
        $classes = Kelas::query()->orderBy('nama_kelas')->orderBy('id')->get();
        $class = $this->kelasId !== null ? $classes->firstWhere('id', $this->kelasId) : null;
        $students = $class !== null
            ? Murid::query()->where('kelas_id', $class->id)->orderBy('nama')->orderBy('id')->get()
            : new Collection;
        $sessions = $class !== null
            ? $this->sessions($class->id, $period)
            : new Collection;
        $statuses = [];

        foreach ($sessions as $session) {
            foreach ($session->murids as $record) {
                if ($record->murid_id !== null) {
                    $statuses[$record->murid_id][$session->id] = [
                        'masuk' => $record->masuk,
                        'pulang' => $record->pulang,
                    ];
                }
            }
        }

        $columns = [];
        $sessionsPerDate = $sessions->countBy(
            fn (AbsenPiket $session): string => (string) $session->tanggal?->toDateString(),
        );

        foreach ($sessions as $index => $session) {
            $date = (string) $session->tanggal?->toDateString();
            $label = $this->sessionLabel($session);

            // Bedakan kolom bila beberapa guru piket mencatat kelas dan tanggal yang sama.
            if (($sessionsPerDate[$date] ?? 0) > 1 && filled($session->nama_guru)) {
                $label .= ' — '.$session->nama_guru;
            }

            $columns[] = [
                'id' => $session->id,
                'nomor' => $index + 1,
                'label' => $label,
                'tanggal' => $session->tanggal,
            ];
        }

        $rows = [];

        foreach ($students as $student) {
            $masukCounts = ['H' => 0, 'T' => 0, 'I' => 0, 'S' => 0, 'A' => 0];
            $pulangCount = 0;
            $codes = [];

            foreach ($sessions as $session) {
                $record = $statuses[$student->id][$session->id] ?? null;
                $masuk = $this->masukCode($record['masuk'] ?? null);
                $pulang = $this->pulangCode($record['pulang'] ?? null);
                $codes[$session->id] = ['masuk' => $masuk, 'pulang' => $pulang];

                if ($masuk !== null) {
                    $masukCounts[$masuk]++;
                }

                if ($pulang === 'P') {
                    $pulangCount++;
                }
            }

            $recorded = array_sum($masukCounts);

            $rows[] = [
                'student' => $student,
                'codes' => $codes,
                'masuk_counts' => $masukCounts,
                'pulang_count' => $pulangCount,
                'percent' => $recorded > 0
                    ? (int) round((($masukCounts['H'] + $masukCounts['T']) / $recorded) * 100)
                    : null,
            ];
        }

        $male = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'L')->count();
        $female = $students->filter(fn (Murid $student): bool => $student->jenisKelaminKode() === 'P')->count();

        return [
            'classes' => $classes,
            'class' => $class,
            'students' => $students,
            'columns' => $columns,
            'rows' => $rows,
            'maleCount' => $male,
            'femaleCount' => $female,
            'bulanOptions' => $bulanOptions,
            'bulanLabel' => $bulanLabel,
            'period' => $period,
            'piketUrl' => AbsensiPiket::getUrl(),
            'canOpenPiket' => AbsensiPiket::canAccess(),
        ];
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return Collection<int, AbsenPiket>
     */
    private function sessions(int $kelasId, array $period): Collection
    {
        [$from, $until] = $this->monthBounds($period);

        return AbsenPiket::query()
            ->where('kelas_id', $kelasId)
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

    private function sessionLabel(AbsenPiket $session): string
    {
        $date = $session->tanggal;

        if ($date === null) {
            return '-';
        }

        return self::HARI[$date->dayOfWeek].', '.$date->day.' '.self::BULAN[$date->month].' '.$date->year;
    }

    private function masukCode(?string $status): ?string
    {
        return match ($status) {
            'Hadir' => 'H',
            'Terlambat' => 'T',
            'Izin' => 'I',
            'Sakit' => 'S',
            'Alpa' => 'A',
            default => null,
        };
    }

    private function pulangCode(?string $status): ?string
    {
        return match ($status) {
            'Pulang' => 'P',
            'Izin' => 'I',
            'Sakit' => 'S',
            'Alpa' => 'A',
            default => null,
        };
    }
}
