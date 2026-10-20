<?php

namespace App\Filament\Pages;

use App\Models\JadwalPelajaran;
use App\Models\JurnalMengajar as JurnalMengajarModel;
use App\Models\LingkupMateri;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use UnitEnum;

class JurnalMengajar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelajaran';

    protected static ?string $navigationLabel = 'Jurnal Mengajar';

    protected static ?string $title = 'Jurnal Mengajar Guru';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.jurnal-mengajar';

    public string $tanggal = '';

    #[Locked]
    public ?int $jadwalId = null;

    #[Locked]
    public ?int $jurnalId = null;

    #[Locked]
    public ?int $revisi = null;

    /** @var array<int, array{lingkup_materi_id: ?int, tujuan_pembelajaran_id: ?int, kegiatan: string, catatan: ?string, tindak_lanjut: ?string}> */
    public array $baris = [];

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
        $this->dispatch('close-modal', id: 'jurnal-mengajar-modal');
        $this->validateDate();
    }

    public function pilihJadwal(int $id): void
    {
        $this->loadJadwal($id);
        $this->dispatch('open-modal', id: 'jurnal-mengajar-modal');
    }

    public function lihatJurnal(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();
        $journal = $this->ownJournals()->findOrFail($id);
        $this->jadwalId = null;
        $this->jurnalId = $journal->id;
        $this->revisi = null;
        $this->baris = [];
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'jurnal-mengajar-modal');
    }

    public function tutupJurnal(): void
    {
        $this->clearSelection();
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'jurnal-mengajar-modal');
    }

    public function tambahBaris(): void
    {
        $this->assertSelectedSchedule();
        $this->baris[] = $this->emptyRow();
        $this->resetValidation();
    }

    public function hapusBaris(int $index): void
    {
        $this->assertSelectedSchedule();
        unset($this->baris[$index]);
        $this->baris = array_values($this->baris);

        if ($this->baris === []) {
            $this->baris = [$this->emptyRow()];
        }

        $this->resetValidation();
    }

    public function updatedBaris(mixed $value, ?string $key = null): void
    {
        $this->resetValidation();

        if ($key === null || ! str_ends_with($key, '.lingkup_materi_id')) {
            return;
        }

        $index = (int) str($key)->before('.')->toString();

        if (isset($this->baris[$index])) {
            $this->baris[$index]['tujuan_pembelajaran_id'] = null;
        }
    }

    public function simpan(): void
    {
        $this->assertToday();
        $this->validate(['jadwalId' => ['required', 'integer']], [], ['jadwalId' => 'jadwal mengajar']);

        DB::transaction(function (): void {
            $schedule = $this->todaySchedules()->lockForUpdate()->findOrFail($this->jadwalId);
            $journal = $this->journalFor($schedule, lock: true);
            $this->authorizeJournal($journal, $schedule);

            if ($journal?->revisi !== $this->revisi) {
                throw ValidationException::withMessages(['baris' => 'Jurnal telah diperbarui dari halaman lain. Pilih ulang jadwal sebelum menyimpan.']);
            }

            $materials = $this->availableMaterials((int) $schedule->mata_pelajaran);

            if ($materials->isEmpty()) {
                throw ValidationException::withMessages(['baris' => 'Belum ada lingkup materi untuk mata pelajaran ini. Isi dulu melalui menu Lingkup Materi dan Tujuan Pembelajaran.']);
            }

            $this->baris = array_values(array_map(function (mixed $row): array {
                if (! is_array($row)) {
                    return $this->emptyRow();
                }

                return [
                    'lingkup_materi_id' => filled($row['lingkup_materi_id'] ?? null) ? (int) $row['lingkup_materi_id'] : null,
                    'tujuan_pembelajaran_id' => filled($row['tujuan_pembelajaran_id'] ?? null) ? (int) $row['tujuan_pembelajaran_id'] : null,
                    'kegiatan' => is_scalar($row['kegiatan'] ?? null) ? trim((string) $row['kegiatan']) : $row['kegiatan'],
                    'catatan' => is_scalar($row['catatan'] ?? null) ? trim((string) $row['catatan']) : $row['catatan'],
                    'tindak_lanjut' => is_scalar($row['tindak_lanjut'] ?? null) ? trim((string) $row['tindak_lanjut']) : $row['tindak_lanjut'],
                ];
            }, $this->baris));

            $materialsById = $materials->keyBy('id');
            $validator = Validator::make(['baris' => $this->baris], [
                'baris' => ['required', 'array', 'min:1', 'max:50'],
                'baris.*.lingkup_materi_id' => ['required', 'integer'],
                'baris.*.tujuan_pembelajaran_id' => ['required', 'integer'],
                'baris.*.kegiatan' => ['required', 'string', 'max:2000'],
                'baris.*.catatan' => ['nullable', 'string', 'max:1000'],
                'baris.*.tindak_lanjut' => ['nullable', 'string', 'max:1000'],
            ], [
                'baris.min' => 'Tambahkan minimal satu baris jurnal.',
                'baris.max' => 'Jurnal maksimal 50 baris dalam sekali simpan.',
                'baris.*.lingkup_materi_id.required' => 'Pilih lingkup materi untuk setiap baris.',
                'baris.*.tujuan_pembelajaran_id.required' => 'Pilih tujuan pembelajaran untuk setiap baris.',
                'baris.*.kegiatan.required' => 'Kegiatan pembelajaran wajib diisi.',
                'baris.*.kegiatan.max' => 'Kegiatan pembelajaran maksimal 2000 karakter.',
                'baris.*.catatan.max' => 'Catatan maksimal 1000 karakter.',
                'baris.*.tindak_lanjut.max' => 'Tindak lanjut maksimal 1000 karakter.',
            ]);

            $validator->after(function ($validator) use ($materialsById): void {
                foreach ($this->baris as $index => $row) {
                    if (! $row['lingkup_materi_id']) {
                        continue;
                    }

                    $material = $materialsById->get($row['lingkup_materi_id']);

                    if ($material === null) {
                        $validator->errors()->add("baris.$index.lingkup_materi_id", 'Lingkup materi tidak tersedia untuk mata pelajaran dan periode ini.');

                        continue;
                    }

                    if (! $row['tujuan_pembelajaran_id'] || ! $material->tujuanPembelajarans->contains('id', $row['tujuan_pembelajaran_id'])) {
                        $validator->errors()->add("baris.$index.tujuan_pembelajaran_id", 'Tujuan pembelajaran tidak sesuai dengan lingkup materi yang dipilih.');
                    }
                }
            });

            $data = $validator->validate();

            if ($journal === null) {
                if ($schedule->mataPelajaran === null || $schedule->kelas === null) {
                    throw ValidationException::withMessages(['baris' => 'Data kelas atau mata pelajaran jadwal belum lengkap. Hubungi admin.']);
                }

                $journal = JurnalMengajarModel::query()->create([
                    'jadwal_pelajaran_id' => $schedule->id,
                    'guru_id' => $schedule->guru_id,
                    'kelas_id' => $schedule->kelas_id,
                    'mata_pelajaran_id' => (int) $schedule->mata_pelajaran,
                    'pencatat_id' => Filament::auth()->id(),
                    'revisi' => 1,
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
                $journal->increment('revisi');
            }

            $journal->baris()->delete();

            foreach ($data['baris'] as $index => $row) {
                $material = $materialsById->get($row['lingkup_materi_id']);
                $purpose = $material->tujuanPembelajarans->firstWhere('id', $row['tujuan_pembelajaran_id']);

                $journal->baris()->create([
                    'lingkup_materi_id' => $material->id,
                    'tujuan_pembelajaran_id' => $purpose->id,
                    'lingkup_materi' => $material->materi,
                    'tujuan_pembelajaran' => $purpose->teks,
                    'kegiatan' => $row['kegiatan'],
                    'catatan' => $row['catatan'] ?: null,
                    'tindak_lanjut' => $row['tindak_lanjut'] ?: null,
                    'urutan' => $index + 1,
                ]);
            }
        }, attempts: 3);

        $this->loadJadwal($this->jadwalId);
        $this->dispatch('close-modal', id: 'jurnal-mengajar-modal');
        Notification::make()->success()->title('Jurnal tersimpan')
            ->body('Jurnal mengajar tercatat satu kali untuk jadwal dan tanggal ini.')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $validDate = Validator::make(['tanggal' => $this->tanggal], $this->dateRules())->passes();
        $isToday = $validDate && $this->tanggal === now()->toDateString();
        $tanggalLabel = $validDate ? CarbonImmutable::parse($this->tanggal)->format('d/m/Y') : $this->tanggal;
        $period = JadwalPelajaran::activePeriod();
        $schedules = $isToday ? $this->todaySchedules()->get() : collect();
        $journals = $validDate ? $this->ownJournals()->get() : collect();
        $journalBySchedule = $journals->keyBy('jadwal_pelajaran_id');
        $rows = [];

        foreach ($schedules as $schedule) {
            $rows[] = $this->tableRow($schedule, $journalBySchedule->get($schedule->id));
        }

        foreach ($journals as $journal) {
            if (! $schedules->contains('id', $journal->jadwal_pelajaran_id)) {
                $rows[] = $this->tableRow(null, $journal);
            }
        }

        $schedule = $schedules->firstWhere('id', $this->jadwalId);
        $journal = $journals->firstWhere('id', $this->jurnalId);
        $canEdit = $schedule !== null && ($journal === null || $journal->pencatat_id === Filament::auth()->id());
        $materials = $canEdit ? $this->availableMaterials((int) $schedule->mata_pelajaran) : collect();

        return [
            'today' => now()->toDateString(),
            'isToday' => $isToday,
            'tanggalLabel' => $tanggalLabel,
            'period' => $period,
            'tableRows' => collect($rows)->sortBy('jam_mulai')->values(),
            'jadwal' => $canEdit ? $schedule : null,
            'jurnal' => $journal,
            'materials' => $materials,
            'tujuanOptions' => $materials->mapWithKeys(fn (LingkupMateri $material): array => [
                $material->id => $material->tujuanPembelajarans->pluck('teks', 'id')->all(),
            ])->all(),
            'lingkupUrl' => InputLingkupMateri::getUrl(),
        ];
    }

    /** @return array<string, mixed> */
    private function tableRow(?JadwalPelajaran $schedule, ?JurnalMengajarModel $journal): array
    {
        return [
            'key' => $schedule !== null ? 'jadwal-'.$schedule->id : 'jurnal-'.$journal->id,
            'jadwal_id' => $schedule?->id,
            'jurnal_id' => $journal?->id,
            'nama_kelas' => $journal?->nama_kelas ?? $schedule?->kelas?->nama_kelas ?? 'Kelas tidak tersedia',
            'nama_mapel' => $journal?->nama_mapel ?? $schedule?->mataPelajaran?->nama_mapel ?? 'Mata pelajaran tidak tersedia',
            'nama_guru' => $journal?->nama_guru ?? $schedule?->guru?->user?->name ?? 'Guru tidak tersedia',
            'jam_mulai' => $journal?->jam_mulai ?? $schedule?->jam_mulai,
            'jam_selesai' => $journal?->jam_selesai ?? $schedule?->jam_selesai,
            'jumlah_baris' => $journal?->baris->count() ?? 0,
            'dicatat_pada' => $journal?->dicatat_pada,
        ];
    }

    private function todaySchedules(): Builder
    {
        abort_unless(static::canAccess(), 403);
        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][now()->dayOfWeek];
        $period = JadwalPelajaran::activePeriod();

        return JadwalPelajaran::query()
            ->whereHas('guru', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()))
            ->forPeriod($period['semester'], $period['tahun_ajaran'])
            ->where('hari', $day)
            ->with(['kelas', 'mataPelajaran', 'guru.user'])
            ->orderBy('jam_mulai')->orderBy('id');
    }

    private function ownJournals(): Builder
    {
        return JurnalMengajarModel::query()->where('pencatat_id', Filament::auth()->id())
            ->whereDate('tanggal', $this->tanggal)->with('baris')
            ->orderBy('jam_mulai')->orderBy('id');
    }

    private function journalFor(JadwalPelajaran $schedule, bool $lock = false): ?JurnalMengajarModel
    {
        return JurnalMengajarModel::query()->where('jadwal_pelajaran_id', $schedule->id)
            ->whereDate('tanggal', $this->tanggal)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with('baris')->first();
    }

    private function availableMaterials(int $subjectId): Collection
    {
        $guruId = Filament::auth()->user()?->guru()->value('id');
        $period = JadwalPelajaran::activePeriod();

        return LingkupMateri::query()
            ->where('guru_id', $guruId)
            ->where('mata_pelajaran_id', $subjectId)
            ->where('semester', $period['semester'])
            ->where('tahun_ajaran', $period['tahun_ajaran'])
            ->whereHas('tujuanPembelajarans')
            ->with('tujuanPembelajarans')
            ->orderBy('urutan')
            ->get();
    }

    private function authorizeJournal(?JurnalMengajarModel $journal, JadwalPelajaran $schedule): void
    {
        abort_if($journal !== null && (
            $journal->guru_id !== $schedule->guru_id || $journal->pencatat_id !== Filament::auth()->id()
        ), 403);
    }

    private function loadJadwal(int $id): void
    {
        $this->assertToday();
        $schedule = $this->todaySchedules()->findOrFail($id);
        $journal = $this->journalFor($schedule);
        $this->authorizeJournal($journal, $schedule);
        $this->jadwalId = $schedule->id;
        $this->jurnalId = $journal?->id;
        $this->revisi = $journal?->revisi;
        $this->baris = [];
        $this->resetValidation();

        if ($journal !== null) {
            foreach ($journal->baris as $row) {
                $this->baris[] = [
                    'lingkup_materi_id' => $row->lingkup_materi_id,
                    'tujuan_pembelajaran_id' => $row->tujuan_pembelajaran_id,
                    'kegiatan' => $row->kegiatan,
                    'catatan' => $row->catatan,
                    'tindak_lanjut' => $row->tindak_lanjut,
                ];
            }
        }

        if ($this->baris === []) {
            $this->baris = [$this->emptyRow()];
        }
    }

    private function assertSelectedSchedule(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($this->jadwalId === null, 403);
    }

    /** @return array{lingkup_materi_id: null, tujuan_pembelajaran_id: null, kegiatan: string, catatan: string, tindak_lanjut: string} */
    private function emptyRow(): array
    {
        return [
            'lingkup_materi_id' => null,
            'tujuan_pembelajaran_id' => null,
            'kegiatan' => '',
            'catatan' => '',
            'tindak_lanjut' => '',
        ];
    }

    private function clearSelection(): void
    {
        $this->jadwalId = null;
        $this->jurnalId = null;
        $this->revisi = null;
        $this->baris = [];
    }

    /** @return array{tanggal: list<string>} */
    private function dateRules(): array
    {
        return ['tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()]];
    }

    private function validateDate(): void
    {
        $this->validate($this->dateRules(), ['tanggal.before_or_equal' => 'Tanggal jurnal tidak boleh di masa depan.']);
    }

    private function assertToday(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validateDate();

        if ($this->tanggal !== CarbonImmutable::now()->toDateString()) {
            throw ValidationException::withMessages(['tanggal' => 'Jurnal hanya dapat diisi atau diperbaiki pada hari yang sama. Riwayat tanggal sebelumnya hanya dapat dilihat.']);
        }
    }
}
