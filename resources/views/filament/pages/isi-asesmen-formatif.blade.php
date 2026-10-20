<x-filament-panels::page>
    <div class="presensi-page asesmen-page">
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

        <div class="asesmen-back">
            <x-filament::button
                tag="a"
                :href="$backUrl"
                color="gray"
                size="sm"
                :outlined="true"
            >
                Kembali ke daftar asesmen
            </x-filament::button>
        </div>

        <x-filament::section heading="Identitas penilaian" description="Nilai disimpan untuk guru, kelas, mata pelajaran, dan periode berjalan.">
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
            </div>
        </x-filament::section>

        @if ($materials->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada tujuan pembelajaran untuk {{ $subject?->nama_mapel ?? 'mata pelajaran ini' }}.</p>
                <p class="presensi-help">Asesmen formatif membutuhkan lingkup materi dan tujuan pembelajaran. Isi data tersebut terlebih dahulu.</p>
                <p><a class="asesmen-link" href="{{ $lingkupUrl }}">Buka Input Lingkup Materi dan Tujuan Pembelajaran</a></p>
            </div>
        @elseif ($students->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum mengisi asesmen formatif.</p>
            </div>
        @else
            <x-filament::section heading="Matriks nilai formatif" :description="'Nilai setiap tujuan pembelajaran untuk kelas '.($class?->nama_kelas ?? '-').'.'">
                <form wire:submit="simpan" class="asesmen-form">
                    <p class="presensi-help">Isi nilai bilangan bulat 0 sampai 100. Kosongkan nilai jika tujuan pembelajaran belum dinilai. Rata-rata hanya dihitung dari nilai yang terisi. Keterangan Tercapai jika rata-rata minimal 75.</p>
                    @error('nilai')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror

                    <div class="presensi-table-scroll" role="region" aria-label="Matriks nilai asesmen formatif" tabindex="0">
                        <table class="asesmen-matrix">
                            <caption class="presensi-sr-only">Matriks nilai formatif {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="2" class="asesmen-number">No</th>
                                    <th scope="col" rowspan="2">NIS</th>
                                    <th scope="col" rowspan="2" class="asesmen-name-col">Nama Murid</th>
                                    <th scope="col" rowspan="2" class="asesmen-gender-col">Jenis Kelamin</th>
                                    @foreach ($materials as $material)
                                        <th scope="col" colspan="{{ $material->tujuanPembelajarans->count() + 1 }}" class="asesmen-material" title="{{ $material->materi }}">
                                            {{ $material->materi }}
                                        </th>
                                    @endforeach
                                    <th scope="col" rowspan="2">Rata-rata</th>
                                    <th scope="col" rowspan="2">Keterangan</th>
                                    <th scope="col" rowspan="2">Peringkat</th>
                                </tr>
                                <tr>
                                    @foreach ($materials as $material)
                                        @foreach ($material->tujuanPembelajarans as $purpose)
                                            <th scope="col" class="asesmen-tp" title="{{ $purpose->teks }}">
                                                TP{{ $loop->iteration }}<span class="presensi-sr-only">: {{ $purpose->teks }}</span>
                                            </th>
                                        @endforeach
                                        <th scope="col" class="asesmen-average">Rata-rata</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $summary = $summaries[$student->id];
                                        $gender = $student->jenisKelaminKode();
                                    @endphp
                                    <tr wire:key="asesmen-siswa-{{ $student->id }}">
                                        <td class="asesmen-number">{{ $loop->iteration }}</td>
                                        <td class="asesmen-nis">{{ $student->nis ?: '-' }}</td>
                                        <td class="asesmen-name-cell">{{ $student->nama }}</td>
                                        <td>{{ $gender }}</td>
                                        @foreach ($materials as $material)
                                            @foreach ($material->tujuanPembelajarans as $purpose)
                                                <td class="asesmen-score-cell">
                                                    <label class="presensi-sr-only" for="asesmen-nilai-{{ $student->id }}-{{ $purpose->id }}">Nilai {{ $student->nama }} untuk {{ $purpose->teks }}</label>
                                                    <input
                                                        id="asesmen-nilai-{{ $student->id }}-{{ $purpose->id }}"
                                                        type="number"
                                                        class="asesmen-score"
                                                        min="0"
                                                        max="100"
                                                        step="1"
                                                        inputmode="numeric"
                                                        placeholder="-"
                                                        wire:model.blur="nilai.{{ $student->id }}.{{ $purpose->id }}"
                                                        aria-describedby="asesmen-nilai-error-{{ $student->id }}-{{ $purpose->id }}"
                                                    >
                                                    <div id="asesmen-nilai-error-{{ $student->id }}-{{ $purpose->id }}">
                                                        @error('nilai.'.$student->id.'.'.$purpose->id)
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                            @endforeach
                                            <td class="asesmen-average">
                                                {{ $summary['material_averages'][$material->id] === null ? '-' : number_format($summary['material_averages'][$material->id], 2, ',', '.') }}
                                            </td>
                                        @endforeach
                                        <td class="asesmen-average">
                                            {{ $summary['average'] === null ? '-' : number_format($summary['average'], 2, ',', '.') }}
                                        </td>
                                        <td class="asesmen-status">{{ $summary['status'] }}</td>
                                        <td class="asesmen-rank">{{ $summary['rank'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="asesmen-footer">
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
                    <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan nilai asesmen formatif.</p>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
