<x-filament-panels::page>
    <div class="presensi-page jurnal-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Jurnal belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filament::section heading="Jadwal mengajar" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            <div class="presensi-stack">
                <div class="presensi-date">
                    <label class="presensi-label" for="jurnal-tanggal">Tanggal</label>
                    <x-filament::input.wrapper :valid="! $errors->has('tanggal')">
                        <x-filament::input
                            id="jurnal-tanggal"
                            type="date"
                            wire:model.live="tanggal"
                            max="{{ $today }}"
                            required
                            aria-describedby="jurnal-tanggal-help jurnal-tanggal-error"
                            aria-invalid="{{ $errors->has('tanggal') ? 'true' : 'false' }}"
                            wire:loading.attr="disabled"
                            wire:target="tanggal,pilihJadwal,lihatJurnal,simpan"
                        />
                    </x-filament::input.wrapper>
                    <p class="presensi-help" id="jurnal-tanggal-help">Pilih hari ini untuk mengisi jurnal atau tanggal sebelumnya untuk melihat riwayat.</p>
                    <div id="jurnal-tanggal-error">
                        @error('tanggal')
                            <p class="presensi-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if (! $isToday)
                    <p class="presensi-notice">Mode riwayat. Jurnal pada tanggal sebelumnya hanya dapat dibaca.</p>
                @endif

                <p class="presensi-help" role="status" wire:loading wire:target="tanggal,pilihJadwal,lihatJurnal">Memuat jadwal dan jurnal...</p>
                <div class="presensi-table-scroll" role="region" aria-label="Tabel jadwal dan jurnal mengajar" tabindex="0" wire:loading.attr="aria-busy" wire:target="tanggal">
                    <table class="presensi-table jurnal-table">
                        <caption class="presensi-sr-only">Jadwal dan jurnal mengajar tanggal {{ $tanggal }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="presensi-number jurnal-number-col">No</th>
                                <th scope="col" class="jurnal-time-col">Waktu</th>
                                <th scope="col" class="jurnal-class-col">Kelas</th>
                                <th scope="col" class="jurnal-subject-col">Mata pelajaran</th>
                                <th scope="col" class="jurnal-status-col">Jurnal</th>
                                <th scope="col" class="jurnal-action-col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tableRows as $row)
                                <tr wire:key="jurnal-jadwal-{{ $row['key'] }}">
                                    <td class="presensi-number">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="presensi-cell-stack presensi-time">
                                            <time datetime="{{ $tanggal }}">{{ $tanggalLabel }}</time>
                                            <span class="presensi-help">{{ substr((string) $row['jam_mulai'], 0, 5) }} - {{ substr((string) $row['jam_selesai'], 0, 5) }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $row['nama_kelas'] }}</td>
                                    <td class="presensi-label">{{ $row['nama_mapel'] }}</td>
                                    <td>
                                        @if ($row['jumlah_baris'] > 0)
                                            <div class="presensi-cell-stack">
                                                <span class="presensi-label">Sudah diisi</span>
                                                <span class="presensi-help">{{ $row['jumlah_baris'] }} baris</span>
                                            </div>
                                        @else
                                            <span class="presensi-help">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td class="jurnal-action-col">
                                        @if ($row['jadwal_id'] !== null && $isToday)
                                            <x-filament::button
                                                type="button"
                                                size="sm"
                                                wire:click="pilihJadwal({{ $row['jadwal_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihJadwal,lihatJurnal,simpan"
                                                :loading-indicator="false"
                                                aria-label="Isi jurnal {{ $row['nama_mapel'] }}, kelas {{ $row['nama_kelas'] }}, {{ substr((string) $row['jam_mulai'], 0, 5) }}"
                                            >
                                                Isi jurnal
                                            </x-filament::button>
                                        @elseif ($row['jurnal_id'] !== null)
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                size="sm"
                                                wire:click="lihatJurnal({{ $row['jurnal_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tanggal,pilihJadwal,lihatJurnal,simpan"
                                                :loading-indicator="false"
                                                aria-label="Lihat jurnal {{ $row['nama_mapel'] }}, kelas {{ $row['nama_kelas'] }}, {{ substr((string) $row['jam_mulai'], 0, 5) }}"
                                            >
                                                Lihat jurnal
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="presensi-empty">
                                        <p class="presensi-label">{{ $isToday ? 'Belum ada jadwal atau jurnal tersimpan hari ini.' : 'Belum ada jurnal tersimpan pada tanggal ini.' }}</p>
                                        <p class="presensi-help">{{ $isToday ? 'Jurnal mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.' : 'Pilih hari ini untuk mengisi jurnal atau tanggal lain untuk melihat riwayat.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Jurnal dicatat satu kali untuk setiap jadwal per hari. Simpan ulang untuk memperbarui jurnal hari ini. Pilih lingkup materi dari data yang sudah diisi sebelumnya.</p>
            </div>
        </x-filament::section>

        <x-filament::modal
            id="jurnal-mengajar-modal"
            width="7xl"
            sticky-header
            sticky-footer
            :close-by-clicking-away="false"
            :close-by-escaping="true"
            :close-button="true"
        >
            <x-slot name="heading">{{ $jadwal ? 'Jurnal mengajar' : 'Riwayat jurnal mengajar' }}</x-slot>

            <div class="presensi-stack">
                @if ($errors->any())
                    <div class="presensi-errors" role="alert">
                        <p class="presensi-label">Jurnal belum tersimpan. Periksa isian berikut.</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($jadwal !== null || $jurnal !== null)
                    <dl class="jurnal-summary">
                        <div>
                            <dt>Mapel</dt>
                            <dd>{{ $jadwal?->mataPelajaran?->nama_mapel ?? $jurnal?->nama_mapel }}</dd>
                        </div>
                        <div>
                            <dt>Guru</dt>
                            <dd>{{ $jadwal?->guru?->user?->name ?? $jurnal?->nama_guru }}</dd>
                        </div>
                        <div>
                            <dt>Tahun Ajaran</dt>
                            <dd>{{ $jadwal?->tahun_ajaran ?? $jurnal?->tahun_ajaran }}</dd>
                        </div>
                        <div>
                            <dt>Semester</dt>
                            <dd>{{ $jadwal?->semester ?? $jurnal?->semester }}</dd>
                        </div>
                        <div>
                            <dt>Kelas</dt>
                            <dd>{{ $jadwal?->kelas?->nama_kelas ?? $jurnal?->nama_kelas }}</dd>
                        </div>
                        <div>
                            <dt>Tanggal dan waktu</dt>
                            <dd>{{ $tanggalLabel }}, {{ substr((string) ($jadwal?->jam_mulai ?? $jurnal?->jam_mulai), 0, 5) }} - {{ substr((string) ($jadwal?->jam_selesai ?? $jurnal?->jam_selesai), 0, 5) }}</dd>
                        </div>
                    </dl>
                @endif

                <p class="presensi-help" role="status" wire:loading wire:target="pilihJadwal,lihatJurnal">Memuat jurnal...</p>

                @if ($jadwal !== null)
                    @if ($materials->isEmpty())
                        <div class="presensi-notice">
                            <p class="presensi-label">Belum ada lingkup materi dengan tujuan pembelajaran untuk {{ $jadwal->mataPelajaran?->nama_mapel ?? 'mata pelajaran ini' }}.</p>
                            <p class="presensi-help">Jurnal membutuhkan lingkup materi dan tujuan pembelajaran. Isi data tersebut terlebih dahulu.</p>
                            <p><a class="jurnal-link" href="{{ $lingkupUrl }}">Buka Input Lingkup Materi dan Tujuan Pembelajaran</a></p>
                        </div>
                    @else
                        <form id="jurnal-form" wire:submit="simpan" class="presensi-stack" wire:key="jurnal-form-{{ $tanggal }}-{{ $jadwal->id }}">
                            <p class="presensi-help">Pilih lingkup materi, lalu pilih salah satu tujuan pembelajaran yang tersedia. Kegiatan pembelajaran wajib diisi. Catatan dan tindak lanjut bersifat opsional.</p>
                            <div class="presensi-table-scroll" role="region" aria-label="Tabel isian jurnal mengajar" tabindex="0">
                                <table class="presensi-table jurnal-form-table">
                                    <caption class="presensi-sr-only">Isian jurnal mengajar {{ $jadwal->mataPelajaran?->nama_mapel }} kelas {{ $jadwal->kelas?->nama_kelas }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="presensi-number">No</th>
                                            <th scope="col" class="jurnal-cell-lm">Lingkup Materi</th>
                                            <th scope="col" class="jurnal-cell-tp">Tujuan Pembelajaran</th>
                                            <th scope="col" class="jurnal-cell-activity">Kegiatan Pembelajaran</th>
                                            <th scope="col" class="jurnal-cell-note">Catatan</th>
                                            <th scope="col" class="jurnal-cell-note">Tindak Lanjut</th>
                                            <th scope="col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($baris as $index => $row)
                                            @php
                                                $lmId = is_array($row) ? ($row['lingkup_materi_id'] ?? null) : null;
                                                $goals = filled($lmId) ? ($tujuanOptions[$lmId] ?? []) : [];
                                            @endphp
                                            <tr wire:key="jurnal-baris-{{ $index }}">
                                                <td class="presensi-number">{{ $loop->iteration }}</td>
                                                <td>
                                                    <label class="presensi-sr-only" for="jurnal-lm-{{ $index }}">Lingkup materi baris {{ $loop->iteration }} (wajib)</label>
                                                    <x-filament::input.wrapper :valid="! $errors->has('baris.'.$index.'.lingkup_materi_id')">
                                                        <x-filament::input.select
                                                            id="jurnal-lm-{{ $index }}"
                                                            wire:model.live="baris.{{ $index }}.lingkup_materi_id"
                                                            required
                                                            aria-describedby="jurnal-lm-error-{{ $index }}"
                                                            aria-invalid="{{ $errors->has('baris.'.$index.'.lingkup_materi_id') ? 'true' : 'false' }}"
                                                        >
                                                            <option value="">Pilih lingkup materi</option>
                                                            @foreach ($materials as $material)
                                                                <option value="{{ $material->id }}">{{ $material->materi }}</option>
                                                            @endforeach
                                                        </x-filament::input.select>
                                                    </x-filament::input.wrapper>
                                                    <div id="jurnal-lm-error-{{ $index }}">
                                                        @error('baris.'.$index.'.lingkup_materi_id')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td>
                                                    <label class="presensi-sr-only" for="jurnal-tp-{{ $index }}">Tujuan pembelajaran baris {{ $loop->iteration }} (wajib)</label>
                                                    <x-filament::input.wrapper :valid="! $errors->has('baris.'.$index.'.tujuan_pembelajaran_id')">
                                                        <x-filament::input.select
                                                            id="jurnal-tp-{{ $index }}"
                                                            wire:model="baris.{{ $index }}.tujuan_pembelajaran_id"
                                                            required
                                                            :disabled="$goals === []"
                                                            aria-describedby="jurnal-tp-error-{{ $index }}"
                                                            aria-invalid="{{ $errors->has('baris.'.$index.'.tujuan_pembelajaran_id') ? 'true' : 'false' }}"
                                                        >
                                                            <option value="">{{ $goals === [] ? 'Pilih lingkup materi dulu' : 'Pilih tujuan pembelajaran' }}</option>
                                                            @foreach ($goals as $goalId => $goal)
                                                                <option value="{{ $goalId }}">{{ $goal }}</option>
                                                            @endforeach
                                                        </x-filament::input.select>
                                                    </x-filament::input.wrapper>
                                                    <div id="jurnal-tp-error-{{ $index }}">
                                                        @error('baris.'.$index.'.tujuan_pembelajaran_id')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td class="jurnal-cell-activity">
                                                    <label class="presensi-sr-only" for="jurnal-kegiatan-{{ $index }}">Kegiatan pembelajaran baris {{ $loop->iteration }} (wajib)</label>
                                                    <textarea
                                                        id="jurnal-kegiatan-{{ $index }}"
                                                        class="jurnal-textarea"
                                                        wire:model="baris.{{ $index }}.kegiatan"
                                                        rows="3"
                                                        maxlength="2000"
                                                        required
                                                        aria-describedby="jurnal-kegiatan-error-{{ $index }}"
                                                        aria-invalid="{{ $errors->has('baris.'.$index.'.kegiatan') ? 'true' : 'false' }}"
                                                    ></textarea>
                                                    <div id="jurnal-kegiatan-error-{{ $index }}">
                                                        @error('baris.'.$index.'.kegiatan')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td class="jurnal-cell-note">
                                                    <label class="presensi-sr-only" for="jurnal-catatan-{{ $index }}">Catatan baris {{ $loop->iteration }}</label>
                                                    <textarea
                                                        id="jurnal-catatan-{{ $index }}"
                                                        class="jurnal-textarea"
                                                        wire:model="baris.{{ $index }}.catatan"
                                                        rows="3"
                                                        maxlength="1000"
                                                        aria-describedby="jurnal-catatan-error-{{ $index }}"
                                                        aria-invalid="{{ $errors->has('baris.'.$index.'.catatan') ? 'true' : 'false' }}"
                                                    ></textarea>
                                                    <div id="jurnal-catatan-error-{{ $index }}">
                                                        @error('baris.'.$index.'.catatan')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td class="jurnal-cell-note">
                                                    <label class="presensi-sr-only" for="jurnal-tindak-{{ $index }}">Tindak lanjut baris {{ $loop->iteration }}</label>
                                                    <textarea
                                                        id="jurnal-tindak-{{ $index }}"
                                                        class="jurnal-textarea"
                                                        wire:model="baris.{{ $index }}.tindak_lanjut"
                                                        rows="3"
                                                        maxlength="1000"
                                                        aria-describedby="jurnal-tindak-error-{{ $index }}"
                                                        aria-invalid="{{ $errors->has('baris.'.$index.'.tindak_lanjut') ? 'true' : 'false' }}"
                                                    ></textarea>
                                                    <div id="jurnal-tindak-error-{{ $index }}">
                                                        @error('baris.'.$index.'.tindak_lanjut')
                                                            <p class="presensi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td class="jurnal-row-action">
                                                    @if (count($baris) > 1)
                                                        <x-filament::button
                                                            type="button"
                                                            color="danger"
                                                            size="sm"
                                                            :outlined="true"
                                                            :loading-indicator="false"
                                                            wire:click="hapusBaris({{ $index }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="hapusBaris,tambahBaris,simpan"
                                                            aria-label="Hapus baris jurnal {{ $loop->iteration }}"
                                                        >
                                                            Hapus
                                                        </x-filament::button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="presensi-toolbar">
                                <p class="presensi-help">Tambahkan baris jika satu pertemuan mencakup lebih dari satu lingkup materi.</p>
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    :outlined="true"
                                    :loading-indicator="false"
                                    wire:click="tambahBaris"
                                    wire:loading.attr="disabled"
                                    wire:target="tambahBaris,hapusBaris,simpan"
                                >
                                    Tambah baris
                                </x-filament::button>
                            </div>
                            <button type="submit" class="presensi-sr-only" tabindex="-1" aria-hidden="true">Simpan jurnal</button>
                            <p class="presensi-help" role="status" wire:loading wire:target="simpan">Menyimpan jurnal. Tunggu hingga proses selesai.</p>
                        </form>
                    @endif
                @elseif ($jurnal !== null)
                    <p class="presensi-notice">Mode riwayat. Jurnal ini merupakan salinan saat pencatatan dan hanya dapat dibaca.</p>
                    <div class="presensi-table-scroll" role="region" aria-label="Tabel riwayat jurnal mengajar" tabindex="0">
                        <table class="presensi-table jurnal-form-table">
                            <caption class="presensi-sr-only">Riwayat jurnal mengajar {{ $jurnal->nama_mapel }} kelas {{ $jurnal->nama_kelas }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="presensi-number">No</th>
                                    <th scope="col">Lingkup Materi</th>
                                    <th scope="col">Tujuan Pembelajaran</th>
                                    <th scope="col">Kegiatan Pembelajaran</th>
                                    <th scope="col">Catatan</th>
                                    <th scope="col">Tindak Lanjut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($jurnal->baris as $row)
                                    <tr wire:key="jurnal-riwayat-{{ $jurnal->id }}-{{ $row->id }}">
                                        <td class="presensi-number">{{ $loop->iteration }}</td>
                                        <td>{{ $row->lingkup_materi }}</td>
                                        <td>{{ $row->tujuan_pembelajaran }}</td>
                                        <td class="jurnal-history-text">{{ $row->kegiatan }}</td>
                                        <td class="jurnal-history-text">{{ $row->catatan ?: 'Tanpa catatan' }}</td>
                                        <td class="jurnal-history-text">{{ $row->tindak_lanjut ?: 'Tanpa tindak lanjut' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="presensi-empty">Tidak ada rincian jurnal pada sesi ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="presensi-help">Dicatat: {{ $jurnal->dicatat_pada?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</p>
                @else
                    <p class="presensi-notice">Tidak ada jurnal yang dipilih. Tutup jendela ini, lalu pilih Isi jurnal atau Lihat jurnal pada tabel jadwal.</p>
                @endif
            </div>

            <x-slot name="footer">
                <div class="presensi-modal-footer">
                    <p class="presensi-help">{{ $jadwal !== null ? 'Jurnal hari ini masih dapat diperbarui.' : 'Riwayat jurnal hanya dapat dibaca.' }}</p>
                    <div class="presensi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="tutupJurnal"
                            wire:loading.attr="disabled"
                            wire:target="simpan,tambahBaris,hapusBaris,tutupJurnal"
                            :loading-indicator="false"
                        >
                            Tutup
                        </x-filament::button>
                        @if ($jadwal !== null && $materials->isNotEmpty())
                            <x-filament::button
                                type="button"
                                wire:click="simpan"
                                :loading-indicator="false"
                                wire:loading.attr="disabled"
                                wire:target="simpan,tambahBaris,hapusBaris,tutupJurnal"
                            >
                                <span wire:loading.remove wire:target="simpan">Simpan jurnal</span>
                                <span wire:loading wire:target="simpan">Menyimpan...</span>
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
