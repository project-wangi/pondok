<x-filament-panels::page>
    <div class="presensi-page asesmen-page rekap-page">
        @if ($peminatans->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada jenis peminatan.</p>
                <p class="presensi-help">Minta admin menambahkan jenis peminatan sebelum melihat rekap absensi peminatan.</p>
            </div>
        @else
            <div class="rekap-kelas-bar">
                <p class="rekap-kelas-title">Klik Untuk Pilih Peminatan</p>
                <div class="rekap-kelas-buttons" role="group" aria-label="Pilih jenis peminatan">
                    @foreach ($peminatans as $option)
                        <button
                            type="button"
                            @class(['rekap-kelas-button', 'is-active' => $option->id === $jenisPeminatanId])
                            wire:click="pilihPeminatan({{ $option->id }})"
                            wire:loading.attr="disabled"
                            wire:target="pilihPeminatan"
                            aria-pressed="{{ $option->id === $jenisPeminatanId ? 'true' : 'false' }}"
                        >
                            {{ $option->nama_peminatan }}
                        </button>
                    @endforeach
                </div>
            </div>

            <x-filament::section>
                <div class="rekap-identity">
                    <div class="rekap-identity-left">
                        <p><span class="rekap-identity-label">Peminatan:</span> {{ $peminatan?->nama_peminatan ?? '-' }}</p>
                        <p><span class="rekap-identity-label">Jumlah Pertemuan:</span> {{ count($columns) }}</p>
                    </div>
                    <div class="rekap-identity-right">
                        <p><span class="rekap-identity-label">Laki-laki:</span> {{ $maleCount }}</p>
                        <p><span class="rekap-identity-label">Perempuan:</span> {{ $femaleCount }}</p>
                        <p><span class="rekap-identity-label">Jml:</span> {{ $students->count() }}</p>
                    </div>
                </div>
            </x-filament::section>

            @if ($students->isEmpty())
                <div class="presensi-notice">
                    <p class="presensi-label">Belum ada siswa pada peminatan ini.</p>
                    <p class="presensi-help">Minta admin menghubungkan siswa ke jenis peminatan sebelum melihat rekap.</p>
                </div>
            @elseif ($columns === [])
                <div class="presensi-notice">
                    <p class="presensi-label">Belum ada absensi peminatan tercatat untuk {{ $peminatan?->nama_peminatan ?? 'peminatan ini' }} pada semester {{ $period['semester'] }}.</p>
                    <p class="presensi-help">Rekap akan terisi setelah guru peminatan mencatat kehadiran.</p>
                    <p><a class="asesmen-link" href="{{ $peminatanUrl }}">Buka Absensi Peminatan</a></p>
                </div>
            @else
                <div class="presensi-table-scroll" role="region" aria-label="Rekap absensi peminatan {{ $peminatan?->nama_peminatan }}" tabindex="0">
                    <table class="asesmen-matrix rekap-matrix">
                        <caption class="presensi-sr-only">Rekap absensi peminatan {{ $peminatan?->nama_peminatan }} semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <colgroup>
                            <col class="rekap-col-no">
                            <col class="rekap-col-nis">
                            <col class="rekap-col-nama">
                            <col class="rekap-col-kelas">
                            <col class="rekap-col-lp">
                            @foreach ($columns as $column)
                                <col class="rekap-col-session">
                            @endforeach
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-percent">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="rekap-col-no rekap-sticky rekap-vertical">No. Urut</th>
                                <th scope="col" rowspan="2" class="rekap-col-nis rekap-sticky rekap-vertical">Nomor Induk</th>
                                <th scope="col" rowspan="2" class="rekap-col-nama rekap-sticky rekap-vertical">Nama Murid</th>
                                <th scope="col" rowspan="2" class="rekap-col-kelas rekap-vertical">Kelas</th>
                                <th scope="col" rowspan="2" class="rekap-col-lp rekap-vertical">LP</th>
                                <th scope="col" colspan="{{ count($columns) }}" class="rekap-group">Tanggal Peminatan</th>
                                <th scope="col" colspan="4" class="rekap-group">Absensi</th>
                                <th scope="col" rowspan="2" class="rekap-percent">% Kehadiran</th>
                            </tr>
                            <tr>
                                @foreach ($columns as $column)
                                    <th scope="col" class="rekap-pertemuan">
                                        <span class="rekap-session-number">{{ $column['nomor'] }}</span>
                                        <span class="rekap-session-date">{{ $column['label'] }}</span>
                                        <span class="presensi-sr-only">Pertemuan {{ $column['nomor'] }}, {{ $column['label'] }}</span>
                                    </th>
                                @endforeach
                                <th scope="col" class="rekap-count rekap-head rekap-head-h" title="Hadir">H</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-i" title="Izin">I</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-s" title="Sakit">S</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-a" title="Alpa">A</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="peminatan-siswa-{{ $row['student']->id }}">
                                    <td class="rekap-col-no rekap-sticky">{{ $loop->iteration }}</td>
                                    <td class="rekap-col-nis rekap-sticky">{{ $row['student']->nis ?: '-' }}</td>
                                    <td class="rekap-col-nama rekap-sticky rekap-name">{{ $row['student']->nama }}</td>
                                    <td class="rekap-col-kelas">{{ $row['student']->kelas?->nama_kelas ?? '-' }}</td>
                                    <td class="rekap-col-lp">{{ $row['student']->jenisKelaminKode() }}</td>
                                    @foreach ($columns as $column)
                                        @php
                                            $code = $row['codes'][$column['id']] ?? null;
                                        @endphp
                                        <td @class(['rekap-cell', 'rekap-status-'.strtolower((string) $code) => $code !== null])>{{ $code }}</td>
                                    @endforeach
                                    <td class="rekap-count">{{ $row['counts']['H'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['I'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['S'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['A'] }}</td>
                                    <td class="rekap-percent-cell">{{ $row['percent'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Persentase kehadiran dihitung dari status yang tercatat: Hadir ÷ (Hadir + Izin + Sakit + Alpa) × 100. Pertemuan tanpa presensi tidak menurunkan persentase.</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
