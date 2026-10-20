<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InputLingkupMateri extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Input Lingkup Materi dan Tujuan Pembelajaran';

    protected static ?string $title = 'Lingkup Materi dan Tujuan Pembelajaran';

    protected static ?int $navigationSort = 1;

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
            ->get();
        $subjectIds = $schedules->pluck('mata_pelajaran')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $subjects = MataPelajaran::query()
            ->whereIn('id', $subjectIds)
            ->orderBy('nama_mapel')
            ->get();
        $materials = LingkupMateri::query()
            ->where('guru_id', $guruId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereIn('mata_pelajaran_id', $subjectIds)
            ->withCount('tujuanPembelajarans')
            ->get()
            ->groupBy('mata_pelajaran_id');
        $classes = $schedules->groupBy('mata_pelajaran')->map(
            fn ($group) => $group->pluck('kelas.nama_kelas')->filter()->unique()->sort()->values(),
        );

        $rows = $subjects->map(function (MataPelajaran $subject) use ($materials, $classes): array {
            $subjectMaterials = $materials->get($subject->id, collect());

            return [
                'subject' => $subject,
                'classes' => $classes->get($subject->id, collect()),
                'lingkup_count' => $subjectMaterials->count(),
                'tujuan_count' => $subjectMaterials->sum('tujuan_pembelajarans_count'),
                'url' => IsiLingkupMateri::getUrl(['mapel' => $subject->id]),
            ];
        })->values();

        return [
            'rows' => $rows,
            'period' => $period,
        ];
    }
}
