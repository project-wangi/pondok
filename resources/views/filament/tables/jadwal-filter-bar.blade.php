@php
    use App\Models\JadwalPelajaran;

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
@endphp

<div class="jadwal-filter-bar" role="group" aria-label="Filter jadwal pelajaran">
    <label class="jadwal-filter-field" for="jadwal-filter-semester">
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

    <label class="jadwal-filter-field" for="jadwal-filter-tahun">
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
</div>
