<x-filament-panels::page>
    <div class="presensi-page sikap-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Nilai belum dapat disimpan. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="sikap-back">
            <x-filament::button
                tag="a"
                :href="$backUrl"
                color="gray"
                size="sm"
                :outlined="true"
            >
                Kembali ke daftar sikap dan keaktifan
            </x-filament::button>
        </div>

        <x-filament::section heading="Identitas penilaian" description="Nilai disimpan untuk guru, kelas, mata pelajaran, dan periode berjalan.">
            <div class="sikap-identity">
                <div class="sikap-identity-item">
                    <span class="presensi-help">Kelas</span>
                    <span class="sikap-identity-value">{{ $class?->nama_kelas ?? 'Kelas tidak tersedia' }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Mata Pelajaran</span>
                    <span class="sikap-identity-value">{{ $subject?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Guru</span>
                    <span class="sikap-identity-value">{{ $guruName ?? 'Guru tidak tersedia' }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Semester</span>
                    <span class="sikap-identity-value">{{ $period['semester'] }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Tahun Ajaran</span>
                    <span class="sikap-identity-value">{{ $period['tahun_ajaran'] }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Jumlah Siswa</span>
                    <span class="sikap-identity-value">{{ $students->count() }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Laki-laki</span>
                    <span class="sikap-identity-value">{{ $maleCount }}</span>
                </div>
                <div class="sikap-identity-item">
                    <span class="presensi-help">Perempuan</span>
                    <span class="sikap-identity-value">{{ $femaleCount }}</span>
                </div>
            </div>
        </x-filament::section>

        @if ($students->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum mengisi nilai sikap dan keaktifan.</p>
            </div>
        @else
            <x-filament::section heading="Nilai sikap dan keaktifan" :description="'Kelas '.($class?->nama_kelas ?? '-').', mata pelajaran '.($subject?->nama_mapel ?? '-').'.'">
                <form wire:submit="simpan" class="sikap-form">
                    <p class="presensi-help">Pilih kategori A sampai E untuk setiap siswa. Angka di samping otomatis mengikuti kategori dan tidak perlu diisi manual. Kosongkan nilai jika belum dinilai.</p>
                    @error('sikap')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror

                    <div class="presensi-table-scroll" role="region" aria-label="Tabel nilai sikap dan keaktifan" tabindex="0">
                        <table class="sikap-table">
                            <caption class="presensi-sr-only">Nilai sikap dan keaktifan {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="2" class="sikap-number">No</th>
                                    <th scope="col" rowspan="2">NIS</th>
                                    <th scope="col" rowspan="2" class="sikap-name-col">Nama Murid</th>
                                    <th scope="col" rowspan="2" class="sikap-gender">L/P</th>
                                    <th scope="col" colspan="2" class="sikap-group">Sikap</th>
                                    <th scope="col" colspan="2" class="sikap-group">Keaktifan</th>
                                </tr>
                                <tr>
                                    <th scope="col">Nilai (Huruf)</th>
                                    <th scope="col">Konversi (Angka)</th>
                                    <th scope="col">Nilai (Huruf)</th>
                                    <th scope="col">Konversi (Angka)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $gender = $student->jenisKelaminKode();
                                    @endphp
                                    <tr wire:key="sikap-siswa-{{ $student->id }}">
                                        <td class="sikap-number">{{ $loop->iteration }}</td>
                                        <td class="sikap-nis">{{ $student->nis ?: '-' }}</td>
                                        <td class="sikap-name-cell">{{ $student->nama }}</td>
                                        <td class="sikap-gender">{{ $gender }}</td>
                                        <td class="sikap-select-cell">
                                            <label class="presensi-sr-only" for="sikap-{{ $student->id }}">Nilai sikap {{ $student->nama }}</label>
                                            <select
                                                id="sikap-{{ $student->id }}"
                                                class="sikap-select"
                                                wire:model.live="sikap.{{ $student->id }}"
                                                aria-describedby="sikap-error-{{ $student->id }}"
                                                aria-invalid="{{ $errors->has('sikap.'.$student->id) ? 'true' : 'false' }}"
                                            >
                                                <option value="">-</option>
                                                @foreach ($kategori as $huruf => $angka)
                                                    <option value="{{ $huruf }}">{{ $huruf }} — {{ $labelSikap[$huruf] }} ({{ $angka }})</option>
                                                @endforeach
                                            </select>
                                            <div id="sikap-error-{{ $student->id }}">
                                                @error('sikap.'.$student->id)
                                                    <p class="presensi-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </td>
                                        <td class="sikap-angka">{{ $kategori[$sikap[$student->id] ?? ''] ?? '-' }}</td>
                                        <td class="sikap-select-cell">
                                            <label class="presensi-sr-only" for="keaktifan-{{ $student->id }}">Nilai keaktifan {{ $student->nama }}</label>
                                            <select
                                                id="keaktifan-{{ $student->id }}"
                                                class="sikap-select"
                                                wire:model.live="keaktifan.{{ $student->id }}"
                                                aria-describedby="keaktifan-error-{{ $student->id }}"
                                                aria-invalid="{{ $errors->has('keaktifan.'.$student->id) ? 'true' : 'false' }}"
                                            >
                                                <option value="">-</option>
                                                @foreach ($kategori as $huruf => $angka)
                                                    <option value="{{ $huruf }}">{{ $huruf }} — {{ $labelKeaktifan[$huruf] }} ({{ $angka }})</option>
                                                @endforeach
                                            </select>
                                            <div id="keaktifan-error-{{ $student->id }}">
                                                @error('keaktifan.'.$student->id)
                                                    <p class="presensi-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </td>
                                        <td class="sikap-angka">{{ $kategori[$keaktifan[$student->id] ?? ''] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="sikap-footer">
                        <p class="presensi-help">Nilai kosong tidak disimpan. Perubahan baru tersimpan setelah menekan Simpan nilai.</p>
                        <x-filament::button
                            type="submit"
                            :loading-indicator="false"
                            wire:loading.attr="disabled"
                            wire:target="simpan"
                        >
                            <span wire:loading.remove wire:target="simpan">Simpan nilai</span>
                            <span wire:loading wire:target="simpan">Menyimpan...</span>
                        </x-filament::button>
                    </div>
                    <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan nilai sikap dan keaktifan.</p>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
