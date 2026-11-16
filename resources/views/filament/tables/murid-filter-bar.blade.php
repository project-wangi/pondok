@php
    use App\Models\Kelas;
    use App\Models\Murid;

    $table = \Livewire\Livewire::current();
    $filters = is_array(data_get($table, 'tableFilters')) ? data_get($table, 'tableFilters') : [];
    $kelasOptions = Kelas::query()
        ->orderBy('nama_kelas')
        ->pluck('nama_kelas', 'id')
        ->all();
@endphp

<div class="table-filter-bar murid-filter-bar" role="group" aria-label="Filter data siswa">
    <label class="table-filter-field" for="murid-filter-status">
        <span>Status</span>
        <select
            id="murid-filter-status"
            wire:model.live="tableFilters.status.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach (Murid::STATUS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="table-filter-field" for="murid-filter-unit">
        <span>Unit</span>
        <select
            id="murid-filter-unit"
            wire:model.live="tableFilters.unit.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach (Kelas::UNITS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="table-filter-field" for="murid-filter-kelas">
        <span>Kelas</span>
        <select
            id="murid-filter-kelas"
            wire:model.live="tableFilters.kelas_id.value"
            wire:loading.attr="disabled"
        >
            <option value="">Semua</option>
            @foreach ($kelasOptions as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
    </label>
</div>
