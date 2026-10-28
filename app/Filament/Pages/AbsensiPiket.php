<?php

namespace App\Filament\Pages;

use App\Models\AbsenPiket;
use App\Models\AbsenPiketMurid;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Murid;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use UnitEnum;

class AbsensiPiket extends Page
{
    private const EDIT_WINDOW_DAYS = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Absensi';

    protected static ?string $navigationLabel = 'Absensi Piket';

    protected static ?string $title = 'Absensi Piket';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.absensi-piket';

    public string $tanggal = '';

    #[Locked]
    public ?int $kelasId = null;

    #[Locked]
    public ?int $absenId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<string, array{masuk: string|null, pulang: string|null}> */
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
        $this->dispatch('close-modal', id: 'absensi-piket-modal');
        $this->validateDate();
    }

    public function pilihKelas(int $id): void
    {
        $this->loadClass($id);
        $this->dispatch('open-modal', id: 'absensi-piket-modal');
    }

    public function lihatAbsen(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();
        abort_unless($this->hasPiketOnDate(), 403);
        $session = $this->sessionQuery()->with('murids')->findOrFail($id);
        $this->kelasId = null;
        $this->revisi = null;
        $this->kehadiran = [];
        $this->absenId = $session->id;
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'absensi-piket-modal');
    }

    public function tutupAbsen(): void
    {
        $this->clearSelection();
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'absensi-piket-modal');
    }

    public function tandaiSemuaMasuk(): void
    {
        $this->assertCanFill();

        foreach ($this->kehadiran as $key => $row) {
            $this->kehadiran[$key]['masuk'] = 'Hadir';
        }
    }

    public function tandaiSemuaPulang(): void
    {
        $this->assertCanFill();

        foreach ($this->kehadiran as $key => $row) {
            $this->kehadiran[$key]['pulang'] = 'Pulang';
        }
    }

    public function simpan(): void
    {
        $this->assertCanFill();
        $this->validate(['kelasId' => ['required', 'integer']], [], ['kelasId' => 'kelas']);

        DB::transaction(function (): void {
            $class = Kelas::query()->lockForUpdate()->findOrFail($this->kelasId);
            $session = $this->sessionFor($class, lock: true);

            if ($session?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['kehadiran' => 'Absensi piket telah diperbarui dari halaman lain. Pilih ulang kelas sebelum menyimpan.']);
            }

            $roster = $this->rosterFor($class, $session, lock: true);

            if ($roster === []) {
                throw ValidationException::withMessages(['kehadiran' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas siswa.']);
            }

            $expectedKeys = array_keys($roster);
            $actualKeys = array_keys($this->kehadiran);
            sort($expectedKeys);
            sort($actualKeys);

            if ($expectedKeys !== $actualKeys) {
                throw ValidationException::withMessages(['kehadiran' => 'Daftar siswa atau absensi telah berubah. Pilih ulang kelas sebelum menyimpan.']);
            }

            $validator = Validator::make(['kehadiran' => $this->kehadiran], [
                'kehadiran' => ['required', 'array'],
                'kehadiran.*' => ['required', 'array:masuk,pulang'],
                'kehadiran.*.masuk' => ['nullable', Rule::in(array_keys(AbsenPiket::STATUS_MASUK))],
                'kehadiran.*.pulang' => ['nullable', Rule::in(array_keys(AbsenPiket::STATUS_PULANG))],
            ], [
                'kehadiran.*.masuk.in' => 'Status absen masuk tidak valid.',
                'kehadiran.*.pulang.in' => 'Status absen pulang tidak valid.',
            ]);

            $validator->after(function ($validator): void {
                foreach ($this->kehadiran as $key => $row) {
                    if (filled($row['pulang'] ?? null) && blank($row['masuk'] ?? null)) {
                        $validator->errors()->add("kehadiran.$key.masuk", 'Isi absen masuk sebelum absen pulang.');
                    }
                }
            });

            $data = $validator->validate();

            if ($session === null) {
                $session = AbsenPiket::query()->create([
                    'kelas_id' => $class->id,
                    'guru_id' => $this->guruId(),
                    'pencatat_id' => Filament::auth()->id(),
                    'revisi' => 1,
                    'tanggal' => $this->tanggal,
                    'nama_guru' => Filament::auth()->user()?->name ?? 'Guru',
                    'nama_kelas' => $class->nama_kelas,
                    'dicatat_pada' => now(),
                ]);
            } else {
                $session->increment('revisi');
            }

            $session->murids()->delete();
            $now = now();
            $records = [];

            foreach ($roster as $key => $student) {
                $masuk = $data['kehadiran'][$key]['masuk'] ?? null;
                $pulang = $data['kehadiran'][$key]['pulang'] ?? null;

                if (blank($masuk) && blank($pulang)) {
                    continue;
                }

                $records[] = [
                    'absen_piket_id' => $session->id,
                    'murid_id' => $student['murid_id'],
                    'nama_murid' => $student['nama'],
                    'nis' => $student['nis'],
                    'masuk' => $masuk ?: null,
                    'pulang' => $pulang ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($records !== []) {
                AbsenPiketMurid::query()->insert($records);
            }
        }, attempts: 3);

        $this->loadClass($this->kelasId);
        $this->dispatch('close-modal', id: 'absensi-piket-modal');
        Notification::make()->success()->title('Absensi piket tersimpan')
            ->body('Absen masuk dan pulang tersimpan untuk kelas dan tanggal ini.')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $validDate = Validator::make(['tanggal' => $this->tanggal], $this->dateRules())->passes();
        $isEditable = $validDate && $this->isWithinEditWindow();
        $hasPiket = $validDate && $this->hasPiketOnDate();
        $rows = [];
        $sessions = collect();
        $class = null;
        $modalSession = null;
        $roster = [];

        if ($hasPiket) {
            $sessions = AbsenPiket::query()
                ->whereDate('tanggal', $this->tanggal)
                ->where('guru_id', $this->guruId())
                ->with('murids')
                ->get()
                ->keyBy('kelas_id');

            if ($isEditable) {
                $classes = Kelas::query()->orderBy('nama_kelas')->get();
                $studentCounts = Murid::query()
                    ->whereIn('kelas_id', $classes->pluck('id'))
                    ->selectRaw('kelas_id, count(*) as total')
                    ->groupBy('kelas_id')
                    ->pluck('total', 'kelas_id');

                foreach ($classes as $item) {
                    $session = $sessions->get($item->id);

                    $rows[] = $this->classRow(
                        $item->id,
                        $item->nama_kelas,
                        (int) ($studentCounts->get($item->id) ?? 0),
                        $session,
                    );
                }
            } else {
                foreach ($sessions->sortBy('nama_kelas') as $session) {
                    $rows[] = $this->classRow(
                        $session->kelas_id,
                        $session->nama_kelas,
                        $session->murids->count(),
                        $session,
                    );
                }
            }

            if ($this->kelasId !== null && $isEditable) {
                $class = Kelas::query()->find($this->kelasId);
                $session = $class !== null ? $this->sessionFor($class) : null;
                $roster = $class !== null ? $this->rosterFor($class, $session) : [];
            }

            if ($this->absenId !== null) {
                $modalSession = $sessions->firstWhere('id', $this->absenId);
            }
        }

        return [
            'today' => now()->toDateString(),
            'isEditable' => $isEditable,
            'hasPiket' => $hasPiket,
            'rows' => collect($rows),
            'kelasSelection' => $class,
            'roster' => $roster,
            'modalSession' => $modalSession,
            'statusMasuk' => AbsenPiket::STATUS_MASUK,
            'statusPulang' => AbsenPiket::STATUS_PULANG,
            'period' => JadwalPelajaran::activePeriod($validDate ? CarbonImmutable::parse($this->tanggal) : null),
        ];
    }

    /** @return array{key: string, kelas_id: int|null, absen_id: int|null, nama_kelas: string, student_count: int, filled_count: int, masuk_count: int, pulang_count: int} */
    private function classRow(?int $classId, string $className, int $studentCount, ?AbsenPiket $session): array
    {
        $records = $session?->murids ?? collect();

        return [
            'key' => $session !== null ? 'absen-'.$session->id : 'kelas-'.$classId,
            'kelas_id' => $classId,
            'absen_id' => $session?->id,
            'nama_kelas' => $className,
            'student_count' => $studentCount,
            'filled_count' => min($records->count(), $studentCount),
            'masuk_count' => $records->whereNotNull('masuk')->count(),
            'pulang_count' => $records->whereNotNull('pulang')->count(),
        ];
    }

    private function loadClass(int $id): void
    {
        $this->assertCanFill();
        $class = Kelas::query()->findOrFail($id);
        $session = $this->sessionFor($class);
        $this->kelasId = $class->id;
        $this->absenId = $session?->id;
        $this->revisi = $session?->revisi;
        $this->resetValidation();
        $this->kehadiran = [];

        foreach ($this->rosterFor($class, $session) as $key => $student) {
            $this->kehadiran[$key] = ['masuk' => $student['masuk'], 'pulang' => $student['pulang']];
        }
    }

    private function clearSelection(): void
    {
        $this->kelasId = null;
        $this->absenId = null;
        $this->revisi = null;
        $this->kehadiran = [];
    }

    private function sessionQuery(): Builder
    {
        return AbsenPiket::query()
            ->whereDate('tanggal', $this->tanggal)
            ->where('guru_id', $this->guruId());
    }

    private function sessionFor(Kelas $class, bool $lock = false): ?AbsenPiket
    {
        return AbsenPiket::query()
            ->where('kelas_id', $class->id)
            ->where('guru_id', $this->guruId())
            ->whereDate('tanggal', $this->tanggal)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with(['murids' => function (HasMany $relation) use ($lock): void {
                if ($lock) {
                    $relation->lockForUpdate();
                }
            }])
            ->first();
    }

    /** @return array<string, array{murid_id: ?int, nama: string, nis: string|null, masuk: string|null, pulang: string|null, row_id: int|null}> */
    private function rosterFor(Kelas $class, ?AbsenPiket $session, bool $lock = false): array
    {
        if ($session !== null) {
            return $session->murids->sortBy('nama_murid')->mapWithKeys(fn (AbsenPiketMurid $record): array => [
                'p'.$record->id => [
                    'murid_id' => $record->murid_id,
                    'nama' => $record->nama_murid,
                    'nis' => $record->nis,
                    'masuk' => $record->masuk,
                    'pulang' => $record->pulang,
                    'row_id' => $record->id,
                ],
            ])->all();
        }

        return Murid::query()->where('kelas_id', $class->id)->orderBy('nama')->orderBy('id')
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get(['id', 'nama', 'nis'])->mapWithKeys(fn (Murid $student): array => [
                'm'.$student->id => [
                    'murid_id' => $student->id,
                    'nama' => $student->nama,
                    'nis' => $student->nis,
                    'masuk' => null,
                    'pulang' => null,
                    'row_id' => null,
                ],
            ])->all();
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
            'tanggal.before_or_equal' => 'Tanggal absensi piket tidak boleh di masa depan.',
        ]);
    }

    private function isWithinEditWindow(): bool
    {
        $now = CarbonImmutable::now();

        return $this->tanggal >= $now->subDays(self::EDIT_WINDOW_DAYS)->toDateString()
            && $this->tanggal <= $now->toDateString();
    }

    private function hasPiketOnDate(): bool
    {
        $guruId = $this->guruId();

        if ($guruId === null) {
            return false;
        }

        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][CarbonImmutable::parse($this->tanggal)->dayOfWeek];

        return JadwalPiket::query()->where('guru_id', $guruId)->where('hari', $day)->exists();
    }

    private function guruId(): ?int
    {
        return Filament::auth()->user()?->guru()->value('id');
    }

    private function assertCanFill(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();

        if (! $this->isWithinEditWindow()) {
            throw ValidationException::withMessages(['tanggal' => 'Absensi piket hanya dapat diisi atau diperbaiki maksimal 7 hari ke belakang. Tanggal yang lebih lama hanya dapat dilihat.']);
        }

        abort_unless($this->hasPiketOnDate(), 403);
    }
}
