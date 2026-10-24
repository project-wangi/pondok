<x-filament-panels::page>
    <div class="presensi-page asesmen-page rapor-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">e-Rapor belum dapat disimpan. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="asesmen-back">
            <x-filament::button
                tag="a"
                :href="$backUrl"
                color="gray"
                size="sm"
                :outlined="true"
            >
                Kembali ke daftar e-Rapor
            </x-filament::button>
        </div>

        <x-filament::section heading="Identitas penilaian" description="Nilai rapor disimpan untuk guru, kelas, mata pelajaran, dan periode berjalan.">
            <div class="asesmen-identity">
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Kelas</span>
                    <span class="asesmen-identity-value">{{ $class?->nama_kelas ?? 'Kelas tidak tersedia' }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Mata Pelajaran</span>
                    <span class="asesmen-identity-value">{{ $subject?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Guru</span>
                    <span class="asesmen-identity-value">{{ $guruName ?? 'Guru tidak tersedia' }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Semester</span>
                    <span class="asesmen-identity-value">{{ $period['semester'] }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Tahun Ajaran</span>
                    <span class="asesmen-identity-value">{{ $period['tahun_ajaran'] }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Jumlah Siswa</span>
                    <span class="asesmen-identity-value">{{ $students->count() }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Laki-laki</span>
                    <span class="asesmen-identity-value">{{ $maleCount }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Perempuan</span>
                    <span class="asesmen-identity-value">{{ $femaleCount }}</span>
                </div>
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Jenis Ujian</span>
                    <span class="asesmen-identity-value">{{ $components->count() }}</span>
                </div>
            </div>
        </x-filament::section>

        @if ($students->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum mengisi e-Rapor.</p>
            </div>
        @else
            <form wire:submit="simpan" class="asesmen-form">
                <x-filament::section heading="Pengaturan bobot nilai rapor" description="Persentase bisa diubah sesuai analisis guru. Total harus 100%. Komponen yang belum memiliki nilai dihitung 0.">
                    <div class="rapor-bobot">
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-formatif">Nilai Formatif</label>
                            <input id="rapor-bobot-formatif" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotFormatif" aria-describedby="rapor-bobot-formatif-error">
                            <div id="rapor-bobot-formatif-error">
                                @error('bobotFormatif') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-sumatif">Sumatif Lingkup Materi</label>
                            <input id="rapor-bobot-sumatif" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotSumatif" aria-describedby="rapor-bobot-sumatif-error">
                            <div id="rapor-bobot-sumatif-error">
                                @error('bobotSumatif') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-asts">Sumatif Tengah Semester (ASTS)</label>
                            <input id="rapor-bobot-asts" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotAsts" aria-describedby="rapor-bobot-asts-error">
                            <div id="rapor-bobot-asts-error">
                                @error('bobotAsts') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-asas">Sumatif Akhir Semester (ASAS)</label>
                            <input id="rapor-bobot-asas" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotAsas" aria-describedby="rapor-bobot-asas-error">
                            <div id="rapor-bobot-asas-error">
                                @error('bobotAsas') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-kehadiran">Nilai Kehadiran</label>
                            <input id="rapor-bobot-kehadiran" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotKehadiran" aria-describedby="rapor-bobot-kehadiran-error">
                            <div id="rapor-bobot-kehadiran-error">
                                @error('bobotKehadiran') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-sikap">Nilai Sikap</label>
                            <input id="rapor-bobot-sikap" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotSikap" aria-describedby="rapor-bobot-sikap-error">
                            <div id="rapor-bobot-sikap-error">
                                @error('bobotSikap') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-keaktifan">Nilai Keaktifan</label>
                            <input id="rapor-bobot-keaktifan" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="bobotKeaktifan" aria-describedby="rapor-bobot-keaktifan-error">
                            <div id="rapor-bobot-keaktifan-error">
                                @error('bobotKeaktifan') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="rapor-bobot-item">
                            <label class="rapor-bobot-label" for="rapor-bobot-kkm">Batas Nilai Ketuntasan (KKM)</label>
                            <input id="rapor-bobot-kkm" type="number" class="rapor-input" min="0" max="100" step="1" inputmode="numeric" wire:model.live.debounce.500ms="kkm" aria-describedby="rapor-bobot-kkm-error">
                            <div id="rapor-bobot-kkm-error">
                                @error('kkm') <p class="presensi-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="rapor-bobot-summary">
                        <span>Total bobot nilai</span>
                        <strong @class(['rapor-total-invalid' => $totalBobot !== 100])>{{ $totalBobot }}%</strong>
                    </div>

                    @if ($totalBobot !== 100)
                        <p class="presensi-error" role="alert">Total bobot saat ini {{ $totalBobot }}%. Ubah bobot hingga berjumlah 100% sebelum menyimpan.</p>
                    @endif
                    @error('bobot')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror
                    <p class="presensi-help">Nilai akhir = jumlah (bobot × nilai komponen) ÷ 100. Nilai yang belum tersedia dihitung 0.</p>
                </x-filament::section>

                <x-filament::section heading="Matriks nilai rapor" :description="'Nilai rapor kelas '.($class?->nama_kelas ?? '-').'.'">
                    <p class="presensi-help">Hanya kolom ASTS dan ASAS yang diisi manual. Kolom formatif, ujian harian, kehadiran, sikap, dan keaktifan terisi otomatis dari menu terkait.</p>
                    @error('nilai')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror

                    @php
                        $groupColspan = 1 + max($components->count(), 1) + 1 + 2;
                    @endphp

                    <div class="presensi-table-scroll" role="region" aria-label="Matriks nilai rapor" tabindex="0">
                        <table class="asesmen-matrix rapor-matrix">
                            <caption class="presensi-sr-only">Matriks nilai rapor {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="3" class="asesmen-number rapor-vertical">No. Urut</th>
                                    <th scope="col" rowspan="3" class="rapor-nis rapor-vertical">Nomor Induk</th>
                                    <th scope="col" rowspan="3" class="asesmen-name-col rapor-vertical">Nama Murid</th>
                                    <th scope="col" rowspan="3" class="rapor-gender rapor-vertical">LP</th>
                                    <th scope="col" colspan="{{ $groupColspan }}" class="rapor-group">Nilai Capaian Pembelajaran</th>
                                    <th scope="col" colspan="3" class="rapor-group">Nilai Pendukung</th>
                                    <th scope="col" rowspan="3" class="rapor-akhir-col rapor-vertical">Nilai Akhir Rapor</th>
                                    <th scope="col" rowspan="3" class="rapor-ket-col rapor-vertical">Ket. (Tuntas/Belum)</th>
                                    <th scope="col" rowspan="3" class="rapor-rank-col rapor-vertical">Peringkat</th>
                                </tr>
                                <tr>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Rata-rata Nilai Formatif">Rata-Rata Nilai Formatif (F)</th>
                                    <th scope="col" colspan="{{ max($components->count(), 1) }}" class="rapor-wrap">Sumatif Lingkup Materi/Ujian Harian</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Rata-rata Sumatif Lingkup Materi">Rata-rata Sumatif Lingkup Materi</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Sumatif Tengah Semester">Sumatif Tengah Semester (ASTS)</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Sumatif Akhir Semester">Sumatif Akhir Semester (ASAS)</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Rata-rata Kehadiran (Angka)">Konversi Nilai Kehadiran (Angka)</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Nilai Sikap (Angka)">Nilai Sikap (Angka)</th>
                                    <th scope="col" rowspan="2" class="rapor-wrap" title="Nilai Keaktifan (Angka)">Nilai Keaktifan (Angka)</th>
                                </tr>
                                <tr>
                                    @forelse ($components as $component)
                                        <th scope="col" class="rapor-uh-col" title="{{ $component->nama }}">UH{{ $loop->iteration }}</th>
                                    @empty
                                        <th scope="col" class="rapor-uh-col" title="Belum ada ujian harian">UH</th>
                                    @endforelse
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        $student = $row['student'];
                                    @endphp
                                    <tr wire:key="rapor-siswa-{{ $student->id }}">
                                        <td class="asesmen-number">{{ $loop->iteration }}</td>
                                        <td class="rapor-nis">{{ $student->nis ?: '-' }}</td>
                                        <td class="asesmen-name-cell">{{ $student->nama }}</td>
                                        <td>{{ $student->jenisKelaminKode() }}</td>
                                        <td class="rapor-value">{{ $row['formatif'] ?? '-' }}</td>
                                        @forelse ($components as $index => $component)
                                            <td class="rapor-value">{{ $row['uh'][$index] ?? '-' }}</td>
                                        @empty
                                            <td class="rapor-value">-</td>
                                        @endforelse
                                        <td class="rapor-value">{{ $row['sumatif'] ?? '-' }}</td>
                                        <td class="rapor-score-cell">
                                            <label class="presensi-sr-only" for="rapor-asts-{{ $student->id }}">Nilai ASTS {{ $student->nama }}</label>
                                            <input
                                                id="rapor-asts-{{ $student->id }}"
                                                type="number"
                                                class="asesmen-score"
                                                min="0"
                                                max="100"
                                                step="1"
                                                inputmode="numeric"
                                                placeholder="-"
                                                wire:model.blur="nilai.{{ $student->id }}.asts"
                                                aria-describedby="rapor-asts-error-{{ $student->id }}"
                                            >
                                            <div id="rapor-asts-error-{{ $student->id }}">
                                                @error('nilai.'.$student->id.'.asts')
                                                    <p class="presensi-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </td>
                                        <td class="rapor-score-cell">
                                            <label class="presensi-sr-only" for="rapor-asas-{{ $student->id }}">Nilai ASAS {{ $student->nama }}</label>
                                            <input
                                                id="rapor-asas-{{ $student->id }}"
                                                type="number"
                                                class="asesmen-score"
                                                min="0"
                                                max="100"
                                                step="1"
                                                inputmode="numeric"
                                                placeholder="-"
                                                wire:model.blur="nilai.{{ $student->id }}.asas"
                                                aria-describedby="rapor-asas-error-{{ $student->id }}"
                                            >
                                            <div id="rapor-asas-error-{{ $student->id }}">
                                                @error('nilai.'.$student->id.'.asas')
                                                    <p class="presensi-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </td>
                                        <td class="rapor-value">{{ $row['kehadiran'] ?? '-' }}</td>
                                        <td class="rapor-value">{{ $row['sikap'] ?? '-' }}</td>
                                        <td class="rapor-value">{{ $row['keaktifan'] ?? '-' }}</td>
                                        <td class="rapor-value rapor-akhir">{{ $row['nilai_akhir'] }}</td>
                                        <td class="rapor-ket">
                                            <span @class(['rapor-tuntas' => $row['ket'] === 'Tuntas', 'rapor-belum' => $row['ket'] !== 'Tuntas'])>{{ $row['ket'] }}</span>
                                        </td>
                                        <td class="rapor-rank">{{ $row['peringkat'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="presensi-help">
                        Sumber otomatis:
                        <a class="asesmen-link" href="{{ $asesmenUrl }}">Asesmen Formatif</a>,
                        <a class="asesmen-link" href="{{ $nilaiUjianUrl }}">Nilai Ujian</a>,
                        <a class="asesmen-link" href="{{ $sikapUrl }}">Sikap dan Keaktifan</a>,
                        <a class="asesmen-link" href="{{ $presensiUrl }}">Presensi Siswa</a>.
                        Kolom UH mengikuti urutan jenis ujian pada menu Nilai Ujian.
                    </p>

                    <div class="asesmen-footer">
                        <p class="presensi-help">Rata-rata memakai pembulatan bilangan bulat. Perubahan tersimpan setelah menekan Simpan e-Rapor.</p>
                        <x-filament::button
                            type="submit"
                            :loading-indicator="false"
                            wire:loading.attr="disabled"
                            wire:target="simpan"
                        >
                            <span wire:loading.remove wire:target="simpan">Simpan e-Rapor</span>
                            <span wire:loading wire:target="simpan">Menyimpan...</span>
                        </x-filament::button>
                    </div>
                    <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan nilai e-Rapor.</p>
                </x-filament::section>
            </form>
        @endif
    </div>
</x-filament-panels::page>
