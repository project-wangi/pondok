<?php

namespace App\Filament\Pages;

use App\Models\AbsenPeminatan;
use App\Models\JadwalPelajaran;
use App\Models\JenisPeminatan;
use App\Models\Murid;
use BackedEnum;
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

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->jenisPeminatanId = JenisPeminatan::query()->orderBy('nama_peminatan')->orderBy('id')->value('id');
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
            'guruName' => Filament::auth()->user()?->name,
            'period' => $period,
            'peminatanUrl' => AbsensiPeminatan::getUrl(),
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
        [$from, $to] = $this->periodBounds($period);

        return AbsenPeminatan::query()
            ->where('jenis_peminatan_id', $jenisPeminatanId)
            ->whereDate('tanggal', '>=', $from)
            ->whereDate('tanggal', '<=', $to)
            ->with('murids')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array{0: string, 1: string}
     */
    private function periodBounds(array $period): array
    {
        $startYear = (int) str($period['tahun_ajaran'])->before('/')->toString();

        if ($period['semester'] === 'Ganjil') {
            return [$startYear.'-07-01', $startYear.'-12-31'];
        }

        $endYear = $startYear + 1;

        return [$endYear.'-01-01', $endYear.'-06-30'];
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
