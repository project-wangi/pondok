<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Donasis\DonasiResource;
use App\Filament\Resources\Gurus\GuruResource;
use App\Filament\Resources\JadwalPelajarans\JadwalPelajaranResource;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\Murids\MuridResource;
use App\Models\AbsenPiket;
use App\Models\Donasi;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JadwalRapat;
use App\Models\Kelas;
use App\Models\Murid;
use App\Models\PresensiMurid;
use App\Models\SesiMengajar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    public function content(Schema $schema): Schema
    {
        if (static::isTeacherDashboard()) {
            return $schema->components([
                Html::make(fn (): Htmlable => view('filament.pages.dashboard-guru', $this->teacherDashboardData())),
            ]);
        }

        if (static::isAdminDashboard()) {
            return $schema->components([
                Html::make(fn (): Htmlable => view('filament.pages.dashboard-admin', $this->adminDashboardData())),
            ]);
        }

        return parent::content($schema);
    }

    protected static function isTeacherDashboard(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->guru()->exists()) {
            return false;
        }

        return ! $user->hasAnyRole(['super_admin', 'admin', 'panel_user']);
    }

    /** @return array<string, mixed> */
    protected function teacherDashboardData(): array
    {
        $user = Filament::auth()->user();
        $guru = $user?->guru()->with('user')->first();
        $today = CarbonImmutable::now();
        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$today->dayOfWeek];
        $period = JadwalPelajaran::activePeriod($today);

        $schedules = $guru === null
            ? collect()
            : JadwalPelajaran::query()
                ->where('guru_id', $guru->id)
                ->forPeriod($period['semester'], $period['tahun_ajaran'])
                ->where('hari', $day)
                ->with(['kelas', 'mataPelajaran'])
                ->orderBy('jam_mulai')
                ->orderBy('id')
                ->get();

        $sessions = $schedules->isEmpty()
            ? collect()
            : SesiMengajar::query()
                ->where('pencatat_id', $user?->id)
                ->whereDate('tanggal', $today->toDateString())
                ->whereIn('jadwal_pelajaran_id', $schedules->pluck('id'))
                ->get()
                ->keyBy('jadwal_pelajaran_id');

        $piket = $guru === null
            ? null
            : JadwalPiket::query()->where('guru_id', $guru->id)->where('hari', $day)->first();

        $rapats = $guru === null
            ? collect()
            : JadwalRapat::query()
                ->whereDate('tanggal', $today->toDateString())
                ->whereHas('gurus', fn (Builder $query): Builder => $query->where('gurus.id', $guru->id))
                ->orderBy('jam_mulai')
                ->orderBy('id')
                ->get();

        $donasi = $guru === null
            ? null
            : Donasi::query()->where('guru_id', $guru->id)->first();

        $hour = (int) $today->format('H');

        return [
            'guruNama' => $guru?->user?->name ?? $user?->name ?? 'Guru',
            'greeting' => match (true) {
                $hour < 11 => 'Selamat pagi',
                $hour < 15 => 'Selamat siang',
                $hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'tanggalLabel' => $today->locale('id')->translatedFormat('l, d F Y'),
            'semester' => $period['semester'],
            'tahunAjaran' => $period['tahun_ajaran'],
            'schedules' => $schedules,
            'sessions' => $sessions,
            'scheduleCount' => $schedules->count(),
            'recordedCount' => $sessions->count(),
            'piket' => $piket,
            'rapats' => $rapats,
            'donasi' => $donasi,
            'links' => [
                'presensi' => PresensiSiswa::getUrl(),
                'jurnal' => JurnalMengajar::getUrl(),
                'piket' => AbsensiPiket::getUrl(),
                'rapat' => AbsensiRapat::getUrl(),
                'peminatan' => AbsensiPeminatan::getUrl(),
                'lingkup' => InputLingkupMateri::getUrl(),
                'asesmen' => InputAsesmenFormatif::getUrl(),
                'sikap' => InputSikapKeaktifan::getUrl(),
                'donasi' => DonasiResource::getUrl(),
            ],
        ];
    }

    protected static function isAdminDashboard(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole(['admin', config('filament-shield.super_admin.name', 'super_admin')])
            || $user->can('ViewAny:Jabatan');
    }

    /** @return array<string, mixed> */
    protected function adminDashboardData(): array
    {
        $user = Filament::auth()->user();
        $today = CarbonImmutable::now();
        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$today->dayOfWeek];
        $period = JadwalPelajaran::activePeriod($today);

        $schedules = JadwalPelajaran::query()
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('hari', $day)
            ->with(['kelas', 'mataPelajaran', 'guru.user'])
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        $sessions = $schedules->isEmpty()
            ? collect()
            : SesiMengajar::query()
                ->whereDate('tanggal', $today->toDateString())
                ->whereIn('jadwal_pelajaran_id', $schedules->pluck('id'))
                ->withCount('presensiMurids')
                ->get()
                ->keyBy('jadwal_pelajaran_id');

        $attendance = $sessions->isEmpty()
            ? collect()
            : PresensiMurid::query()
                ->whereIn('sesi_mengajar_id', $sessions->pluck('id'))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

        $attendanceCounts = [
            'Hadir' => (int) ($attendance['Hadir'] ?? 0),
            'Izin' => (int) ($attendance['Izin'] ?? 0),
            'Sakit' => (int) ($attendance['Sakit'] ?? 0),
            'Alpa' => (int) ($attendance['Alpa'] ?? 0),
        ];
        $attendanceTotal = array_sum($attendanceCounts);

        $piket = JadwalPiket::query()
            ->where('hari', $day)
            ->with('guru.user')
            ->get();

        $piketRecorded = $piket->isEmpty()
            ? collect()
            : AbsenPiket::query()
                ->whereDate('tanggal', $today->toDateString())
                ->whereIn('guru_id', $piket->pluck('guru_id'))
                ->distinct()
                ->pluck('guru_id')
                ->flip();

        $teachers = Guru::query()->with('user:id,name')->get();

        $salaryTotal = $teachers->sum(
            fn (Guru $guru): int => (int) GajiGuru::rincianGuru($guru, $today->month, $today->year)['total'],
        );

        $meetings = JadwalRapat::query()
            ->whereDate('tanggal', '>=', $today->toDateString())
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->limit(3)
            ->get()
            ->map(fn (JadwalRapat $rapat): array => [
                'tanggal' => CarbonImmutable::parse((string) $rapat->tanggal)->locale('id')->translatedFormat('d M Y'),
                'jam' => substr((string) $rapat->jam_mulai, 0, 5),
                'agenda' => $rapat->agenda,
            ])
            ->all();

        $hour = (int) $today->format('H');

        return [
            'nama' => $user?->name ?? 'Admin',
            'greeting' => match (true) {
                $hour < 11 => 'Selamat pagi',
                $hour < 15 => 'Selamat siang',
                $hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'tanggalLabel' => $today->locale('id')->translatedFormat('l, d F Y'),
            'bulanLabel' => $today->locale('id')->translatedFormat('F Y'),
            'semester' => $period['semester'],
            'tahunAjaran' => $period['tahun_ajaran'],
            'hari' => $day,
            'schedules' => $schedules,
            'sessions' => $sessions,
            'scheduleCount' => $schedules->count(),
            'recordedCount' => $sessions->filter(fn (SesiMengajar $session): bool => $session->presensi_murids_count > 0)->count(),
            'attendanceCounts' => $attendanceCounts,
            'attendanceTotal' => $attendanceTotal,
            'attendancePercent' => $attendanceTotal > 0
                ? rtrim(rtrim(number_format($attendanceCounts['Hadir'] / $attendanceTotal * 100, 1, ',', '.'), '0'), ',')
                : null,
            'piket' => $piket,
            'piketRecorded' => $piketRecorded,
            'salaryTotal' => (int) $salaryTotal,
            'teacherCount' => $teachers->count(),
            'counts' => [
                'guru' => $teachers->count(),
                'kelas' => Kelas::query()->count(),
                'murid' => Murid::query()->count(),
            ],
            'meetings' => $meetings,
            'links' => [
                'gaji' => GajiGuru::getUrl(),
                'rekap' => RekapMengajarGuru::getUrl(),
                'jadwal' => JadwalPelajaranResource::getUrl('index'),
                'kelas' => KelasResource::getUrl('index'),
                'guru' => GuruResource::getUrl('index'),
                'murid' => MuridResource::getUrl('index'),
            ],
        ];
    }
}
