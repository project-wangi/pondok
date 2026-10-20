<x-filament-panels::page>
    <div class="presensi-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Presensi belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $periodLabel = 'Periode berjalan: semester '.$activePeriod['semester'].' tahun ajaran '.$activePeriod['tahun_ajaran'].'.';
        @endphp

        <x-filament::section heading="Jadwal mengajar" :description="$periodLabel">
            <div class="presensi-stack">
                <div class="presensi-date">
                    <label class="presensi-label" for="presensi-tanggal">Tanggal</label>
                    <x-filament::input.wrapper :valid="! $errors->has('tanggal')">
                        <x-filament::input
                            id="presensi-tanggal"
                            type="date"
                            wire:model.live="tanggal"
                            max="{{ $today }}"
                            required
                            aria-describedby="presensi-tanggal-help presensi-tanggal-error"
                            aria-invalid="{{ $errors->has('tanggal') ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            wire:target="tanggal,pilihJadwal,lihatPresensi,simpan"
                        />
                    </x-filament::input.wrapper>
                    <p class="presensi-help" id="presensi-tanggal-help">Pilih hari ini untuk mengisi atau tanggal sebelumnya untuk melihat riwayat.</p>
                    <div id="presensi-tanggal-error">
                        @error('tanggal')
                            <p class="presensi-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($isToday)
                    @if ($jadwals->isEmpty())
                        <div class="presensi-notice">
                            <p class="presensi-label">Tidak ada jadwal mengajar pada periode berjalan.</p>
                            <p class="presensi-help">Hubungi admin jika jadwal Anda belum tercantum. Sesi yang sudah tersimpan tetap ditampilkan pada tabel.</p>
                        </div>
                    @else
                        <p class="presensi-help">Tekan Absen untuk mengisi atau memperbarui presensi pada jadwal hari ini.</p>
                    @endif
                @elseif ($tanggal <= $today)
                    <p class="presensi-notice">Mode riwayat. Presensi pada tanggal sebelumnya hanya dapat dibaca.</p>
                @else
                    <p class="presensi-notice">Presensi untuk tanggal mendatang tidak tersedia. Pilih hari ini atau tanggal sebelumnya.</p>
                @endif

                <p class="presensi-help" role="status" wire:loading wire:target="tanggal,pilihJadwal,lihatPresensi">Memuat jadwal dan presensi...</p>
                <div class="presensi-table-scroll" role="region" aria-label="Tabel jadwal dan sesi presensi" tabindex="0" wire:loading.attr="aria-busy" wire:target="tanggal">
                    <table class="presensi-table presensi-schedules-table">
                        <caption class="presensi-sr-only">Jadwal dan sesi presensi tanggal {{ $tanggal }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="presensi-number">No</th>
                                <th scope="col">Waktu</th>
                                <th scope="col">Kelas</th>
                                <th scope="col">Mata pelajaran</th>
                                <th scope="col">Pengajar</th>
                                <th scope="col">Hadir</th>
                                <th scope="col"><abbr title="Persentase kehadiran">%</abbr></th>
                                <th scope="col">Absen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tableRows as $row)
                                <tr wire:key="presensi-jadwal-{{ $row['key'] }}">
                                    <td class="presensi-number">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="presensi-cell-stack presensi-time">
                                            <time datetime="{{ $tanggal }}">{{ $tanggal }}</time>
                                            <span class="presensi-help">{{ substr((string) $row['jam_mulai'], 0, 5) }} - {{ substr((string) $row['jam_selesai'], 0, 5) }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $row['nama_kelas'] }}</td>
                                    <td class="presensi-label">{{ $row['nama_mapel'] }}</td>
                                    <td>{{ $row['nama_guru'] }}</td>
                                    <td>
                                        @if ($row['sesi_id'] !== null)
                                            <div class="presensi-cell-stack">
                                                <span class="presensi-numeric">{{ $row['hadir'] }}/{{ $row['total'] }}</span>
                                                @if ($row['dicatat_pada'])
                                                    <span class="presensi-help">Dicatat {{ $row['dicatat_pada']->format('H:i') }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="presensi-help">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td class="presensi-numeric">
                                        @if ($row['persentase'] !== null)
                                            {{ number_format($row['persentase'], 2, ',', '.') }}%
                                        @else
                                            <span class="presensi-help">{{ $row['sesi_id'] !== null ? 'Tidak tersedia' : 'Belum diisi' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['jadwal_id'] !== null && $isToday)
                                            <x-filament::button
                                                type="button"
                                                wire:click="pilihJadwal({{ $row['jadwal_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihJadwal,lihatPresensi,simpan"
                                                :loading-indicator="false"
                                                aria-label="Absen {{ $row['nama_mapel'] }}, kelas {{ $row['nama_kelas'] }}, {{ substr((string) $row['jam_mulai'], 0, 5) }}"
                                            >
                                                Absen
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                wire:click="lihatPresensi({{ $row['sesi_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihJadwal,lihatPresensi,simpan"
                                                :loading-indicator="false"
                                                aria-label="Lihat presensi {{ $row['nama_mapel'] }}, kelas {{ $row['nama_kelas'] }}, {{ substr((string) $row['jam_mulai'], 0, 5) }}"
                                            >
                                                Lihat presensi
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="presensi-empty">
                                        <p class="presensi-label">{{ $isToday ? 'Belum ada jadwal atau sesi tersimpan hari ini.' : 'Belum ada sesi tersimpan pada tanggal ini.' }}</p>
                                        <p class="presensi-help">{{ $isToday ? 'Hubungi admin untuk memeriksa jadwal mengajar Anda.' : 'Pilih hari ini untuk mengisi presensi atau tanggal lain untuk melihat riwayat.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Satu sesi dicatat untuk setiap jadwal per hari. Menyimpan ulang presensi hari ini memperbarui sesi yang sama. Catatan sesi ini bukan perhitungan penggajian.</p>
            </div>
        </x-filament::section>

        <x-filament::modal
            id="presensi-siswa-modal"
            width="7xl"
            sticky-header
            sticky-footer
            :close-by-clicking-away="false"
            :close-by-escaping="true"
            :close-button="true"
        >
            <x-slot name="heading">{{ $jadwal && $isToday ? 'Presensi siswa' : 'Riwayat presensi siswa' }}</x-slot>
            <x-slot name="description">
                @if ($jadwal && $isToday)
                    {{ $jadwal->mataPelajaran?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }} · {{ $jadwal->kelas?->nama_kelas ?? 'Kelas tidak tersedia' }} · {{ $jadwal->guru?->user?->name ?? 'Guru tidak tersedia' }} · {{ $tanggal }} · {{ substr((string) $jadwal->jam_mulai, 0, 5) }} - {{ substr((string) $jadwal->jam_selesai, 0, 5) }}
                @elseif ($modalSession)
                    {{ $modalSession->nama_mapel }} · {{ $modalSession->nama_kelas }} · {{ $modalSession->nama_guru }} · {{ $tanggal }} · {{ substr((string) $modalSession->jam_mulai, 0, 5) }} - {{ substr((string) $modalSession->jam_selesai, 0, 5) }}
                @endif
            </x-slot>

            <div class="presensi-stack">
                @if ($errors->any())
                    <div class="presensi-errors" role="alert">
                        <p class="presensi-label">Presensi belum tersimpan. Periksa isian berikut.</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="presensi-help" role="status" wire:loading wire:target="pilihJadwal,lihatPresensi">Memuat daftar siswa...</p>

                @if ($jadwal && $isToday)
                    <form id="presensi-form" wire:submit="simpan" class="presensi-stack" wire:key="presensi-form-{{ $tanggal }}-{{ $jadwal->id }}">
                        <div class="presensi-toolbar">
                            <p class="presensi-help" id="presensi-isian-help">Pilih status setiap siswa. Catatan opsional, maksimal 500 karakter. Perubahan tersimpan setelah menekan Simpan presensi.</p>
                            <x-filament::button
                                type="button"
                                color="gray"
                                :loading-indicator="false"
                                :disabled="empty($roster)"
                                wire:click="tandaiSemuaHadir"
                                wire:loading.attr="disabled"
                                wire:target="tandaiSemuaHadir,simpan,tutupPresensi"
                            >
                                Tandai semua Hadir
                            </x-filament::button>
                        </div>
                        <fieldset class="presensi-fields" aria-describedby="presensi-isian-help" wire:loading.attr="disabled" wire:target="tandaiSemuaHadir,simpan,tutupPresensi">
                            <legend class="presensi-sr-only">Kehadiran siswa</legend>
                            <div class="presensi-table-scroll" role="region" aria-label="Tabel isian presensi siswa" tabindex="0">
                                <table class="presensi-table presensi-students-table">
                                    <caption class="presensi-sr-only">Isian presensi siswa kelas {{ $jadwal->kelas?->nama_kelas }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="presensi-number">No</th>
                                            <th scope="col">NIS</th>
                                            <th scope="col">Nama Siswa</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($roster as $key => $siswa)
                                            <tr wire:key="presensi-siswa-{{ $jadwal->id }}-{{ $key }}">
                                                <td class="presensi-number">{{ $loop->iteration }}</td>
                                                <td>{{ $siswa['nis'] ?: 'Belum tercatat' }}</td>
                                                <td class="presensi-label">{{ $siswa['nama'] }}</td>
                                                <td>
                                                    <label class="presensi-sr-only" for="presensi-status-{{ $jadwal->id }}-{{ $key }}">Status kehadiran {{ $siswa['nama'] }} (wajib)</label>
                                                    <x-filament::input.wrapper :valid="! $errors->has('kehadiran.'.$key.'.status')">
                                                        <x-filament::input.select
                                                            id="presensi-status-{{ $jadwal->id }}-{{ $key }}"
                                                            wire:model="kehadiran.{{ $key }}.status"
                                                            required
                                                            aria-describedby="presensi-status-error-{{ $jadwal->id }}-{{ $key }}"
                                                            aria-invalid="{{ $errors->has('kehadiran.'.$key.'.status') ? 'true' : 'false' }}"
                                                        >
                                                            <option value="">Pilih status</option>
                                                            @foreach ($statuses as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </x-filament::input.select>
                                                    </x-filament::input.wrapper>
                                                    <div id="presensi-status-error-{{ $jadwal->id }}-{{ $key }}">
                                                        @error('kehadiran.'.$key.'.status')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td>
                                                    <label class="presensi-sr-only" for="presensi-catatan-{{ $jadwal->id }}-{{ $key }}">Catatan untuk {{ $siswa['nama'] }} (opsional)</label>
                                                    <x-filament::input.wrapper :valid="! $errors->has('kehadiran.'.$key.'.catatan')">
                                                        <x-filament::input
                                                            id="presensi-catatan-{{ $jadwal->id }}-{{ $key }}"
                                                            type="text"
                                                            wire:model="kehadiran.{{ $key }}.catatan"
                                                            maxlength="500"
                                                            aria-describedby="presensi-catatan-error-{{ $jadwal->id }}-{{ $key }}"
                                                            aria-invalid="{{ $errors->has('kehadiran.'.$key.'.catatan') ? 'true' : 'false' }}"
                                                        />
                                                    </x-filament::input.wrapper>
                                                    <div id="presensi-catatan-error-{{ $jadwal->id }}-{{ $key }}">
                                                        @error('kehadiran.'.$key.'.catatan')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="presensi-empty">
                                                    <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                                                    <p class="presensi-help">Minta admin menempatkan siswa ke kelas ini sebelum menyimpan presensi.</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                        <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan presensi. Tunggu hingga proses selesai.</p>
                        <p class="presensi-help" role="status" wire:loading wire:target="tandaiSemuaHadir">Menandai semua siswa Hadir. Perubahan belum disimpan.</p>
                        <button type="submit" class="presensi-sr-only" tabindex="-1" aria-hidden="true">Simpan presensi</button>
                    </form>
                @elseif ($modalSession)
                    <p class="presensi-notice">Mode riwayat. Data siswa dan jadwal merupakan salinan saat pencatatan dan hanya dapat dibaca.</p>
                    <div class="presensi-table-scroll" role="region" aria-label="Tabel riwayat presensi siswa" tabindex="0">
                        <table class="presensi-table presensi-students-table">
                            <caption class="presensi-sr-only">Riwayat presensi siswa kelas {{ $modalSession->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="presensi-number">No</th>
                                    <th scope="col">NIS</th>
                                    <th scope="col">Nama Siswa</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($modalSession->presensiMurids as $presensi)
                                    <tr wire:key="presensi-history-{{ $modalSession->id }}-{{ $presensi->id }}">
                                        <td class="presensi-number">{{ $loop->iteration }}</td>
                                        <td>{{ $presensi->nis ?: 'Belum tercatat' }}</td>
                                        <td class="presensi-label">{{ $presensi->nama_murid }}</td>
                                        <td>{{ $statuses[$presensi->status] ?? $presensi->status }}</td>
                                        <td class="presensi-note">{{ filled($presensi->catatan) ? $presensi->catatan : 'Tanpa catatan' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="presensi-empty">Tidak ada rincian presensi siswa pada sesi ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="presensi-help">Dicatat: {{ $modalSession->dicatat_pada?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</p>
                @else
                    <p class="presensi-notice">Tidak ada presensi yang dipilih. Tutup jendela ini, lalu pilih Absen atau Lihat presensi pada tabel jadwal.</p>
                @endif
            </div>

            <x-slot name="footer">
                <div class="presensi-modal-footer">
                    <p class="presensi-help">{{ $jadwal && $isToday ? 'Presensi hari ini masih dapat diedit dan disimpan kembali.' : 'Riwayat presensi hanya dapat dibaca.' }}</p>
                    <div class="presensi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="tutupPresensi"
                            wire:loading.attr="disabled"
                            wire:target="simpan,tandaiSemuaHadir,tutupPresensi"
                            :loading-indicator="false"
                        >
                            Tutup
                        </x-filament::button>
                        @if ($jadwal && $isToday)
                            <x-filament::button
                                type="button"
                                wire:click="simpan"
                                :loading-indicator="false"
                                :disabled="empty($roster)"
                                wire:loading.attr="disabled"
                                wire:target="simpan,tandaiSemuaHadir,tutupPresensi"
                            >
                                <span wire:loading.remove wire:target="simpan">Simpan presensi</span>
                                <span wire:loading wire:target="simpan">Menyimpan...</span>
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
