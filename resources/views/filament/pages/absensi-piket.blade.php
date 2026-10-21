<x-filament-panels::page>
    <div class="presensi-page piket-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Absensi piket belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filament::section heading="Absensi piket" :description="'Periode: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            <div class="presensi-stack">
                <div class="presensi-date">
                    <label class="presensi-label" for="piket-tanggal">Tanggal</label>
                    <x-filament::input.wrapper :valid="! $errors->has('tanggal')">
                        <x-filament::input
                            id="piket-tanggal"
                            type="date"
                            wire:model.live="tanggal"
                            max="{{ $today }}"
                            required
                            aria-describedby="piket-tanggal-help piket-tanggal-error"
                            aria-invalid="{{ $errors->has('tanggal') ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            wire:target="tanggal,pilihKelas,lihatAbsen,simpan"
                        />
                    </x-filament::input.wrapper>
                    <p class="presensi-help" id="piket-tanggal-help">Pilih hari ini atau maksimal 7 hari ke belakang untuk mengisi. Tanggal yang lebih lama hanya dapat dibaca sebagai riwayat.</p>
                    <div id="piket-tanggal-error">
                        @error('tanggal')
                            <p class="presensi-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if (! $hasPiket)
                    <p class="presensi-notice">Anda tidak memiliki jadwal piket pada tanggal ini. Hubungi admin jika jadwal piket Anda belum tercantum.</p>
                @elseif (! $isEditable)
                    <p class="presensi-notice">Mode riwayat. Absensi piket yang lebih lama dari 7 hari hanya dapat dibaca.</p>
                @else
                    <p class="presensi-help">Tekan Absen pada kelas untuk mengisi absen masuk dan pulang siswa kelas tersebut.</p>
                @endif

                <p class="presensi-help" role="status" wire:loading wire:target="tanggal,pilihKelas,lihatAbsen">Memuat data absensi piket...</p>
                <div class="presensi-table-scroll" role="region" aria-label="Tabel absensi piket per kelas" tabindex="0" wire:loading.attr="aria-busy" wire:target="tanggal">
                    <table class="presensi-table">
                        <caption class="presensi-sr-only">Absensi piket per kelas tanggal {{ $tanggal }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="presensi-number">No</th>
                                <th scope="col">Kelas</th>
                                <th scope="col">Siswa</th>
                                <th scope="col">Masuk</th>
                                <th scope="col">Pulang</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr wire:key="piket-{{ $row['key'] }}">
                                    <td class="presensi-number">{{ $loop->iteration }}</td>
                                    <td class="presensi-label">{{ $row['nama_kelas'] }}</td>
                                    <td class="presensi-numeric">{{ $row['student_count'] }}</td>
                                    <td class="presensi-numeric">
                                        @if ($row['student_count'] > 0)
                                            {{ $row['masuk_count'] }}/{{ $row['student_count'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="presensi-numeric">
                                        @if ($row['student_count'] > 0)
                                            {{ $row['pulang_count'] }}/{{ $row['student_count'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['student_count'] === 0)
                                            <span class="presensi-help">Belum ada siswa</span>
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="presensi-help">Belum diisi</span>
                                        @elseif ($row['masuk_count'] >= $row['student_count'] && $row['pulang_count'] >= $row['student_count'])
                                            <span class="presensi-label">Lengkap</span>
                                        @else
                                            <div class="presensi-cell-stack">
                                                <span class="presensi-label">Sebagian</span>
                                                <span class="presensi-help">{{ $row['filled_count'] }}/{{ $row['student_count'] }} siswa</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['kelas_id'] !== null && $isEditable && $row['student_count'] > 0)
                                            <x-filament::button
                                                type="button"
                                                size="sm"
                                                wire:click="pilihKelas({{ $row['kelas_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihKelas,lihatAbsen,simpan"
                                                :loading-indicator="false"
                                                aria-label="Absen piket kelas {{ $row['nama_kelas'] }}"
                                            >
                                                Absen
                                            </x-filament::button>
                                        @elseif ($row['absen_id'] !== null)
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                size="sm"
                                                wire:click="lihatAbsen({{ $row['absen_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihKelas,lihatAbsen,simpan"
                                                :loading-indicator="false"
                                                aria-label="Lihat absensi piket kelas {{ $row['nama_kelas'] }}"
                                            >
                                                Lihat absen
                                            </x-filament::button>
                                        @else
                                            <span class="presensi-help">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="presensi-empty">
                                        <p class="presensi-label">{{ $hasPiket ? 'Belum ada kelas yang dapat diabsen pada tanggal ini.' : 'Tidak ada data absensi piket pada tanggal ini.' }}</p>
                                        <p class="presensi-help">Pilih tanggal lain atau hubungi admin untuk memeriksa jadwal piket Anda.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Satu absensi dicatat untuk setiap kelas per hari. Menyimpan ulang memperbarui data pada tanggal yang sama. Pengisian susulan dibatasi maksimal 7 hari ke belakang.</p>
            </div>
        </x-filament::section>

        <x-filament::modal
            id="absensi-piket-modal"
            width="7xl"
            sticky-header
            sticky-footer
            :close-by-clicking-away="false"
            :close-by-escaping="true"
            :close-button="true"
        >
            <x-slot name="heading">{{ $kelasSelection && $isEditable ? 'Absensi piket siswa' : 'Riwayat absensi piket siswa' }}</x-slot>
            <x-slot name="description">
                @if ($kelasSelection && $isEditable)
                    {{ $kelasSelection->nama_kelas }} · {{ $tanggal }} · {{ $period['semester'] }} {{ $period['tahun_ajaran'] }}
                @elseif ($modalSession)
                    {{ $modalSession->nama_kelas }} · {{ $tanggal }} · dicatat oleh {{ $modalSession->nama_guru }}
                @endif
            </x-slot>

            <div class="presensi-stack">
                @if ($errors->any())
                    <div class="presensi-errors" role="alert">
                        <p class="presensi-label">Absensi piket belum tersimpan. Periksa isian berikut.</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="presensi-help" role="status" wire:loading wire:target="pilihKelas,lihatAbsen">Memuat daftar siswa...</p>

                @if ($kelasSelection && $isEditable)
                    <form wire:submit="simpan" class="presensi-stack" wire:key="piket-form-{{ $tanggal }}-{{ $kelasSelection->id }}">
                        <div class="presensi-toolbar">
                            <p class="presensi-help">Pilih status absen masuk dan pulang untuk setiap siswa. Kosongkan jika belum dicatat, lalu simpan.</p>
                            <div class="presensi-actions">
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    :outlined="true"
                                    :loading-indicator="false"
                                    :disabled="empty($roster)"
                                    wire:click="tandaiSemuaMasuk"
                                    wire:loading.attr="disabled"
                                    wire:target="tandaiSemuaMasuk,tandaiSemuaPulang,simpan,tutupAbsen"
                                >
                                    Semua masuk Hadir
                                </x-filament::button>
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    :outlined="true"
                                    :loading-indicator="false"
                                    :disabled="empty($roster)"
                                    wire:click="tandaiSemuaPulang"
                                    wire:loading.attr="disabled"
                                    wire:target="tandaiSemuaMasuk,tandaiSemuaPulang,simpan,tutupAbsen"
                                >
                                    Semua pulang Pulang
                                </x-filament::button>
                            </div>
                        </div>

                        <div class="presensi-table-scroll" role="region" aria-label="Tabel isian absensi piket siswa" tabindex="0">
                            <table class="presensi-table presensi-students-table">
                                <caption class="presensi-sr-only">Isian absensi piket siswa kelas {{ $kelasSelection->nama_kelas }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="presensi-number">No</th>
                                        <th scope="col">NIS</th>
                                        <th scope="col">Nama Siswa</th>
                                        <th scope="col">Absen Masuk</th>
                                        <th scope="col">Absen Pulang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($roster as $key => $siswa)
                                        <tr wire:key="piket-siswa-{{ $kelasSelection->id }}-{{ $key }}">
                                            <td class="presensi-number">{{ $loop->iteration }}</td>
                                            <td>{{ $siswa['nis'] ?: 'Belum tercatat' }}</td>
                                            <td class="presensi-label">{{ $siswa['nama'] }}</td>
                                            <td>
                                                <label class="presensi-sr-only" for="piket-masuk-{{ $kelasSelection->id }}-{{ $key }}">Absen masuk {{ $siswa['nama'] }}</label>
                                                <x-filament::input.wrapper :valid="! $errors->has('kehadiran.'.$key.'.masuk')">
                                                    <x-filament::input.select
                                                        id="piket-masuk-{{ $kelasSelection->id }}-{{ $key }}"
                                                        wire:model="kehadiran.{{ $key }}.masuk"
                                                        aria-describedby="piket-masuk-error-{{ $kelasSelection->id }}-{{ $key }}"
                                                        aria-invalid="{{ $errors->has('kehadiran.'.$key.'.masuk') ? 'true' : 'false' }}"
                                                    >
                                                        <option value="">Belum dicatat</option>
                                                        @foreach ($statusMasuk as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </x-filament::input.select>
                                                </x-filament::input.wrapper>
                                                <div id="piket-masuk-error-{{ $kelasSelection->id }}-{{ $key }}">
                                                    @error('kehadiran.'.$key.'.masuk')
                                                        <p class="presensi-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </td>
                                            <td>
                                                <label class="presensi-sr-only" for="piket-pulang-{{ $kelasSelection->id }}-{{ $key }}">Absen pulang {{ $siswa['nama'] }}</label>
                                                <x-filament::input.wrapper :valid="! $errors->has('kehadiran.'.$key.'.pulang')">
                                                    <x-filament::input.select
                                                        id="piket-pulang-{{ $kelasSelection->id }}-{{ $key }}"
                                                        wire:model="kehadiran.{{ $key }}.pulang"
                                                        aria-describedby="piket-pulang-error-{{ $kelasSelection->id }}-{{ $key }}"
                                                        aria-invalid="{{ $errors->has('kehadiran.'.$key.'.pulang') ? 'true' : 'false' }}"
                                                    >
                                                        <option value="">Belum dicatat</option>
                                                        @foreach ($statusPulang as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </x-filament::input.select>
                                                </x-filament::input.wrapper>
                                                <div id="piket-pulang-error-{{ $kelasSelection->id }}-{{ $key }}">
                                                    @error('kehadiran.'.$key.'.pulang')
                                                        <p class="presensi-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="presensi-empty">
                                                <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                                                <p class="presensi-help">Minta admin menempatkan siswa ke kelas ini sebelum menyimpan absensi.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan absensi piket. Tunggu hingga proses selesai.</p>
                        <button type="submit" class="presensi-sr-only" tabindex="-1" aria-hidden="true">Simpan absensi piket</button>
                    </form>
                @elseif ($modalSession)
                    <p class="presensi-notice">Mode riwayat. Data siswa dan status merupakan salinan saat pencatatan dan hanya dapat dibaca.</p>
                    <div class="presensi-table-scroll" role="region" aria-label="Tabel riwayat absensi piket siswa" tabindex="0">
                        <table class="presensi-table presensi-students-table">
                            <caption class="presensi-sr-only">Riwayat absensi piket kelas {{ $modalSession->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="presensi-number">No</th>
                                    <th scope="col">NIS</th>
                                    <th scope="col">Nama Siswa</th>
                                    <th scope="col">Masuk</th>
                                    <th scope="col">Pulang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($modalSession->murids->sortBy('nama_murid') as $record)
                                    <tr wire:key="piket-history-{{ $modalSession->id }}-{{ $record->id }}">
                                        <td class="presensi-number">{{ $loop->iteration }}</td>
                                        <td>{{ $record->nis ?: 'Belum tercatat' }}</td>
                                        <td class="presensi-label">{{ $record->nama_murid }}</td>
                                        <td>{{ $record->masuk ? ($statusMasuk[$record->masuk] ?? $record->masuk) : 'Belum dicatat' }}</td>
                                        <td>{{ $record->pulang ? ($statusPulang[$record->pulang] ?? $record->pulang) : 'Belum dicatat' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="presensi-empty">Tidak ada rincian absensi siswa pada sesi ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="presensi-help">Dicatat: {{ $modalSession->dicatat_pada?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</p>
                @else
                    <p class="presensi-notice">Tidak ada absensi yang dipilih. Tutup jendela ini, lalu pilih Absen pada tabel kelas.</p>
                @endif
            </div>

            <x-slot name="footer">
                <div class="presensi-modal-footer">
                    <p class="presensi-help">{{ $kelasSelection && $isEditable ? 'Absensi piket pada tanggal ini masih dapat diedit dan disimpan kembali.' : 'Riwayat absensi piket hanya dapat dibaca.' }}</p>
                    <div class="presensi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="tutupAbsen"
                            wire:loading.attr="disabled"
                            wire:target="simpan,tandaiSemuaMasuk,tandaiSemuaPulang,tutupAbsen"
                            :loading-indicator="false"
                        >
                            Tutup
                        </x-filament::button>
                        @if ($kelasSelection && $isEditable)
                            <x-filament::button
                                type="button"
                                wire:click="simpan"
                                :loading-indicator="false"
                                :disabled="empty($roster)"
                                wire:loading.attr="disabled"
                                wire:target="simpan,tandaiSemuaMasuk,tandaiSemuaPulang,tutupAbsen"
                            >
                                Simpan absen
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
