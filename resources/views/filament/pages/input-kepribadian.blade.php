<x-filament-panels::page>
    <div class="presensi-page kepribadian-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Nilai kepribadian belum dapat disimpan. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rapor-period">
            <label class="rapor-period-field" for="kepribadian-semester">
                <span>Semester</span>
                <select id="kepribadian-semester" wire:model.live="semester">
                    @foreach ($semesterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="rapor-period-field" for="kepribadian-tahun-ajaran">
                <span>Tahun Ajaran</span>
                <select id="kepribadian-tahun-ajaran" wire:model.live="tahunAjaran">
                    @foreach ($tahunAjaranOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <p class="rapor-period-help">
                Menampilkan semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.
            </p>
        </div>

        @if ($kelas === null)
            <div class="presensi-notice">
                <p class="presensi-label">Akun ini belum ditetapkan sebagai wali kelas.</p>
                <p class="presensi-help">Minta admin mengisi kelas pada data Guru untuk mengaktifkan penilaian kepribadian.</p>
            </div>
        @elseif ($murids->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Kelas {{ $kelas->nama_kelas }} belum memiliki siswa.</p>
                <p class="presensi-help">Tempatkan siswa ke kelas melalui menu Data Murid.</p>
            </div>
        @else
            <x-filament::section
                :heading="'Kepribadian kelas '.$kelas->nama_kelas"
                description="Isi angka 0 sampai 100. Huruf otomatis: A ≥ 90, B ≥ 80, C ≥ 70, D ≥ 60, E < 60."
            >
                <form wire:submit="simpan" class="asesmen-form">
                    <div class="presensi-table-scroll" role="region" aria-label="Nilai kepribadian kelas {{ $kelas->nama_kelas }}" tabindex="0">
                        <table class="presensi-table kepribadian-table">
                            <caption class="presensi-sr-only">
                                Nilai kepribadian kelas {{ $kelas->nama_kelas }} semester {{ $period['semester'] }} {{ $period['tahun_ajaran'] }}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="kepribadian-number-col">No</th>
                                    <th scope="col" class="kepribadian-nis-col">NIS</th>
                                    <th scope="col" class="kepribadian-name-col">Nama</th>
                                    @foreach ($aspek as $label)
                                        <th scope="col" class="kepribadian-value-col">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($murids as $murid)
                                    <tr wire:key="kepribadian-murid-{{ $murid->id }}">
                                        <td class="kepribadian-number-col presensi-numeric">{{ $loop->iteration }}</td>
                                        <td class="kepribadian-nis-col presensi-numeric">{{ $murid->nis ?: '—' }}</td>
                                        <td class="kepribadian-name-col">{{ $murid->nama }}</td>
                                        @foreach ($aspek as $key => $label)
                                            <td class="kepribadian-value-col">
                                                <label class="presensi-sr-only" for="kepribadian-{{ $murid->id }}-{{ $key }}">
                                                    {{ $label }} {{ $murid->nama }}
                                                </label>
                                                <div class="kepribadian-field">
                                                    <input
                                                        id="kepribadian-{{ $murid->id }}-{{ $key }}"
                                                        type="number"
                                                        class="rapor-input kepribadian-input"
                                                        min="0"
                                                        max="100"
                                                        step="1"
                                                        inputmode="numeric"
                                                        placeholder="—"
                                                        wire:model.live.debounce.400ms="kepribadian.{{ $murid->id }}.{{ $key }}"
                                                    >
                                                    <span class="kepribadian-letter">{{ $huruf[$murid->id][$key] ?? '—' }}</span>
                                                </div>
                                                @error('kepribadian.'.$murid->id.'.'.$key)
                                                    <p class="presensi-error">{{ $message }}</p>
                                                @enderror
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="asesmen-footer">
                        <p class="presensi-help">
                            Hanya siswa yang diisi yang disimpan. Nilai kepribadian tercetak pada rapor dan ekspor Excel.
                        </p>
                        <x-filament::button type="submit">
                            Simpan kepribadian
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
