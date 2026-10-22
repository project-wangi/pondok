<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Donasis\DonasiResource;
use App\Models\Donasi;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JadwalRapat;
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
        if (! static::isTeacherDashboard()) {
            return parent::content($schema);
        }

        return $schema->components([
            Html::make(fn (): Htmlable => view('filament.pages.dashboard-guru', $this->teacherDashboardData())),
        ]);
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
}
