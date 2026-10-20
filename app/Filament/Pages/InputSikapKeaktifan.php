<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Murid;
use App\Models\SikapKeaktifan;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InputSikapKeaktifan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Sikap dan Keaktifan';

    protected static ?string $title = 'Sikap dan Keaktifan';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'sikap-keaktifan';

    protected string $view = 'filament.pages.input-sikap-keaktifan';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $guruId = Filament::auth()->user()?->guru()->value('id');
        $period = JadwalPelajaran::activePeriod();
        $schedules = JadwalPelajaran::query()
            ->where('guru_id', $guruId)
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->with(['kelas', 'mataPelajaran'])
            ->get()
            ->unique(fn (JadwalPelajaran $schedule): string => $schedule->kelas_id.'-'.$schedule->mata_pelajaran)
            ->sortBy(fn (JadwalPelajaran $schedule): string => ($schedule->kelas?->nama_kelas ?? '').' '.($schedule->mataPelajaran?->nama_mapel ?? ''))
            ->values();
        $classIds = $schedules->pluck('kelas_id')->unique()->values();
        $studentCounts = Murid::query()
            ->whereIn('kelas_id', $classIds)
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');
        $entries = SikapKeaktifan::query()
            ->where('pencatat_id', Filament::auth()->id())
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount('nilais')
            ->get()
            ->keyBy(fn (SikapKeaktifan $entry): string => $entry->kelas_id.'-'.$entry->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($studentCounts, $entries): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $studentCount = (int) ($studentCounts->get($schedule->kelas_id) ?? 0);
            $entry = $entries->get($schedule->kelas_id.'-'.$subjectId);
            $filled = min((int) ($entry?->nilais_count ?? 0), $studentCount);

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'student_count' => $studentCount,
                'filled_count' => $filled,
                'url' => IsiSikapKeaktifan::getUrl(['kelas' => $schedule->kelas_id, 'mapel' => $subjectId]),
            ];
        });

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
