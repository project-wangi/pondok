<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Murid;
use App\Models\RaporKepribadian;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Wali kelas mengisi nilai kepribadian siswa pada kelas yang diampu.
 */
class InputKepribadian extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Nilai Kepribadian';

    protected static ?string $title = 'Nilai Kepribadian';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'kepribadian';

    protected string $view = 'filament.pages.input-kepribadian';

    public string $semester = '';

    public string $tahunAjaran = '';

    /** @var array<int, array<string, string>> */
    public array $kepribadian = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && ($user->hasRole('wali_kelas')
                || $user->guru()->whereNotNull('kelas_id')->exists()
                || $user->isAdministrator()
                || $user->can('View:InputKepribadian'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $period = JadwalPelajaran::activePeriod();
        $this->semester = $period['semester'];
        $this->tahunAjaran = $period['tahun_ajaran'];
        $this->muatKepribadian();
    }

    public function updatedSemester(string $value): void
    {
        if (! array_key_exists($value, JadwalPelajaran::SEMESTERS)) {
            $this->semester = JadwalPelajaran::activePeriod()['semester'];

            return;
        }

        $this->semester = $value;
        $this->muatKepribadian();
    }

    public function updatedTahunAjaran(string $value): void
    {
        if (! in_array($value, $this->tahunAjaranOptions(), true)) {
            $this->tahunAjaran = JadwalPelajaran::activePeriod()['tahun_ajaran'];

            return;
        }

        $this->tahunAjaran = $value;
        $this->muatKepribadian();
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $kelas = $this->kelas();
        abort_if($kelas === null, 404);

        $murids = $this->murids($kelas);
        $rules = [];
        $attributes = [];

        foreach ($murids as $murid) {
            foreach (RaporKepribadian::ASPEK as $key => $label) {
                $rules['kepribadian.'.$murid->id.'.'.$key] = ['nullable', 'integer', 'between:0,100'];
                $attributes['kepribadian.'.$murid->id.'.'.$key] = $label.' '.$murid->nama;
            }
        }

        $validated = $this->validate($rules, [
            'kepribadian.*.*.integer' => 'Nilai :attribute harus berupa angka bulat.',
            'kepribadian.*.*.between' => 'Nilai :attribute harus berada antara 0 dan 100.',
        ], $attributes);

        DB::transaction(function () use ($murids, $validated): void {
            foreach ($murids as $murid) {
                $values = $validated['kepribadian'][$murid->id] ?? [];
                $filled = array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '');

                if ($filled === []) {
                    continue;
                }

                RaporKepribadian::query()->updateOrCreate(
                    [
                        'murid_id' => $murid->id,
                        'semester' => $this->semester,
                        'tahun_ajaran' => $this->tahunAjaran,
                    ],
                    array_map(
                        fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value,
                        $values,
                    ),
                );
            }
        });

        $this->muatKepribadian();

        Notification::make()
            ->success()
            ->title('Nilai kepribadian tersimpan')
            ->body('Huruf kepribadian dibuat otomatis dari angka yang diisi.')
            ->send();
    }

    public function hurufKepribadianMap(): array
    {
        $result = [];

        foreach ($this->kepribadian as $muridId => $values) {
            foreach (array_keys(RaporKepribadian::ASPEK) as $key) {
                $value = trim((string) ($values[$key] ?? ''));
                $result[$muridId][$key] = $value !== '' && ctype_digit($value)
                    ? RaporKepribadian::hurufUntuk((int) $value)
                    : null;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $kelas = $this->kelas();

        return [
            'kelas' => $kelas,
            'murids' => $kelas === null ? new Collection : $this->murids($kelas),
            'kepribadian' => $this->kepribadian,
            'huruf' => $this->hurufKepribadianMap(),
            'aspek' => RaporKepribadian::ASPEK,
            'semesterOptions' => JadwalPelajaran::SEMESTERS,
            'tahunAjaranOptions' => $this->tahunAjaranOptions(),
            'period' => ['semester' => $this->semester, 'tahun_ajaran' => $this->tahunAjaran],
        ];
    }

    private function kelas(): ?Kelas
    {
        $guru = Filament::auth()->user()?->guru()->with('kelasWali')->first();

        return $guru?->kelasWali;
    }

    /** @return Collection<int, Murid> */
    private function murids(Kelas $kelas): Collection
    {
        return Murid::query()
            ->aktif()
            ->where('kelas_id', $kelas->id)
            ->orderBy('nama')
            ->orderBy('id')
            ->get();
    }

    private function muatKepribadian(): void
    {
        $this->kepribadian = [];
        $kelas = $this->kelas();

        if ($kelas === null) {
            return;
        }

        $murids = $this->murids($kelas);
        $records = RaporKepribadian::query()
            ->whereIn('murid_id', $murids->modelKeys())
            ->where('semester', $this->semester)
            ->where('tahun_ajaran', $this->tahunAjaran)
            ->get()
            ->keyBy('murid_id');

        foreach ($murids as $murid) {
            $record = $records->get($murid->id);

            foreach (array_keys(RaporKepribadian::ASPEK) as $key) {
                $this->kepribadian[$murid->id][$key] = $record?->{$key} === null
                    ? ''
                    : (string) $record->{$key};
            }
        }
    }

    /** @return list<string> */
    private function tahunAjaranOptions(): array
    {
        return JadwalPelajaran::query()
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->pluck('tahun_ajaran')
            ->push(JadwalPelajaran::activePeriod()['tahun_ajaran'])
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
