<?php

namespace App\Filament\Pages;

use App\Models\BobotNilai;
use App\Models\ERapor;
use App\Models\JadwalPelajaran;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Bobot nilai rapor diatur oleh role kurikulum, bukan oleh masing-masing guru.
 * Satu set bobot berlaku untuk semua mata pelajaran pada periode tertentu agar
 * seluruh guru pengampu memakai persentase yang sama.
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

    /** @var array<string, string> */
    public array $bobot = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:PengaturanBobotNilai') ?? false;
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

        $rules = [];
        $attributes = [];

        foreach (BobotNilai::KOMPONEN as $key => $label) {
            $rules['bobot.'.$key] = ['required', 'integer', 'between:0,100'];
            $attributes['bobot.'.$key] = $label;
        }

        $validated = $this->validate($rules, [
            'bobot.*.required' => 'Bobot :attribute wajib diisi.',
            'bobot.*.integer' => 'Bobot :attribute harus berupa bilangan bulat.',
            'bobot.*.between' => 'Bobot :attribute harus berada antara 0 dan 100.',
        ], $attributes);

        $values = array_map('intval', $validated['bobot']);
        $total = array_sum($values);

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'bobot' => 'Total bobot harus 100%. Saat ini '.$total.'%.',
            ]);
        }

        DB::transaction(function () use ($values): void {
            BobotNilai::query()->updateOrCreate(
                [
                    'semester' => $this->semester,
                    'tahun_ajaran' => $this->tahunAjaran,
                ],
                BobotNilai::kolomRapor($values),
            );

            ERapor::query()
                ->where('semester', $this->semester)
                ->where('tahun_ajaran', $this->tahunAjaran)
                ->update(BobotNilai::kolomRapor($values));
        });

        $this->muatBobot();

        Notification::make()
            ->success()
            ->title('Bobot nilai tersimpan')
            ->body('Bobot berlaku untuk semua mata pelajaran pada '.$this->semester.' '.$this->tahunAjaran.'.')
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'bobot' => $this->bobot,
            'komponen' => BobotNilai::KOMPONEN,
            'semesterOptions' => JadwalPelajaran::SEMESTERS,
            'tahunAjaranOptions' => $this->tahunAjaranOptions(),
            'period' => ['semester' => $this->semester, 'tahun_ajaran' => $this->tahunAjaran],
        ];
    }

    private function muatBobot(): void
    {
        $values = BobotNilai::untuk($this->semester, $this->tahunAjaran);

        $this->bobot = array_map(
            fn (int $value): string => (string) $value,
            $values,
        );
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
