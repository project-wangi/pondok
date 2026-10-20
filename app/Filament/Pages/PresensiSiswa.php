<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\Murid;
use App\Models\PresensiMurid;
use App\Models\SesiMengajar;
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

class PresensiSiswa extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Presensi Siswa';

    protected static ?string $title = 'Presensi Siswa';

    protected string $view = 'filament.pages.presensi-siswa';

    public string $tanggal = '';

    #[Locked]
    public ?int $jadwalId = null;

    #[Locked]
    public ?int $sessionRevision = null;

    #[Locked]
    public ?int $riwayatId = null;

    /** @var array<string, array{status: string, catatan: ?string}> */
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
        $this->jadwalId = null;
        $this->sessionRevision = null;
        $this->riwayatId = null;
        $this->kehadiran = [];
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'presensi-siswa-modal');
        $this->validateDate();
    }

    public function pilihJadwal(int $id): void
    {
        $this->loadSchedule($id);
        $this->dispatch('open-modal', id: 'presensi-siswa-modal');
    }

    public function lihatPresensi(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();
        $session = $this->ownSessions()->findOrFail($id);
        $this->jadwalId = null;
        $this->sessionRevision = null;
        $this->kehadiran = [];
        $this->riwayatId = $session->id;
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'presensi-siswa-modal');
    }

    public function tutupPresensi(): void
    {
        $this->jadwalId = null;
        $this->riwayatId = null;
        $this->sessionRevision = null;
        $this->kehadiran = [];
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'presensi-siswa-modal');
    }

    private function loadSchedule(int $id): void
    {
        $this->assertToday();
        $schedule = $this->todaySchedules()->findOrFail($id);
        $session = $this->sessionFor($schedule);
        $this->authorizeSession($session, $schedule);
        $this->sessionRevision = $session?->revisi;
        $this->riwayatId = null;
        $this->jadwalId = $schedule->id;
        $this->resetValidation();
        $this->kehadiran = [];

        foreach ($this->rosterFor($schedule, $session) as $key => $student) {
            $this->kehadiran[$key] = ['status' => $student['status'], 'catatan' => $student['catatan']];
        }
    }

    public function tandaiSemuaHadir(): void
    {
        $this->assertToday();
        $schedule = $this->todaySchedules()->findOrFail($this->jadwalId);
        $session = $this->sessionFor($schedule);
        $this->authorizeSession($session, $schedule);

        foreach ($this->rosterFor($schedule, $session) as $key => $student) {
            $this->kehadiran[$key] = [
                'status' => 'Hadir',
                'catatan' => $this->kehadiran[$key]['catatan'] ?? $student['catatan'],
            ];
        }
    }

    public function simpan(): void
    {
        $this->assertToday();
        $this->validate(['jadwalId' => ['required', 'integer']], [], ['jadwalId' => 'jadwal mengajar']);

        DB::transaction(function (): void {
            // Serialize submissions for this schedule; the unique key also guards duplicate sessions.
            $schedule = $this->todaySchedules()->lockForUpdate()->findOrFail($this->jadwalId);
            $session = $this->sessionFor($schedule, lock: true);
            $this->authorizeSession($session, $schedule);

            if ($session?->revisi !== $this->sessionRevision) {
                throw ValidationException::withMessages(['kehadiran' => 'Presensi telah diperbarui dari halaman lain. Pilih ulang jadwal sebelum menyimpan.']);
            }

            $roster = $this->rosterFor($schedule, $session, lock: true);

            if ($roster === []) {
                throw ValidationException::withMessages(['kehadiran' => 'Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas siswa.']);
            }

            $expectedKeys = array_keys($roster);
            $actualKeys = array_keys($this->kehadiran);
            sort($expectedKeys);
            sort($actualKeys);

            if ($expectedKeys !== $actualKeys) {
                throw ValidationException::withMessages(['kehadiran' => 'Daftar siswa atau presensi telah berubah. Pilih ulang jadwal sebelum menyimpan.']);
            }

            $data = $this->validate([
                'kehadiran' => ['required', 'array'],
                'kehadiran.*' => ['required', 'array:status,catatan'],
                'kehadiran.*.status' => ['required', Rule::in(array_keys(PresensiMurid::STATUSES))],
                'kehadiran.*.catatan' => ['nullable', 'string', 'max:500'],
            ], [
                'kehadiran.*.status.required' => 'Pilih status kehadiran untuk setiap siswa.',
                'kehadiran.*.status.in' => 'Status kehadiran tidak valid.',
                'kehadiran.*.catatan.max' => 'Catatan maksimal 500 karakter.',
            ]);

            if ($session === null) {
                if ($schedule->mataPelajaran === null || $schedule->kelas === null) {
                    throw ValidationException::withMessages(['jadwalId' => 'Data kelas atau mata pelajaran jadwal belum lengkap. Hubungi admin.']);
                }

                $session = SesiMengajar::query()->create([
                    'jadwal_pelajaran_id' => $schedule->id,
                    'guru_id' => $schedule->guru_id,
                    'pencatat_id' => Filament::auth()->id(),
                    'kelas_id' => $schedule->kelas_id,
                    'tanggal' => $this->tanggal,
                    'semester' => $schedule->semester,
                    'tahun_ajaran' => $schedule->tahun_ajaran,
                    'nama_guru' => $schedule->guru->user->name,
                    'nama_kelas' => $schedule->kelas->nama_kelas,
                    'nama_mapel' => $schedule->mataPelajaran->nama_mapel,
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'dicatat_pada' => now(),
                ]);
            } else {
                $session->increment('revisi');
            }

            foreach ($roster as $key => $student) {
                $values = [
                    'status' => $data['kehadiran'][$key]['status'],
                    'catatan' => $data['kehadiran'][$key]['catatan'] ?? null,
                ];

                if ($student['presensi_id'] !== null) {
                    $session->presensiMurids()->whereKey($student['presensi_id'])->update($values);
                } else {
                    $session->presensiMurids()->create([
                        ...$values,
                        'murid_id' => $student['murid_id'],
                        'nama_murid' => $student['nama'],
                        'nis' => $student['nis'],
                    ]);
                }
            }
        }, attempts: 3);

        $this->loadSchedule($this->jadwalId);
        $this->dispatch('close-modal', id: 'presensi-siswa-modal');
        Notification::make()->success()->title('Presensi tersimpan')
            ->body('Sesi mengajar tercatat satu kali untuk jadwal dan tanggal ini.')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $validDate = Validator::make(['tanggal' => $this->tanggal], $this->dateRules())->passes();
        $isToday = $validDate && $this->tanggal === now()->toDateString();
        $schedules = $isToday ? $this->todaySchedules()->get() : collect();
        $schedule = $schedules->firstWhere('id', $this->jadwalId);
        $session = $schedule !== null ? $this->sessionFor($schedule) : null;
        $canEditSession = $session === null || ($session->guru_id === $schedule?->guru_id && $session->pencatat_id === Filament::auth()->id());

        $activePeriod = JadwalPelajaran::activePeriod();
        $sessions = $validDate ? $this->ownSessions()->get() : collect();
        $modalSession = $sessions->firstWhere('id', $this->riwayatId);
        $tableRows = [];

        foreach ($schedules as $item) {
            $saved = $sessions->firstWhere('jadwal_pelajaran_id', $item->id);
            $tableRows[] = $this->tableRow($item, $saved);
        }

        foreach ($sessions as $saved) {
            if (! $schedules->contains('id', $saved->jadwal_pelajaran_id)) {
                $tableRows[] = $this->tableRow(null, $saved);
            }
        }

        return [
            'activePeriod' => $activePeriod,
            'modalSession' => $modalSession,
            'tableRows' => collect($tableRows)->sortBy('jam_mulai')->values(),
            'today' => now()->toDateString(),
            'isToday' => $isToday,
            'statuses' => PresensiMurid::STATUSES,
            'jadwals' => $schedules,
            'jadwal' => $canEditSession ? $schedule : null,
            'roster' => $schedule !== null && $canEditSession ? $this->rosterFor($schedule, $session) : [],
            'sessions' => $sessions,
        ];
    }

    /** @return array<string, mixed> */
    private function tableRow(?JadwalPelajaran $schedule, ?SesiMengajar $session): array
    {
        $total = $session?->presensiMurids->count() ?? 0;
        $present = $session?->presensiMurids->where('status', 'Hadir')->count() ?? 0;

        return [
            'key' => $schedule !== null ? 'jadwal-'.$schedule->id : 'sesi-'.$session->id,
            'jadwal_id' => $schedule?->id,
            'sesi_id' => $session?->id,
            'nama_kelas' => $session?->nama_kelas ?? $schedule?->kelas?->nama_kelas ?? 'Kelas tidak tersedia',
            'nama_mapel' => $session?->nama_mapel ?? $schedule?->mataPelajaran?->nama_mapel ?? 'Mata pelajaran tidak tersedia',
            'nama_guru' => $session?->nama_guru ?? $schedule?->guru?->user?->name ?? 'Guru tidak tersedia',
            'jam_mulai' => $session?->jam_mulai ?? $schedule?->jam_mulai,
            'jam_selesai' => $session?->jam_selesai ?? $schedule?->jam_selesai,
            'hadir' => $session !== null ? $present : null,
            'total' => $total,
            'persentase' => $session !== null && $total > 0 ? round($present / $total * 100, 2) : null,
            'dicatat_pada' => $session?->dicatat_pada,
        ];
    }

    private function ownSessions(): Builder
    {
        // History stays readable across periods; only new attendance uses the running period.
        return SesiMengajar::query()->where('pencatat_id', Filament::auth()->id())
            ->whereDate('tanggal', $this->tanggal)->with('presensiMurids')
            ->orderBy('jam_mulai')->orderBy('id');
    }

    private function todaySchedules(): Builder
    {
        abort_unless(static::canAccess(), 403);
        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][now()->dayOfWeek];
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->whereHas('guru', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()))
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('hari', $day)->with(['kelas', 'mataPelajaran', 'guru.user'])
            ->orderBy('jam_mulai')->orderBy('id');
    }

    private function sessionFor(JadwalPelajaran $schedule, bool $lock = false): ?SesiMengajar
    {
        // Locking reads see the latest commit even under MySQL REPEATABLE READ.
        return SesiMengajar::query()->where('jadwal_pelajaran_id', $schedule->id)
            ->whereDate('tanggal', $this->tanggal)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with(['presensiMurids' => function (HasMany $relation) use ($lock): void {
                if ($lock) {
                    $relation->lockForUpdate();
                }
            }])
            ->first();
    }

    private function authorizeSession(?SesiMengajar $session, JadwalPelajaran $schedule): void
    {
        abort_if($session !== null && (
            $session->guru_id !== $schedule->guru_id || $session->pencatat_id !== Filament::auth()->id()
        ), 403);
    }

    /** @return array<string, array{murid_id: ?int, nama: string, nis: ?string, status: string, catatan: ?string, presensi_id: ?int}> */
    private function rosterFor(JadwalPelajaran $schedule, ?SesiMengajar $session, bool $lock = false): array
    {
        if ($session !== null) {
            return $session->presensiMurids->sortBy('nama_murid')->mapWithKeys(fn (PresensiMurid $attendance): array => [
                'p'.$attendance->id => [
                    'murid_id' => $attendance->murid_id,
                    'nama' => $attendance->nama_murid,
                    'nis' => $attendance->nis,
                    'status' => $attendance->status,
                    'catatan' => $attendance->catatan,
                    'presensi_id' => $attendance->id,
                ],
            ])->all();
        }

        return Murid::query()->where('kelas_id', $schedule->kelas_id)->orderBy('nama')->orderBy('id')
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get(['id', 'nama', 'nis'])->mapWithKeys(fn (Murid $student): array => [
                'm'.$student->id => [
                    'murid_id' => $student->id, 'nama' => $student->nama, 'nis' => $student->nis,
                    'status' => '', 'catatan' => '', 'presensi_id' => null,
                ],
            ])->all();
    }

    /** @return array{tanggal: list<string>} */
    private function dateRules(): array
    {
        return ['tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()]];
    }

    private function validateDate(): void
    {
        $this->validate($this->dateRules(), ['tanggal.before_or_equal' => 'Tanggal presensi tidak boleh di masa depan.']);
    }

    private function assertToday(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();

        if ($this->tanggal !== CarbonImmutable::now()->toDateString()) {
            throw ValidationException::withMessages(['tanggal' => 'Presensi hanya dapat diisi atau diperbaiki pada hari yang sama. Riwayat tanggal sebelumnya hanya dapat dilihat.']);
        }
    }
}
