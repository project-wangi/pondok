<x-filament-panels::page>
    <div class="presensi-page bobot-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Bobot nilai belum dapat disimpan. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rapor-period">
            <label class="rapor-period-field" for="bobot-semester">
                <span>Semester</span>
                <select id="bobot-semester" wire:model.live="semester">
                    @foreach ($semesterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="rapor-period-field" for="bobot-tahun-ajaran">
                <span>Tahun Ajaran</span>
                <select id="bobot-tahun-ajaran" wire:model.live="tahunAjaran">
                    @foreach ($tahunAjaranOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <p class="rapor-period-help">
                Satu set bobot dipakai semua mata pelajaran, kelas, dan guru pengampu
                pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.
            </p>
        </div>

        <x-filament::section
            heading="Bobot nilai semua mata pelajaran"
            description="Isi persentase tiap komponen. Total seluruh komponen harus 100%. Guru tetap mengatur KKM masing-masing."
        >
            <form wire:submit="simpan" class="bobot-form">
                <div class="bobot-grid">
                    @foreach ($komponen as $key => $label)
                        <div class="bobot-field" wire:key="bobot-field-{{ $key }}">
                            <label for="bobot-{{ $key }}">{{ $label }}</label>
                            <input
                                id="bobot-{{ $key }}"
                                type="number"
                                class="rapor-input bobot-input"
                                min="0"
                                max="100"
                                step="1"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="bobot.{{ $key }}"
                                aria-describedby="bobot-{{ $key }}-error"
                            >
                            <div id="bobot-{{ $key }}-error">
                                @error('bobot.'.$key)
                                    <p class="presensi-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                @php
                    $total = array_sum(array_map('intval', $bobot));
                @endphp

                <div class="bobot-summary">
                    <span>Total bobot</span>
                    <strong @class(['rapor-total-invalid' => $total !== 100])>{{ $total }}%</strong>
                </div>

                @error('bobot')
                    <p class="presensi-error">{{ $message }}</p>
                @enderror

                <div class="bobot-actions">
                    <p class="presensi-help">
                        Perubahan bobot langsung dipakai saat guru membuka halaman e-Rapor.
                        Nilai akhir = jumlah (bobot × nilai komponen) ÷ 100.
                    </p>
                    <x-filament::button type="submit">
                        Simpan bobot
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
