<?php

namespace App\Filament\Pages;

use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\JadwalRapat;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use UnitEnum;

class AbsensiRapat extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Absensi Rapat';

    protected static ?string $title = 'Absensi Rapat';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.absensi-rapat';

    #[Locked]
    public ?int $rapatId = null;

    public $foto = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function pilihRapat(int $id): void
    {
        $this->assertMember($id);
        $this->rapatId = $id;
        $this->foto = null;
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'absensi-rapat-modal');
    }

    public function tutupRapat(): void
    {
        $this->rapatId = null;
        $this->foto = null;
        $this->resetValidation();
        $this->dispatch('rapat-camera-stop');
        $this->dispatch('close-modal', id: 'absensi-rapat-modal');
    }

    public function absen(): void
    {
        $this->validate(['rapatId' => ['required', 'integer']], [], ['rapatId' => 'rapat']);
        $this->assertMember((int) $this->rapatId);
        $meeting = $this->todayMeetings()->findOrFail($this->rapatId);
        $guru = $this->currentGuru();
        abort_if($guru === null, 403);

        if (AbsenRapat::query()->where('jadwal_rapat_id', $meeting->id)->where('guru_id', $guru->id)->exists()) {
            throw ValidationException::withMessages(['rapatId' => 'Anda sudah tercatat pada rapat ini. Absensi rapat hanya dapat dilakukan satu kali.']);
        }

        $this->validate([
            'foto' => ['required', 'image', 'max:5120'],
        ], [
            'foto.required' => 'Ambil selfie terlebih dahulu.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max' => 'Foto maksimal 5 MB.',
        ]);

        $path = $this->foto->store(
            'absen-rapat/'.CarbonImmutable::parse($meeting->tanggal)->format('Y-m-d'),
            AbsenRapat::FOTO_DISK,
        );

        AbsenRapat::query()->create([
            'jadwal_rapat_id' => $meeting->id,
            'guru_id' => $guru->id,
            'user_id' => Filament::auth()->id(),
            'nama_guru' => $guru->user?->name ?? Filament::auth()->user()?->name ?? 'Guru',
            'agenda' => $meeting->agenda,
            'tanggal' => $meeting->tanggal,
            'jam_mulai' => $meeting->jam_mulai,
            'jam_selesai' => $meeting->jam_selesai,
            'foto' => $path,
            'dicatat_pada' => now(),
        ]);

        $this->tutupRapat();
        Notification::make()->success()->title('Absensi rapat tersimpan')
            ->body('Selfie absensi tersimpan untuk rapat ini.')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $meetings = $this->todayMeetings()->get();
        $guruId = $this->currentGuru()?->id;
        $attendance = $guruId === null
            ? collect()
            : AbsenRapat::query()
                ->where('guru_id', $guruId)
                ->whereIn('jadwal_rapat_id', $meetings->pluck('id'))
                ->get()
                ->keyBy('jadwal_rapat_id');

        $rows = $meetings->map(fn (JadwalRapat $meeting): array => [
            'key' => 'rapat-'.$meeting->id,
            'rapat_id' => $meeting->id,
            'agenda' => $meeting->agenda,
            'jam_mulai' => $meeting->jam_mulai,
            'jam_selesai' => $meeting->jam_selesai,
            'absen' => $attendance->get($meeting->id),
        ]);

        return [
            'today' => CarbonImmutable::now()->locale('id')->translatedFormat('l, d F Y'),
            'rows' => $rows,
            'selected' => $meetings->firstWhere('id', $this->rapatId),
            'guruNama' => $this->currentGuru()?->nama,
        ];
    }

    private function todayMeetings(): Builder
    {
        $guruId = Filament::auth()->user()?->guru()->value('id');

        return JadwalRapat::query()
            ->whereDate('tanggal', CarbonImmutable::now()->toDateString())
            ->whereHas('gurus', fn (Builder $query): Builder => $query->where('gurus.id', $guruId))
            ->orderBy('jam_mulai')->orderBy('id');
    }

    private function assertMember(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->todayMeetings()->whereKey($id)->exists(), 404);
    }

    private function currentGuru(): ?Guru
    {
        return Filament::auth()->user()?->guru()->first();
    }
}
