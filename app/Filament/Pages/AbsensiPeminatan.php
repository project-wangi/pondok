<?php

namespace App\Filament\Pages;

use App\Models\AbsenPeminatan;
use App\Models\AbsenPeminatanMurid;
use App\Models\Guru;
use App\Models\JadwalPeminatan;
use App\Models\Murid;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use UnitEnum;

class AbsensiPeminatan extends Page
{
    private const EDIT_WINDOW_DAYS = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Absensi Peminatan';

    protected static ?string $title = 'Absensi Peminatan';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.absensi-peminatan';

    public string $tanggal = '';

    #[Locked]
    public ?int $peminatanId = null;

    #[Locked]
    public ?int $absenId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<string, array{status: string}> */
    public array $kehadiran = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function updatedTanggal(): void
    {
        $this->clearSelection();
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'absensi-peminatan-modal');
        $this->validateDate();
    }

    public function pilihPeminatan(int $id): void
    {
        $this->loadPeminatan($id);
        $this->dispatch('open-modal', id: 'absensi-peminatan-modal');
    }

    public function lihatAbsen(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();
        abort_unless($this->hasScheduleOnDate(), 403);
        $session = AbsenPeminatan::query()->whereDate('tanggal', $this->tanggal)->with('murids')->findOrFail($id);
        $this->peminatanId = null;
        $this->revisi = null;
        $this->kehadiran = [];
        $this->absenId = $session->id;
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'absensi-peminatan-modal');
    }

    public function tutupAbsen(): void
    {
        $this->clearSelection();
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'absensi-peminatan-modal');
    }

    public function tandaiSemuaHadir(): void
    {
        $this->assertCanFill();

        foreach ($this->kehadiran as $key => $row) {
            $this->kehadiran[$key]['status'] = 'Hadir';
        }
    }

    public function simpan(): void
    {
        $this->assertCanFill();
        $this->validate(['peminatanId' => ['required', 'integer']], [], ['peminatanId' => 'jenis peminatan']);

        DB::transaction(function (): void {
            abort_unless($this->teachesPeminatan((int) $this->peminatanId), 403);

            $session = $this->sessionFor((int) $this->peminatanId, lock: true);

            if ($session?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['kehadiran' => 'Absensi peminatan telah diperbarui dari halaman lain. Pilih ulang peminatan sebelum menyimpan.']);
            }

            $roster = $this->rosterFor((int) $this->peminatanId, $session, lock: true);

            if ($roster === []) {
                throw ValidationException::withMessages(['kehadiran' => 'Belum ada siswa yang terdaftar pada peminatan ini. Hubungi admin untuk mengatur data peminatan siswa.']);
            }

            $expectedKeys = array_keys($roster);
            $actualKeys = array_keys($this->kehadiran);
            sort($expectedKeys);
            sort($actualKeys);

            if ($expectedKeys !== $actualKeys) {
                throw ValidationException::withMessages(['kehadiran' => 'Daftar siswa atau absensi telah berubah. Pilih ulang peminatan sebelum menyimpan.']);
            }

            $data = Validator::make(['kehadiran' => $this->kehadiran], [
                'kehadiran' => ['required', 'array'],
                'kehadiran.*' => ['required', 'array:status'],
                'kehadiran.*.status' => ['required', Rule::in(array_keys(AbsenPeminatan::STATUSES))],
            ], [
                'kehadiran.*.status.required' => 'Pilih status kehadiran untuk setiap siswa.',
                'kehadiran.*.status.in' => 'Status kehadiran tidak valid.',
            ])->validate();

            $peminatan = $this->scheduledPeminatans()->firstWhere('jenis_peminatan_id', $this->peminatanId);

            if ($peminatan === null) {
                throw ValidationException::withMessages(['peminatanId' => 'Jadwal peminatan tidak tersedia untuk tanggal ini.']);
            }

            if ($session === null) {
                $session = AbsenPeminatan::query()->create([
                    'jenis_peminatan_id' => $peminatan->jenis_peminatan_id,
                    'guru_id' => $this->currentGuru()?->id,
                    'pencatat_id' => Filament::auth()->id(),
                    'revisi' => 1,
                    'tanggal' => $this->tanggal,
                    'jam_mulai' => $peminatan->jam_mulai,
                    'jam_selesai' => $peminatan->jam_selesai,
                    'nama_guru' => Filament::auth()->user()?->name ?? 'Guru',
                    'nama_peminatan' => $peminatan->jenisPeminatan?->nama_peminatan ?? 'Peminatan',
                    'dicatat_pada' => now(),
                ]);
            } else {
                $session->increment('revisi');
            }

            $session->murids()->delete();
            $now = now();
            $records = [];

            foreach ($roster as $key => $student) {
                $records[] = [
                    'absen_peminatan_id' => $session->id,
                    'murid_id' => $student['murid_id'],
                    'nama_murid' => $student['nama'],
                    'nis' => $student['nis'],
                    'nama_kelas' => $student['nama_kelas'],
                    'status' => $data['kehadiran'][$key]['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            AbsenPeminatanMurid::query()->insert($records);
        }, attempts: 3);

        $this->loadPeminatan($this->peminatanId);
        $this->dispatch('close-modal', id: 'absensi-peminatan-modal');
        Notification::make()->success()->title('Absensi peminatan tersimpan')
            ->body('Kehadiran tersimpan untuk peminatan dan tanggal ini.')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $validDate = Validator::make(['tanggal' => $this->tanggal], $this->dateRules())->passes();
        $isEditable = $validDate && $this->isWithinEditWindow();
        $schedules = $validDate ? $this->scheduledPeminatans() : collect();
        $hasSchedule = $schedules->isNotEmpty();
        $rows = [];
        $sessions = collect();
        $selection = null;
        $modalSession = null;
        $roster = [];

        if ($hasSchedule) {
            $sessions = AbsenPeminatan::query()
                ->whereDate('tanggal', $this->tanggal)
                ->with('murids')
                ->get()
                ->keyBy('jenis_peminatan_id');

            if ($isEditable) {
                $studentCounts = DB::table('jenis_peminatan_murid')
                    ->selectRaw('jenis_peminatan_id, count(*) as total')
                    ->whereIn('jenis_peminatan_id', $schedules->pluck('jenis_peminatan_id'))
                    ->groupBy('jenis_peminatan_id')
                    ->pluck('total', 'jenis_peminatan_id');

                foreach ($schedules as $schedule) {
                    $peminatanId = (int) $schedule->jenis_peminatan_id;
                    $session = $sessions->get($peminatanId);

                    $rows[] = $this->peminatanRow(
                        $peminatanId,
                        $schedule->jenisPeminatan?->nama_peminatan ?? 'Peminatan tidak tersedia',
                        (int) ($studentCounts->get($peminatanId) ?? 0),
                        $session,
                        $schedule->jam_mulai,
                        $schedule->jam_selesai,
                    );
                }
            } else {
                foreach ($sessions->sortBy('nama_peminatan') as $session) {
                    $rows[] = $this->peminatanRow(
                        $session->jenis_peminatan_id,
                        $session->nama_peminatan,
                        $session->murids->count(),
                        $session,
                        $session->jam_mulai,
                        $session->jam_selesai,
                    );
                }
            }

            if ($this->peminatanId !== null && $isEditable) {
                $schedule = $schedules->firstWhere('jenis_peminatan_id', $this->peminatanId);

                if ($schedule !== null) {
                    $session = $this->sessionFor((int) $this->peminatanId);
                    $selection = [
                        'id' => (int) $this->peminatanId,
                        'nama' => $schedule->jenisPeminatan?->nama_peminatan ?? 'Peminatan tidak tersedia',
                        'jam_mulai' => $schedule->jam_mulai,
                        'jam_selesai' => $schedule->jam_selesai,
                    ];
                    $roster = $this->rosterFor((int) $this->peminatanId, $session);
                }
            }

            if ($this->absenId !== null) {
                $modalSession = $sessions->firstWhere('id', $this->absenId);
            }
        }

        return [
            'today' => now()->toDateString(),
            'isEditable' => $isEditable,
            'hasSchedule' => $hasSchedule,
            'rows' => collect($rows),
            'selection' => $selection,
            'roster' => $roster,
            'modalSession' => $modalSession,
            'statuses' => AbsenPeminatan::STATUSES,
        ];
    }

    /** @return array{key: string, peminatan_id: int|null, absen_id: int|null, nama_peminatan: string, jam_mulai: string|null, jam_selesai: string|null, student_count: int, filled_count: int, hadir_count: int} */
    private function peminatanRow(?int $peminatanId, string $name, int $studentCount, ?AbsenPeminatan $session, ?string $jamMulai = null, ?string $jamSelesai = null): array
    {
        $records = $session?->murids ?? collect();

        return [
            'key' => $session !== null ? 'absen-'.$session->id : 'peminatan-'.$peminatanId,
            'peminatan_id' => $peminatanId,
            'absen_id' => $session?->id,
            'nama_peminatan' => $name,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'student_count' => $studentCount,
            'filled_count' => min($records->count(), $studentCount),
            'hadir_count' => $records->where('status', 'Hadir')->count(),
        ];
    }

    private function loadPeminatan(int $id): void
    {
        $this->assertCanFill();
        abort_unless($this->teachesPeminatan($id), 404);
        $session = $this->sessionFor($id);
        $this->peminatanId = $id;
        $this->absenId = $session?->id;
        $this->revisi = $session?->revisi;
        $this->resetValidation();
        $this->kehadiran = [];

        foreach ($this->rosterFor($id, $session) as $key => $student) {
            $this->kehadiran[$key] = ['status' => $student['status']];
        }
    }

    private function clearSelection(): void
    {
        $this->peminatanId = null;
        $this->absenId = null;
        $this->revisi = null;
        $this->kehadiran = [];
    }

    /** @return Collection<int, JadwalPeminatan> */
    private function scheduledPeminatans(): Collection
    {
        $guruId = Filament::auth()->user()?->guru()->value('id');

        if ($guruId === null) {
            return new Collection;
        }

        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][CarbonImmutable::parse($this->tanggal)->dayOfWeek];

        return JadwalPeminatan::query()
            ->where('guru_id', $guruId)
            ->where('hari', $day)
            ->with('jenisPeminatan')
            ->get()
            ->unique('jenis_peminatan_id')
            ->sortBy(fn (JadwalPeminatan $schedule): string => $schedule->jenisPeminatan?->nama_peminatan ?? '')
            ->values();
    }

    private function teachesPeminatan(int $id): bool
    {
        return $this->scheduledPeminatans()->contains('jenis_peminatan_id', $id);
    }

    private function sessionFor(int $peminatanId, bool $lock = false): ?AbsenPeminatan
    {
        return AbsenPeminatan::query()
            ->where('jenis_peminatan_id', $peminatanId)
            ->whereDate('tanggal', $this->tanggal)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with(['murids' => function (HasMany $relation) use ($lock): void {
                if ($lock) {
                    $relation->lockForUpdate();
                }
            }])
            ->first();
    }

    /** @return array<string, array{murid_id: ?int, nama: string, nis: string|null, nama_kelas: string|null, status: string, row_id: int|null}> */
    private function rosterFor(int $peminatanId, ?AbsenPeminatan $session, bool $lock = false): array
    {
        if ($session !== null) {
            return $session->murids->sortBy('nama_murid')->mapWithKeys(fn (AbsenPeminatanMurid $record): array => [
                'p'.$record->id => [
                    'murid_id' => $record->murid_id,
                    'nama' => $record->nama_murid,
                    'nis' => $record->nis,
                    'nama_kelas' => $record->nama_kelas,
                    'status' => $record->status,
                    'row_id' => $record->id,
                ],
            ])->all();
        }

        return Murid::query()
            ->whereHas('jenisPeminatans', fn (Builder $query): Builder => $query->where('jenis_peminatans.id', $peminatanId))
            ->with('kelas')
            ->orderBy('nama')->orderBy('id')
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get(['id', 'nama', 'nis', 'kelas_id'])
            ->mapWithKeys(fn (Murid $student): array => [
                'm'.$student->id => [
                    'murid_id' => $student->id,
                    'nama' => $student->nama,
                    'nis' => $student->nis,
                    'nama_kelas' => $student->kelas?->nama_kelas,
                    'status' => '',
                    'row_id' => null,
                ],
            ])->all();
    }

    private function currentGuru(): ?Guru
    {
        return Filament::auth()->user()?->guru()->first();
    }

    /** @return array{tanggal: list<string>} */
    private function dateRules(): array
    {
        return ['tanggal' => [
            'required',
            'date_format:Y-m-d',
            'before_or_equal:'.CarbonImmutable::now()->toDateString(),
        ]];
    }

    private function validateDate(): void
    {
        $this->validate($this->dateRules(), [
            'tanggal.before_or_equal' => 'Tanggal absensi peminatan tidak boleh di masa depan.',
        ]);
    }

    private function isWithinEditWindow(): bool
    {
        $now = CarbonImmutable::now();

        return $this->tanggal >= $now->subDays(self::EDIT_WINDOW_DAYS)->toDateString()
            && $this->tanggal <= $now->toDateString();
    }

    private function hasScheduleOnDate(): bool
    {
        return $this->scheduledPeminatans()->isNotEmpty();
    }

    private function assertCanFill(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();

        if (! $this->isWithinEditWindow()) {
            throw ValidationException::withMessages(['tanggal' => 'Absensi peminatan hanya dapat diisi atau diperbaiki maksimal 7 hari ke belakang. Tanggal yang lebih lama hanya dapat dilihat.']);
        }

        abort_unless($this->hasScheduleOnDate(), 403);
    }
}
