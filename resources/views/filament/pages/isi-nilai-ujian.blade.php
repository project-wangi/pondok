<x-filament-panels::page>
    <div class="presensi-page asesmen-page ujian-page">
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
                Kembali ke daftar nilai ujian
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
                <div class="asesmen-identity-item">
                    <span class="presensi-help">Jumlah Ujian</span>
                    <span class="asesmen-identity-value">{{ $componentCount }}</span>
                </div>
            </div>
        </x-filament::section>

        @if ($students->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum mengisi nilai ujian.</p>
            </div>
        @else
            <form wire:submit="simpan" class="asesmen-form">
                <x-filament::section heading="Komponen ujian" description="Tambahkan jenis ujian untuk kelas dan mata pelajaran ini, misalnya UTS atau UAS.">
                    <div class="ujian-komponen-list">
                        @foreach ($komponens as $index => $komponen)
                            <div class="ujian-komponen-row" wire:key="ujian-komponen-{{ $index }}">
                                <label class="ujian-komponen-label" for="ujian-nama-{{ $index }}">Nama ujian {{ $loop->iteration }}</label>
                                <input
                                    id="ujian-nama-{{ $index }}"
                                    type="text"
                                    class="ujian-name-input"
                                    wire:model.blur="komponens.{{ $index }}.nama"
                                    maxlength="100"
                                    required
                                    aria-describedby="ujian-nama-error-{{ $index }}"
                                    aria-invalid="{{ $errors->has('komponens.'.$index.'.nama') ? 'true' : 'false' }}"
                                >
                                <div class="ujian-komponen-action">
                                    @if (count($komponens) > 1)
                                        <x-filament::button
                                            type="button"
                                            color="danger"
                                            size="sm"
                                            :outlined="true"
                                            :loading-indicator="false"
                                            wire:click="hapusKomponen({{ $index }})"
                                            wire:loading.attr="disabled"
                                            wire:target="hapusKomponen,tambahKomponen,simpan"
                                            aria-label="Hapus ujian {{ $loop->iteration }}"
                                        >
                                            Hapus
                                        </x-filament::button>
                                    @endif
                                </div>
                                <div id="ujian-nama-error-{{ $index }}" class="ujian-komponen-error">
                                    @error('komponens.'.$index.'.nama')
                                        <p class="presensi-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endforeach

                        <div class="ujian-komponen-footer">
                            <p class="presensi-help">Maksimal {{ $maxKomponen }} ujian per kelas dan mata pelajaran.</p>
                            <x-filament::button
                                type="button"
                                color="gray"
                                :outlined="true"
                                :loading-indicator="false"
                                wire:click="tambahKomponen"
                                wire:loading.attr="disabled"
                                wire:target="tambahKomponen,hapusKomponen,simpan"
                                :disabled="count($komponens) >= $maxKomponen"
                            >
                                Tambah ujian
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Matriks nilai ujian" :description="'Isi nilai dan catatan per ujian untuk kelas '.($class?->nama_kelas ?? '-').'.'">
                    <p class="presensi-help">Isi nilai bilangan bulat 0 sampai 100. Kolom catatan boleh dikosongkan. Nilai dan catatan yang kosong tidak disimpan.</p>
                    @error('komponens')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror
                    @error('nilai')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror
                    @error('catatan')
                        <p class="presensi-error">{{ $message }}</p>
                    @enderror

                    <div class="presensi-table-scroll" role="region" aria-label="Matriks nilai ujian" tabindex="0">
                        <table class="asesmen-matrix ujian-matrix">
                            <caption class="presensi-sr-only">Matriks nilai ujian {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="2" class="asesmen-number">No</th>
                                    <th scope="col" rowspan="2">NIS</th>
                                    <th scope="col" rowspan="2" class="asesmen-name-col">Nama Murid</th>
                                    @foreach ($komponens as $index => $komponen)
                                        <th scope="col" colspan="2" class="ujian-component">
                                            {{ trim((string) ($komponen['nama'] ?? '')) !== '' ? $komponen['nama'] : 'Ujian '.($index + 1) }}
                                        </th>
                                    @endforeach
                                    <th scope="col" rowspan="2">Rata-rata</th>
                                </tr>
                                <tr>
                                    @foreach ($komponens as $komponen)
                                        <th scope="col" class="ujian-score-col">Nilai</th>
                                        <th scope="col" class="ujian-note-col">Catatan</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr wire:key="ujian-siswa-{{ $student->id }}">
                                        <td class="asesmen-number">{{ $loop->iteration }}</td>
                                        <td class="asesmen-nis">{{ $student->nis ?: '-' }}</td>
                                        <td class="asesmen-name-cell">{{ $student->nama }}</td>
                                        @foreach ($komponens as $index => $komponen)
                                            <td class="asesmen-score-cell">
                                                <label class="presensi-sr-only" for="ujian-nilai-{{ $index }}-{{ $student->id }}">Nilai {{ $student->nama }} untuk {{ trim((string) ($komponen['nama'] ?? '')) !== '' ? $komponen['nama'] : 'ujian '.($index + 1) }}</label>
                                                <input
                                                    id="ujian-nilai-{{ $index }}-{{ $student->id }}"
                                                    type="number"
                                                    class="asesmen-score"
                                                    min="0"
                                                    max="100"
                                                    step="1"
                                                    inputmode="numeric"
                                                    placeholder="-"
                                                    wire:model.blur="nilai.{{ $index }}.{{ $student->id }}"
                                                    aria-describedby="ujian-nilai-error-{{ $index }}-{{ $student->id }}"
                                                >
                                                <div id="ujian-nilai-error-{{ $index }}-{{ $student->id }}">
                                                    @error('nilai.'.$index.'.'.$student->id)
                                                        <p class="presensi-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </td>
                                            <td class="ujian-note-cell">
                                                <label class="presensi-sr-only" for="ujian-catatan-{{ $index }}-{{ $student->id }}">Catatan {{ $student->nama }} untuk {{ trim((string) ($komponen['nama'] ?? '')) !== '' ? $komponen['nama'] : 'ujian '.($index + 1) }}</label>
                                                <input
                                                    id="ujian-catatan-{{ $index }}-{{ $student->id }}"
                                                    type="text"
                                                    class="ujian-note"
                                                    maxlength="500"
                                                    placeholder="Catatan (opsional)"
                                                    wire:model.blur="catatan.{{ $index }}.{{ $student->id }}"
                                                    aria-describedby="ujian-catatan-error-{{ $index }}-{{ $student->id }}"
                                                >
                                                <div id="ujian-catatan-error-{{ $index }}-{{ $student->id }}">
                                                    @error('catatan.'.$index.'.'.$student->id)
                                                        <p class="presensi-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </td>
                                        @endforeach
                                        <td class="asesmen-average">
                                            {{ $averages[$student->id] === null ? '-' : number_format($averages[$student->id], 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="asesmen-footer">
                        <p class="presensi-help">Rata-rata dihitung dari nilai yang terisi saja. Perubahan baru tersimpan setelah menekan Simpan nilai.</p>
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
                    <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan nilai ujian.</p>
                </x-filament::section>
            </form>
        @endif
    </div>
</x-filament-panels::page>
