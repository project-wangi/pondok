<x-filament-panels::page>
    <div class="presensi-page peminatan-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Absensi peminatan belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filament::section heading="Absensi peminatan" description="Satu absensi dicatat untuk setiap jenis peminatan per hari.">
            <div class="presensi-stack">
                <div class="presensi-date">
                    <label class="presensi-label" for="peminatan-tanggal">Tanggal</label>
                    <x-filament::input.wrapper :valid="! $errors->has('tanggal')">
                        <x-filament::input
                            id="peminatan-tanggal"
                            type="date"
                            wire:model.live="tanggal"
                            max="{{ $today }}"
                            required
                            aria-describedby="peminatan-tanggal-help peminatan-tanggal-error"
                            aria-invalid="{{ $errors->has('tanggal') ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            wire:target="tanggal,pilihPeminatan,lihatAbsen,simpan"
                        />
                    </x-filament::input.wrapper>
                    <p class="presensi-help" id="peminatan-tanggal-help">Pilih hari ini atau maksimal 7 hari ke belakang untuk mengisi. Tanggal yang lebih lama hanya dapat dibaca sebagai riwayat.</p>
                    <div id="peminatan-tanggal-error">
                        @error('tanggal')
                            <p class="presensi-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if (! $hasSchedule)
                    <p class="presensi-notice">Anda tidak memiliki jadwal peminatan pada tanggal ini. Hubungi admin jika jadwal peminatan Anda belum tercantum.</p>
                @elseif (! $isEditable)
                    <p class="presensi-notice">Mode riwayat. Absensi peminatan yang lebih lama dari 7 hari hanya dapat dibaca.</p>
                @else
                    <p class="presensi-help">Tekan Absen pada peminatan untuk mengisi kehadiran siswa yang terdaftar pada peminatan tersebut.</p>
                @endif

                <p class="presensi-help" role="status" wire:loading wire:target="tanggal,pilihPeminatan,lihatAbsen">Memuat data absensi peminatan...</p>
                <div class="presensi-table-scroll" role="region" aria-label="Tabel absensi peminatan" tabindex="0" wire:loading.attr="aria-busy" wire:target="tanggal">
                    <table class="presensi-table">
                        <caption class="presensi-sr-only">Absensi peminatan tanggal {{ $tanggal }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="presensi-number">No</th>
                                <th scope="col">Waktu</th>
                                <th scope="col">Jenis Peminatan</th>
                                <th scope="col">Siswa</th>
                                <th scope="col">Hadir</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr wire:key="peminatan-{{ $row['key'] }}">
                                    <td class="presensi-number">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="presensi-cell-stack presensi-time">
                                            <time datetime="{{ $tanggal }}">{{ $tanggal }}</time>
                                            <span class="presensi-help">
                                                @if (filled($row['jam_mulai']) && filled($row['jam_selesai']))
                                                    {{ substr((string) $row['jam_mulai'], 0, 5) }} - {{ substr((string) $row['jam_selesai'], 0, 5) }}
                                                @else
                                                    Waktu belum diatur
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="presensi-label">{{ $row['nama_peminatan'] }}</td>
                                    <td class="presensi-numeric">{{ $row['student_count'] }}</td>
                                    <td class="presensi-numeric">
                                        @if ($row['student_count'] > 0)
                                            {{ $row['hadir_count'] }}/{{ $row['student_count'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['student_count'] === 0)
                                            <span class="presensi-help">Belum ada siswa</span>
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="presensi-help">Belum diisi</span>
                                        @elseif ($row['filled_count'] >= $row['student_count'])
                                            <span class="presensi-label">Lengkap</span>
                                        @else
                                            <div class="presensi-cell-stack">
                                                <span class="presensi-label">Sebagian</span>
                                                <span class="presensi-help">{{ $row['filled_count'] }}/{{ $row['student_count'] }} siswa</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['peminatan_id'] !== null && $isEditable && $row['student_count'] > 0)
                                            <x-filament::button
                                                type="button"
                                                size="sm"
                                                wire:click="pilihPeminatan({{ $row['peminatan_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihPeminatan,lihatAbsen,simpan"
                                                :loading-indicator="false"
                                                aria-label="Absen peminatan {{ $row['nama_peminatan'] }}"
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
                                                wire:target="tanggal,pilihPeminatan,lihatAbsen,simpan"
                                                :loading-indicator="false"
                                                aria-label="Lihat absensi peminatan {{ $row['nama_peminatan'] }}"
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
                                        <p class="presensi-label">{{ $hasSchedule ? 'Belum ada peminatan yang dapat diabsen pada tanggal ini.' : 'Tidak ada data absensi peminatan pada tanggal ini.' }}</p>
                                        <p class="presensi-help">Pilih tanggal lain atau hubungi admin untuk memeriksa jadwal peminatan Anda.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Absensi peminatan diisi satu kali per pertemuan. Menyimpan ulang memperbarui data pada tanggal yang sama. Pengisian susulan dibatasi maksimal 7 hari ke belakang.</p>
            </div>
        </x-filament::section>

        <x-filament::modal
            id="absensi-peminatan-modal"
            width="7xl"
            sticky-header
            sticky-footer
            :close-by-clicking-away="false"
            :close-by-escaping="true"
            :close-button="true"
        >
            <x-slot name="heading">{{ $selection && $isEditable ? 'Absensi peminatan siswa' : 'Riwayat absensi peminatan' }}</x-slot>
            <x-slot name="description">
                @if ($selection && $isEditable)
                    {{ $selection['nama'] }} · {{ $tanggal }} · {{ substr((string) $selection['jam_mulai'], 0, 5) }} - {{ substr((string) $selection['jam_selesai'], 0, 5) }}
                @elseif ($modalSession)
                    {{ $modalSession->nama_peminatan }} · {{ $tanggal }}@if (filled($modalSession->jam_mulai) && filled($modalSession->jam_selesai)) · {{ substr((string) $modalSession->jam_mulai, 0, 5) }} - {{ substr((string) $modalSession->jam_selesai, 0, 5) }}@endif · dicatat oleh {{ $modalSession->nama_guru }}
                @endif
            </x-slot>

            <div class="presensi-stack">
                @if ($errors->any())
                    <div class="presensi-errors" role="alert">
                        <p class="presensi-label">Absensi peminatan belum tersimpan. Periksa isian berikut.</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="presensi-help" role="status" wire:loading wire:target="pilihPeminatan,lihatAbsen">Memuat daftar siswa...</p>

                @if ($selection && $isEditable)
                    <form wire:submit="simpan" class="presensi-stack" wire:key="peminatan-form-{{ $tanggal }}-{{ $selection['id'] }}">
                        <div class="presensi-toolbar">
                            <p class="presensi-help">Pilih status kehadiran untuk setiap siswa, lalu simpan.</p>
                            <div class="presensi-actions">
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    :outlined="true"
                                    :loading-indicator="false"
                                    :disabled="empty($roster)"
                                    wire:click="tandaiSemuaHadir"
                                    wire:loading.attr="disabled"
                                    wire:target="tandaiSemuaHadir,simpan,tutupAbsen"
                                >
                                    Tandai semua Hadir
                                </x-filament::button>
                            </div>
                        </div>

                        <div class="presensi-table-scroll" role="region" aria-label="Tabel isian absensi peminatan siswa" tabindex="0">
                            <table class="presensi-table presensi-students-table">
                                <caption class="presensi-sr-only">Isian absensi peminatan {{ $selection['nama'] }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="presensi-number">No</th>
                                        <th scope="col">NIS</th>
                                        <th scope="col">Nama Siswa</th>
                                        <th scope="col">Kelas</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($roster as $key => $siswa)
                                        <tr wire:key="peminatan-siswa-{{ $selection['id'] }}-{{ $key }}">
                                            <td class="presensi-number">{{ $loop->iteration }}</td>
                                            <td>{{ $siswa['nis'] ?: 'Belum tercatat' }}</td>
                                            <td class="presensi-label">{{ $siswa['nama'] }}</td>
                                            <td>{{ $siswa['nama_kelas'] ?: '-' }}</td>
                                            <td>
                                                <label class="presensi-sr-only" for="peminatan-status-{{ $selection['id'] }}-{{ $key }}">Status kehadiran {{ $siswa['nama'] }}</label>
                                                <x-filament::input.wrapper :valid="! $errors->has('kehadiran.'.$key.'.status')">
                                                    <x-filament::input.select
                                                        id="peminatan-status-{{ $selection['id'] }}-{{ $key }}"
                                                        wire:model="kehadiran.{{ $key }}.status"
                                                        required
                                                        aria-describedby="peminatan-status-error-{{ $selection['id'] }}-{{ $key }}"
                                                        aria-invalid="{{ $errors->has('kehadiran.'.$key.'.status') ? 'true' : 'false' }}"
                                                    >
                                                        <option value="">Pilih status</option>
                                                        @foreach ($statuses as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </x-filament::input.select>
                                                </x-filament::input.wrapper>
                                                <div id="peminatan-status-error-{{ $selection['id'] }}-{{ $key }}">
                                                    @error('kehadiran.'.$key.'.status')
                                                        <p class="presensi-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="presensi-empty">
                                                <p class="presensi-label">Belum ada siswa pada peminatan ini.</p>
                                                <p class="presensi-help">Minta admin mengatur jenis peminatan siswa sebelum menyimpan absensi.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan absensi peminatan. Tunggu hingga proses selesai.</p>
                        <button type="submit" class="presensi-sr-only" tabindex="-1" aria-hidden="true">Simpan absensi peminatan</button>
                    </form>
                @elseif ($modalSession)
                    <p class="presensi-notice">Mode riwayat. Data siswa dan status merupakan salinan saat pencatatan dan hanya dapat dibaca.</p>
                    <div class="presensi-table-scroll" role="region" aria-label="Tabel riwayat absensi peminatan siswa" tabindex="0">
                        <table class="presensi-table presensi-students-table">
                            <caption class="presensi-sr-only">Riwayat absensi peminatan {{ $modalSession->nama_peminatan }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="presensi-number">No</th>
                                    <th scope="col">NIS</th>
                                    <th scope="col">Nama Siswa</th>
                                    <th scope="col">Kelas</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($modalSession->murids->sortBy('nama_murid') as $record)
                                    <tr wire:key="peminatan-history-{{ $modalSession->id }}-{{ $record->id }}">
                                        <td class="presensi-number">{{ $loop->iteration }}</td>
                                        <td>{{ $record->nis ?: 'Belum tercatat' }}</td>
                                        <td class="presensi-label">{{ $record->nama_murid }}</td>
                                        <td>{{ $record->nama_kelas ?: '-' }}</td>
                                        <td>{{ $statuses[$record->status] ?? $record->status }}</td>
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
                    <p class="presensi-notice">Tidak ada absensi yang dipilih. Tutup jendela ini, lalu pilih Absen pada tabel peminatan.</p>
                @endif
            </div>

            <x-slot name="footer">
                <div class="presensi-modal-footer">
                    <p class="presensi-help">{{ $selection && $isEditable ? 'Absensi peminatan pada tanggal ini masih dapat diedit dan disimpan kembali.' : 'Riwayat absensi peminatan hanya dapat dibaca.' }}</p>
                    <div class="presensi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="tutupAbsen"
                            wire:loading.attr="disabled"
                            wire:target="simpan,tandaiSemuaHadir,tutupAbsen"
                            :loading-indicator="false"
                        >
                            Tutup
                        </x-filament::button>
                        @if ($selection && $isEditable)
                            <x-filament::button
                                type="button"
                                wire:click="simpan"
                                :loading-indicator="false"
                                :disabled="empty($roster)"
                                wire:loading.attr="disabled"
                                wire:target="simpan,tandaiSemuaHadir,tutupAbsen"
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
