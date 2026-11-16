<?php

namespace App\Filament\Pages;

use App\Models\BobotNilai;
use App\Models\ERapor;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Bobot nilai rapor diatur oleh role kurikulum, bukan oleh masing-masing guru.
 * Bobot berlaku untuk satu mata pelajaran pada periode tertentu sehingga semua
 * guru pengampu mata pelajaran tersebut memakai persentase yang sama.
 */
class PengaturanBobotNilai extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Bobot Nilai';

    protected static ?string $title = 'Bobot Nilai Rapor';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'bobot-nilai';

    protected string $view = 'filament.pages.pengaturan-bobot-nilai';

    public string $semester = '';

    public string $tahunAjaran = '';

    /** @var array<int, array<string, string>> */
    public array $bobot = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && ($user->hasRole('kurikulum')
                || $user->isAdministrator()
                || $user->can('View:PengaturanBobotNilai'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $period = JadwalPelajaran::activePeriod();
        $this->semester = $period['semester'];
        $this->tahunAjaran = $period['tahun_ajaran'];
        $this->muatBobot();
    }

    public function updatedSemester(string $value): void
    {
        if (! array_key_exists($value, JadwalPelajaran::SEMESTERS)) {
            $this->semester = JadwalPelajaran::activePeriod()['semester'];

            return;
        }

        $this->semester = $value;
        $this->muatBobot();
    }

    public function updatedTahunAjaran(string $value): void
    {
        if (! in_array($value, $this->tahunAjaranOptions(), true)) {
            $this->tahunAjaran = JadwalPelajaran::activePeriod()['tahun_ajaran'];

            return;
        }

        $this->tahunAjaran = $value;
        $this->muatBobot();
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $mapels = $this->mapels();
        $rules = [];
        $attributes = [];

        foreach ($mapels as $mapel) {
            foreach (BobotNilai::KOMPONEN as $key => $label) {
                $rules['bobot.'.$mapel->id.'.'.$key] = ['required', 'integer', 'between:0,100'];
                $attributes['bobot.'.$mapel->id.'.'.$key] = $label.' '.$mapel->nama_mapel;
            }
        }

        $validated = $this->validate($rules, [
            'bobot.*.*.required' => 'Bobot :attribute wajib diisi.',
            'bobot.*.*.integer' => 'Bobot :attribute harus berupa bilangan bulat.',
            'bobot.*.*.between' => 'Bobot :attribute harus berada antara 0 dan 100.',
        ], $attributes);

        $values = [];

        foreach ($mapels as $mapel) {
            $row = array_map('intval', $validated['bobot'][$mapel->id] ?? []);
            $total = array_sum($row);

            if ($total !== 100) {
                throw ValidationException::withMessages([
                    'bobot.'.$mapel->id => 'Total bobot '.$mapel->nama_mapel.' harus 100%. Saat ini '.$total.'%.',
                ]);
            }

            $values[$mapel->id] = $row;
        }

        DB::transaction(function () use ($values): void {
            foreach ($values as $mapelId => $row) {
                BobotNilai::query()->updateOrCreate(
                    [
                        'mata_pelajaran_id' => $mapelId,
                        'semester' => $this->semester,
                        'tahun_ajaran' => $this->tahunAjaran,
                    ],
                    BobotNilai::kolomRapor($row),
                );

                ERapor::query()
                    ->where('mata_pelajaran_id', $mapelId)
                    ->where('semester', $this->semester)
                    ->where('tahun_ajaran', $this->tahunAjaran)
                    ->update(BobotNilai::kolomRapor($row));
            }
        });

        $this->muatBobot();

        Notification::make()
            ->success()
            ->title('Bobot nilai tersimpan')
            ->body('Bobot berlaku untuk seluruh guru pengampu pada '.$this->semester.' '.$this->tahunAjaran.'.')
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'mapels' => $this->mapels(),
            'bobot' => $this->bobot,
            'komponen' => BobotNilai::KOMPONEN,
            'semesterOptions' => JadwalPelajaran::SEMESTERS,
            'tahunAjaranOptions' => $this->tahunAjaranOptions(),
            'period' => ['semester' => $this->semester, 'tahun_ajaran' => $this->tahunAjaran],
        ];
    }

    /** @return Collection<int, MataPelajaran> */
    private function mapels(): Collection
    {
        return MataPelajaran::query()->orderBy('nama_mapel')->get();
    }

    private function muatBobot(): void
    {
        $this->bobot = [];

        foreach ($this->mapels() as $mapel) {
            $values = BobotNilai::untuk($mapel->id, $this->semester, $this->tahunAjaran);
            $this->bobot[$mapel->id] = array_map(
                fn (int $value): string => (string) $value,
                $values,
            );
        }
    }

    /** @return list<string> */
    private function tahunAjaranOptions(): array
    {
        return JadwalPelajaran::query()
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->pluck('tahun_ajaran')
            ->merge(ERapor::query()->whereNotNull('tahun_ajaran')->distinct()->pluck('tahun_ajaran'))
            ->push(JadwalPelajaran::activePeriod()['tahun_ajaran'])
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
