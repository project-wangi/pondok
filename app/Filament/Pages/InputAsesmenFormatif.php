<?php

namespace App\Filament\Pages;

use App\Models\AsesmenFormatif;
use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use App\Models\Murid;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InputAsesmenFormatif extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Asesmen Formatif';

    protected static ?string $title = 'Asesmen Formatif';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'asesmen-formatif';

    protected string $view = 'filament.pages.input-asesmen-formatif';

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
        $subjectIds = $schedules->pluck('mata_pelajaran')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $classIds = $schedules->pluck('kelas_id')->unique()->values();
        $materials = LingkupMateri::query()
            ->where('guru_id', $guruId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereIn('mata_pelajaran_id', $subjectIds)
            ->withCount('tujuanPembelajarans')
            ->get()
            ->groupBy('mata_pelajaran_id');
        $studentCounts = Murid::query()
            ->whereIn('kelas_id', $classIds)
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');
        $assessments = AsesmenFormatif::query()
            ->where('pencatat_id', Filament::auth()->id())
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount('nilais')
            ->get()
            ->keyBy(fn (AsesmenFormatif $assessment): string => $assessment->kelas_id.'-'.$assessment->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($materials, $studentCounts, $assessments): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $materialGroup = $materials->get($subjectId, collect());
            $tpCount = (int) $materialGroup->sum('tujuan_pembelajarans_count');
            $studentCount = (int) ($studentCounts->get($schedule->kelas_id) ?? 0);
            $assessment = $assessments->get($schedule->kelas_id.'-'.$subjectId);
            $possible = $studentCount * $tpCount;
            $filled = min((int) ($assessment?->nilais_count ?? 0), $possible);

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'student_count' => $studentCount,
                'tp_count' => $tpCount,
                'filled_count' => $filled,
                'possible_count' => $possible,
                'url' => IsiAsesmenFormatif::getUrl(['kelas' => $schedule->kelas_id, 'mapel' => $subjectId]),
                'lingkup_url' => IsiLingkupMateri::getUrl(['mapel' => $subjectId]),
            ];
        });

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
