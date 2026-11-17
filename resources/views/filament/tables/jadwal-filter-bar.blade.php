@php
    use App\Models\JadwalPelajaran;
    use App\Models\Kelas;

    $table = \Livewire\Livewire::current();
    $filters = is_array(data_get($table, 'tableFilters')) ? data_get($table, 'tableFilters') : [];
    $semester = data_get($filters, 'semester.value');
    $tahunAjaran = data_get($filters, 'tahun_ajaran.value');
    $tahunOptions = JadwalPelajaran::query()
        ->whereNotNull('tahun_ajaran')
        ->distinct()
        ->orderByDesc('tahun_ajaran')
        ->pluck('tahun_ajaran')
        ->push($tahunAjaran)
        ->filter()
        ->unique()
        ->sortDesc()
        ->values()
        ->all();
    $kelasOptions = Kelas::query()
        ->orderBy('nama_kelas')
        ->get()
        ->map(fn (Kelas $kelas): array => [
            'id' => $kelas->id,
            'label' => filled($kelas->unit)
                ? $kelas->nama_kelas.' ('.$kelas->unit.')'
                : (string) $kelas->nama_kelas,
        ]);
@endphp

<div class="table-filter-bar jadwal-filter-bar" role="group" aria-label="Filter jadwal pelajaran">
    <label class="table-filter-field" for="jadwal-filter-semester">
        <span>Semester</span>
        <select
            id="jadwal-filter-semester"
            wire:model.live="tableFilters.semester.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach (JadwalPelajaran::SEMESTERS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="table-filter-field" for="jadwal-filter-tahun">
        <span>Tahun ajaran</span>
        <select
            id="jadwal-filter-tahun"
            wire:model.live="tableFilters.tahun_ajaran.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach ($tahunOptions as $tahun)
                <option value="{{ $tahun }}">{{ $tahun }}</option>
            @endforeach
        </select>
    </label>

    <label class="table-filter-field" for="jadwal-filter-kelas">
        <span>Kelas</span>
        <select
            id="jadwal-filter-kelas"
            wire:model.live="tableFilters.kelas_id.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach ($kelasOptions as $kelas)
                <option value="{{ $kelas['id'] }}">{{ $kelas['label'] }}</option>
            @endforeach
        </select>
    </label>
</div>
