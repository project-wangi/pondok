<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InputNilaiUjian extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Nilai Ujian';

    protected static ?string $title = 'Nilai Ujian';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'nilai-ujian';

    protected string $view = 'filament.pages.input-nilai-ujian';

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
            ->where('pencatat_id', Filament::auth()->id())
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->withCount([
                'komponens',
                'nilais as filled_nilais_count' => fn (Builder $query): Builder => $query->whereNotNull('nilai_ujian_nilais.nilai'),
            ])
            ->get()
            ->keyBy(fn (NilaiUjian $exam): string => $exam->kelas_id.'-'.$exam->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($studentCounts, $exams): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $studentCount = (int) ($studentCounts->get($schedule->kelas_id) ?? 0);
            $exam = $exams->get($schedule->kelas_id.'-'.$subjectId);
            $componentCount = (int) ($exam?->komponens_count ?? 0);
            $possible = $studentCount * $componentCount;

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'student_count' => $studentCount,
                'component_count' => $componentCount,
                'filled_count' => min((int) ($exam?->filled_nilais_count ?? 0), $possible),
                'possible_count' => $possible,
                'url' => IsiNilaiUjian::getUrl(['kelas' => $schedule->kelas_id, 'mapel' => $subjectId]),
            ];
        });

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
