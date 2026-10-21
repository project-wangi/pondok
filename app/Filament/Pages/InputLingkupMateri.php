<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InputLingkupMateri extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Lingkup Materi dan Tujuan Pembelajaran';

    protected static ?string $title = 'Lingkup Materi dan Tujuan Pembelajaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'lingkup-materi';

    protected string $view = 'filament.pages.input-lingkup-materi';

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
            ->with(['mataPelajaran', 'kelas'])
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
            ->whereIn('kelas_id', $classIds)
            ->whereIn('mata_pelajaran_id', $subjectIds)
            ->withCount('tujuanPembelajarans')
            ->get()
            ->groupBy(fn (LingkupMateri $material): string => $material->kelas_id.'-'.$material->mata_pelajaran_id);

        $rows = $schedules->map(function (JadwalPelajaran $schedule) use ($materials): array {
            $subjectId = (int) $schedule->mata_pelajaran;
            $subjectMaterials = $materials->get($schedule->kelas_id.'-'.$subjectId, collect());

            return [
                'kelas' => $schedule->kelas,
                'subject' => $schedule->mataPelajaran,
                'lingkup_count' => $subjectMaterials->count(),
                'tujuan_count' => $subjectMaterials->sum('tujuan_pembelajarans_count'),
                'url' => IsiLingkupMateri::getUrl([
                    'kelas' => $schedule->kelas_id,
                    'mapel' => $subjectId,
                ]),
            ];
        });

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
