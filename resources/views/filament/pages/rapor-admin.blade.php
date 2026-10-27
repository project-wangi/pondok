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

        @if ($langkah === 'kelas')
            <x-filament::section
                heading="Pilih kelas"
                description="Pilih kelas untuk melihat daftar siswa berdasarkan peringkat pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}."
            >
                @if ($kelasList->isEmpty())
                    <div class="presensi-notice">
                        <p class="presensi-label">Belum ada kelas.</p>
                        <p class="presensi-help">Tambahkan kelas terlebih dahulu melalui menu Kelas.</p>
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

            <article class="rapor-sheet" aria-label="Rapor {{ $rapor['student']['nama'] }}">
                <header class="rapor-sheet-header">
                    <p class="rapor-school">{{ $rapor['school_name'] }}</p>
                    @if ($rapor['school_address'] !== '')
                        <p class="rapor-school-address">{{ $rapor['school_address'] }}</p>
                    @endif
                    <h2 class="rapor-sheet-title">LAPORAN HASIL BELAJAR</h2>
                    <p class="rapor-sheet-subtitle">
                        UJIAN AKHIR SEMESTER {{ mb_strtoupper($rapor['semester']) }} {{ $rapor['tahun_ajaran'] }}
                    </p>
                </header>

                <dl class="rapor-identity">
                    <div>
                        <dt>Nama</dt>
                        <dd>{{ $rapor['student']['nama'] }}</dd>
                    </div>
                    <div>
                        <dt>NISM</dt>
                        <dd>{{ $rapor['student']['nis'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Kelas</dt>
                        <dd>{{ $rapor['class'] }}</dd>
                    </div>
                    <div>
                        <dt>NISN</dt>
                        <dd>{{ $rapor['student']['nisn'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Tahun Pelajaran</dt>
                        <dd>{{ $rapor['tahun_ajaran'] }}</dd>
                    </div>
                    <div>
                        <dt>No. Absen</dt>
                        <dd>{{ $rapor['student']['no_absen'] }}</dd>
                    </div>
                </dl>

                <table class="rapor-sheet-table rapor-score-table">
                    <caption class="presensi-sr-only">Nilai mata pelajaran {{ $rapor['student']['nama'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="rapor-no-col">No</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col" class="rapor-kkm-col">KKM</th>
                            <th scope="col" class="rapor-score-col">Angka</th>
                            <th scope="col" class="rapor-letter-col">Huruf</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rapor['subjects'] as $index => $subject)
                            <tr>
                                <td class="rapor-no-col presensi-numeric">{{ $index + 1 }}</td>
                                <td class="rapor-subject-name">{{ $subject['nama'] }}</td>
                                <td class="rapor-kkm-col presensi-numeric">{{ $subject['kkm'] ?? '—' }}</td>
                                <td class="rapor-score-col presensi-numeric">{{ $subject['angka'] ?? '—' }}</td>
                                <td class="rapor-letter-col">{{ $subject['huruf'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="rapor-empty-cell">Belum ada mata pelajaran untuk kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="rapor-summary">
                    <div>
                        <span>Jumlah Nilai</span>
                        <strong>{{ $rapor['jumlah'] ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Rata-rata</span>
                        <strong>{{ $rapor['rata_rata'] ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Peringkat Kelas</span>
                        <strong>
                            @if ($rapor['peringkat'] !== null)
                                {{ $rapor['peringkat'] }} dari {{ $rapor['jumlah_siswa'] }}
                            @else
                                —
                            @endif
                        </strong>
                    </div>
                    <div>
                        <span>Peminatan</span>
                        <strong>{{ $rapor['peminatan'] !== '' ? $rapor['peminatan'] : '—' }}</strong>
                    </div>
                </div>

                <div class="rapor-sheet-columns">
                    <section class="rapor-block">
                        <h3>Kepribadian</h3>
                        <table class="rapor-sheet-table rapor-small-table">
                            <thead>
                                <tr>
                                    <th scope="col">Aspek</th>
                                    <th scope="col" class="rapor-score-col">Angka</th>
                                    <th scope="col" class="rapor-letter-col">Huruf</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rapor['kepribadian'] as $aspect)
                                    <tr>
                                        <td class="rapor-subject-name">{{ $aspect['label'] }}</td>
                                        <td class="rapor-score-col presensi-numeric">{{ $aspect['angka'] ?? '—' }}</td>
                                        <td class="rapor-letter-col">{{ $aspect['huruf'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>

                    <section class="rapor-block">
                        <h3>Ketidakhadiran</h3>
                        <table class="rapor-sheet-table rapor-small-table">
                            <thead>
                                <tr>
                                    <th scope="col">Keterangan</th>
                                    <th scope="col" class="rapor-score-col">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="rapor-subject-name">Sakit</td>
                                    <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['sakit'] }}</td>
                                </tr>
                                <tr>
                                    <td class="rapor-subject-name">Izin</td>
                                    <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['izin'] }}</td>
                                </tr>
                                <tr>
                                    <td class="rapor-subject-name">Alpa</td>
                                    <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['alpa'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <p class="rapor-note">{{ $rapor['catatan'] }}</p>

                <div class="rapor-signatures">
                    <div>
                        <span>Kepala Madrasah</span>
                        <strong>{{ $rapor['kepala_madrasah'] ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Wali Kelas</span>
                        <strong>{{ $rapor['wali_kelas'] ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Orang Tua/Wali</span>
                        <strong>{{ $rapor['orang_tua'] ?: '................' }}</strong>
                    </div>
                </div>
            </article>

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

                <div class="rapor-export">
                    <p class="presensi-help">
                        Unduh rapor dalam format Excel. Simpan kepribadian terlebih dahulu agar nilainya ikut tercetak.
                    </p>
                    <x-filament::button wire:click="export" color="success" icon="heroicon-o-arrow-down-tray">
                        Export Excel
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
