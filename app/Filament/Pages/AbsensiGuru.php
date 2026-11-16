<?php

namespace App\Filament\Pages;

use App\Models\AbsenGuru;
use App\Models\Guru;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Absensi harian guru dengan tombol masuk dan pulang.
 *
 * Satu guru hanya memiliki satu catatan per tanggal. Catatan ini dipakai
 * sebagai hari hadir pada perhitungan uang transport di menu Gaji Guru.
 */
class AbsensiGuru extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Absensi';

    protected static ?string $navigationLabel = 'Absensi Guru';

    protected static ?string $title = 'Absensi Guru';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.absensi-guru';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && $user->guru()->exists() && $user->can('View:AbsensiGuru');
    }

    public function masuk(): void
    {
        $guru = $this->currentGuru();
        abort_if($guru === null, 403);

        if (($absen = $this->todayAbsen($guru)) !== null) {
            $this->rejectAlreadyClockedIn($absen);
        }

        try {
            $absen = AbsenGuru::query()->create([
                'guru_id' => $guru->id,
                'user_id' => Filament::auth()->id(),
                'nama_guru' => $guru->nama,
                'tanggal' => CarbonImmutable::now()->toDateString(),
                'masuk_pada' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Tombol terkirim dua kali hampir bersamaan; catatan pertama yang dipakai.
            $this->rejectAlreadyClockedIn($this->todayAbsen($guru));
        }

        Notification::make()->success()
            ->title('Absen masuk tersimpan')
            ->body('Tercatat pukul '.$absen->masuk_pada?->format('H:i').'.')
            ->send();
    }

    public function pulang(): void
    {
        $guru = $this->currentGuru();
        abort_if($guru === null, 403);

        $absen = $this->todayAbsen($guru);

        if ($absen === null || $absen->masuk_pada === null) {
            throw ValidationException::withMessages([
                'pulang' => 'Absen masuk terlebih dahulu sebelum absen pulang.',
            ]);
        }

        if ($absen->pulang_pada !== null) {
            throw ValidationException::withMessages([
                'pulang' => 'Anda sudah absen pulang hari ini pukul '.$absen->pulang_pada->format('H:i').'.',
            ]);
        }

        $absen->forceFill(['pulang_pada' => now()])->save();

        Notification::make()->success()
            ->title('Absen pulang tersimpan')
            ->body('Tercatat pukul '.$absen->pulang_pada?->format('H:i').'.')
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $guru = $this->currentGuru();
        abort_if($guru === null, 403);

        $now = CarbonImmutable::now();

        return [
            'today' => $now->locale('id')->translatedFormat('l, d F Y'),
            'sekarang' => $now->format('H:i:s'),
            'absen' => $this->todayAbsen($guru),
            'riwayat' => $this->historyRows($guru, $now),
            'guruNama' => $guru->nama,
        ];
    }

    private function todayAbsen(Guru $guru): ?AbsenGuru
    {
        return AbsenGuru::query()
            ->where('guru_id', $guru->id)
            ->whereDate('tanggal', CarbonImmutable::now()->toDateString())
            ->first();
    }

    private function rejectAlreadyClockedIn(?AbsenGuru $absen): never
    {
        throw ValidationException::withMessages([
            'masuk' => 'Anda sudah absen masuk hari ini pukul '.($absen?->masuk_pada?->format('H:i') ?? '-').'.',
        ]);
    }

    /**
     * Riwayat absensi tujuh hari terakhir dalam bentuk baris siap tampil.
     *
     * @return list<array{key: int, tanggal: string, hari: string, masuk: string, pulang: string, keterangan: string}>
     */
    private function historyRows(Guru $guru, CarbonImmutable $now): array
    {
        return AbsenGuru::query()
            ->where('guru_id', $guru->id)
            ->where('tanggal', '>=', $now->subDays(6)->toDateString())
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (AbsenGuru $row): array => [
                'key' => $row->id,
                'tanggal' => $row->tanggal->locale('id')->translatedFormat('d M Y'),
                'hari' => $row->tanggal->locale('id')->translatedFormat('l'),
                'masuk' => $row->masuk_pada?->format('H:i') ?? '-',
                'pulang' => $row->pulang_pada?->format('H:i') ?? '-',
                'keterangan' => match (true) {
                    $row->masuk_pada === null => 'Belum masuk',
                    $row->pulang_pada === null => 'Belum pulang',
                    default => 'Lengkap',
                },
            ])
            ->all();
    }

    private function currentGuru(): ?Guru
    {
        return Filament::auth()->user()?->guru()->first();
    }
}
