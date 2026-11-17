<x-filament-panels::page>
    <div class="presensi-page rapor-admin-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Rapor belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rapor-period">
            <label class="rapor-period-field" for="rapor-semester">
                <span>Semester</span>
                <select id="rapor-semester" wire:model.live="semester">
                    @foreach ($semesterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="rapor-period-field" for="rapor-tahun-ajaran">
                <span>Tahun Ajaran</span>
                <select id="rapor-tahun-ajaran" wire:model.live="tahunAjaran">
                    @foreach ($tahunAjaranOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <p class="rapor-period-help">
                Menampilkan semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.
            </p>
        </div>

        @if ($langkah === 'kelas')
            <x-filament::section
                heading="Pilih kelas"
                description="Pilih kelas untuk melihat daftar siswa berdasarkan peringkat pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}."
            >
                @if ($kelasList->isEmpty())
                    <div class="presensi-notice">
                        @if ($terbatasKeKelasWali)
                            <p class="presensi-label">Anda belum ditetapkan sebagai wali kelas.</p>
                            <p class="presensi-help">Rapor hanya dapat dibuka untuk kelas yang Anda walikan. Hubungi admin untuk menetapkan kelas wali Anda.</p>
                        @else
                            <p class="presensi-label">Belum ada kelas.</p>
                            <p class="presensi-help">Tambahkan kelas terlebih dahulu melalui menu Kelas.</p>
                        @endif
                    </div>
                @else
                    <div class="rapor-class-grid">
                        @foreach ($kelasList as $item)
                            <button
                                type="button"
                                class="rapor-class-card"
                                wire:key="rapor-kelas-{{ $item['kelas']->id }}"
                                wire:click="pilihKelas({{ $item['kelas']->id }})"
                                aria-label="Lihat peringkat kelas {{ $item['kelas']->nama_kelas }}"
                            >
                                <span class="rapor-class-name">{{ $item['kelas']->nama_kelas }}</span>
                                <span class="rapor-class-meta">
                                    {{ $item['jumlah_murid'] }} siswa
                                    <span aria-hidden="true">·</span>
                                    {{ $item['jumlah_mapel'] }} mapel terkirim
                                </span>
                                <span class="rapor-class-link">Lihat peringkat</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="presensi-help">
                        Peringkat dihitung dari rata-rata nilai akhir mata pelajaran yang sudah dikirim guru melalui e-Rapor.
                        Siswa tanpa nilai ditempatkan di urutan akhir.
                    </p>
                @endif
            </x-filament::section>
        @elseif ($langkah === 'murid')
            <div class="rapor-back">
                <x-filament::button wire:click="kembaliKeDaftarKelas" color="gray" size="sm" :outlined="true">
                    Kembali ke daftar kelas
                </x-filament::button>
            </div>

            <x-filament::section
                :heading="'Peringkat kelas '.$kelas->nama_kelas"
                :description="'Semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'. Klik nama siswa untuk membuka rapor.'"
            >
                @if ($daftarMurid->isEmpty())
                    <div class="presensi-notice">
                        <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                        <p class="presensi-help">Tempatkan siswa ke kelas melalui menu Data Murid.</p>
                    </div>
                @else
                    <div class="presensi-table-scroll" role="region" aria-label="Peringkat siswa kelas {{ $kelas->nama_kelas }}" tabindex="0">
                        <table class="presensi-table rapor-rank-table">
                            <caption class="presensi-sr-only">Peringkat siswa kelas {{ $kelas->nama_kelas }} semester {{ $period['semester'] }} {{ $period['tahun_ajaran'] }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="rapor-rank-col">Peringkat</th>
                                    <th scope="col" class="rapor-nis-col">NIS</th>
                                    <th scope="col" class="rapor-name-col">Nama</th>
                                    <th scope="col" class="rapor-average-col">Rata-rata</th>
                                    <th scope="col" class="rapor-submitted-col">Mapel terkirim</th>
                                    <th scope="col" class="rapor-action-col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($daftarMurid as $row)
                                    <tr wire:key="rapor-murid-{{ $row['murid']->id }}">
                                        <td class="rapor-rank-col presensi-numeric">{{ $row['peringkat'] ?? '—' }}</td>
                                        <td class="rapor-nis-col presensi-numeric">{{ $row['murid']->nis ?? '—' }}</td>
                                        <td class="rapor-name-col">{{ $row['murid']->nama }}</td>
                                        <td class="rapor-average-col presensi-numeric">{{ $row['rata_rata'] ?? '—' }}</td>
                                        <td class="rapor-submitted-col presensi-numeric">{{ $row['jumlah_mapel'] }}</td>
                                        <td class="rapor-action-col">
                                            <x-filament::button
                                                wire:click="pilihMurid({{ $row['murid']->id }})"
                                                size="sm"
                                                :outlined="$row['jumlah_mapel'] === 0"
                                            >
                                                Lihat rapor
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="presensi-help">
                        Peringkat memakai urutan kompetisi: nilai rata-rata yang sama memperoleh peringkat yang sama.
                        Rata-rata hanya menghitung mata pelajaran yang sudah memiliki nilai e-Rapor.
                    </p>
                @endif
            </x-filament::section>
        @else
            <div class="rapor-back">
                <x-filament::button wire:click="kembaliKeDaftarMurid" color="gray" size="sm" :outlined="true">
                    Kembali ke peringkat kelas
                </x-filament::button>
            </div>

            @include('rapor-sheet', ['rapor' => $rapor])

            <div class="rapor-kepribadian-section">
                <x-filament::section
                    heading="Nilai kepribadian"
                    description="Isi angka 0 sampai 100. Huruf otomatis: A ≥ 90, B ≥ 80, C ≥ 70, D ≥ 60, E < 60, dan ikut tercetak pada rapor."
                >
                    <form wire:submit="simpanKepribadian" class="rapor-kepribadian-form">
                        <div class="rapor-kepribadian-grid">
                            @foreach ($aspekKepribadian as $key => $label)
                                <label class="rapor-kepribadian-field" for="kepribadian-{{ $key }}">
                                    <span class="rapor-kepribadian-label">{{ $label }}</span>
                                    <input
                                        id="kepribadian-{{ $key }}"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        inputmode="numeric"
                                        class="rapor-kepribadian-input"
                                        wire:model.live.debounce.400ms="kepribadian.{{ $key }}"
                                        aria-describedby="kepribadian-{{ $key }}-huruf"
                                    >
                                    <span id="kepribadian-{{ $key }}-huruf" class="rapor-kepribadian-letter">
                                        {{ $hurufKepribadian[$key] ?? '—' }}
                                    </span>
                                    @error('kepribadian.'.$key)
                                        <span class="presensi-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            @endforeach
                        </div>

                        <div class="rapor-kepribadian-actions">
                            <x-filament::button type="submit">
                                Simpan kepribadian
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
            </div>

            <div class="rapor-export">
                <p class="presensi-help">
                    Cetak rapor dibuka pada tab baru dengan lembar bersih, lalu simpan sebagai PDF lewat dialog cetak.
                    Simpan kepribadian terlebih dahulu agar nilainya ikut tercetak.
                </p>
                <div class="rapor-export-actions">
                    <x-filament::button
                        tag="a"
                        :href="route('rapor.print', [
                            'kelas' => $kelas->id,
                            'murid' => $muridId,
                            'semester' => $semester,
                            'tahun_ajaran' => $tahunAjaran,
                        ])"
                        target="_blank"
                        rel="noopener"
                        color="primary"
                        icon="heroicon-o-printer"
                    >
                        Cetak / Simpan PDF
                    </x-filament::button>

                    <x-filament::button wire:click="export" color="success" icon="heroicon-o-arrow-down-tray">
                        Ekspor Excel
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
