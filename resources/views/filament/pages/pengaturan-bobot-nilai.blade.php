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
                Bobot berlaku untuk semua kelas dan guru pengampu pada semester
                {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.
            </p>
        </div>

        <x-filament::section
            heading="Bobot nilai per mata pelajaran"
            description="Isi persentase tiap komponen. Total setiap mata pelajaran harus 100%. Guru tetap mengatur KKM masing-masing."
        >
            @if ($mapels->isEmpty())
                <div class="presensi-notice">
                    <p class="presensi-label">Belum ada mata pelajaran.</p>
                    <p class="presensi-help">Tambahkan mata pelajaran terlebih dahulu melalui menu Mata Pelajaran.</p>
                </div>
            @else
                <form wire:submit="simpan" class="asesmen-form">
                    <div class="presensi-table-scroll" role="region" aria-label="Bobot nilai per mata pelajaran" tabindex="0">
                        <table class="presensi-table bobot-table">
                            <caption class="presensi-sr-only">
                                Bobot nilai mata pelajaran semester {{ $period['semester'] }} {{ $period['tahun_ajaran'] }}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="bobot-number-col">No</th>
                                    <th scope="col" class="bobot-subject-col">Mata Pelajaran</th>
                                    @foreach ($komponen as $label)
                                        <th scope="col" class="bobot-value-col">{{ $label }}</th>
                                    @endforeach
                                    <th scope="col" class="bobot-total-col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mapels as $mapel)
                                    @php
                                        $total = array_sum(array_map('intval', $bobot[$mapel->id] ?? []));
                                    @endphp
                                    <tr wire:key="bobot-mapel-{{ $mapel->id }}">
                                        <td class="bobot-number-col presensi-numeric">{{ $loop->iteration }}</td>
                                        <td class="bobot-subject-col">{{ $mapel->nama_mapel }}</td>
                                        @foreach ($komponen as $key => $label)
                                            <td class="bobot-value-col">
                                                <label class="presensi-sr-only" for="bobot-{{ $mapel->id }}-{{ $key }}">
                                                    {{ $label }} {{ $mapel->nama_mapel }}
                                                </label>
                                                <input
                                                    id="bobot-{{ $mapel->id }}-{{ $key }}"
                                                    type="number"
                                                    class="rapor-input bobot-input"
                                                    min="0"
                                                    max="100"
                                                    step="1"
                                                    inputmode="numeric"
                                                    wire:model.live.debounce.400ms="bobot.{{ $mapel->id }}.{{ $key }}"
                                                >
                                            </td>
                                        @endforeach
                                        <td @class(['bobot-total-col', 'presensi-numeric', 'rapor-total-invalid' => $total !== 100])>
                                            {{ $total }}%
                                        </td>
                                    </tr>
                                    @error('bobot.'.$mapel->id)
                                        <tr wire:key="bobot-error-{{ $mapel->id }}">
                                            <td colspan="{{ 3 + count($komponen) }}">
                                                <p class="presensi-error">{{ $message }}</p>
                                            </td>
                                        </tr>
                                    @enderror
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="asesmen-footer">
                        <p class="presensi-help">
                            Perubahan bobot langsung dipakai saat guru membuka halaman e-Rapor.
                            Nilai akhir = jumlah (bobot × nilai komponen) ÷ 100.
                        </p>
                        <x-filament::button type="submit">
                            Simpan bobot
                        </x-filament::button>
                    </div>
                </form>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
