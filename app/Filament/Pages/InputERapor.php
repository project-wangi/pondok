<?php

namespace App\Filament\Pages;

use App\Models\ERapor;
use App\Models\JadwalPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InputERapor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'e-Rapor';

    protected static ?string $title = 'e-Rapor';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'e-rapor';

    protected string $view = 'filament.pages.input-e-rapor';

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
        $exams = NilaiUjian::query()
            ->where('guru_id', $guruId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount('komponens')
            ->get()
            ->keyBy(fn (NilaiUjian $exam): string => $exam->kelas_id.'-'.$exam->mata_pelajaran_id);
        $rapors = ERapor::query()
            ->where('guru_id', $guruId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount([
                'nilais as asts_count' => fn (Builder $query): Builder => $query->whereNotNull('asts'),
                'nilais as asas_count' => fn (Builder $query): Builder => $query->whereNotNull('asas'),
            ])
            ->get()
            ->keyBy(fn (ERapor $rapor): string => $rapor->kelas_id.'-'.$rapor->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($studentCounts, $exams, $rapors): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $studentCount = (int) ($studentCounts->get($schedule->kelas_id) ?? 0);
            $exam = $exams->get($schedule->kelas_id.'-'.$subjectId);
            $rapor = $rapors->get($schedule->kelas_id.'-'.$subjectId);
            $possible = $studentCount * 2;
            $filled = min((int) (($rapor?->asts_count ?? 0) + ($rapor?->asas_count ?? 0)), $possible);

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'student_count' => $studentCount,
                'component_count' => (int) ($exam?->komponens_count ?? 0),
                'filled_count' => $filled,
                'possible_count' => $possible,
                'url' => IsiERapor::getUrl(['kelas' => $schedule->kelas_id, 'mapel' => $subjectId]),
            ];
        });

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
