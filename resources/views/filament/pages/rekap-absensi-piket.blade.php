<x-filament-panels::page>
    <div class="presensi-page asesmen-page rekap-page piket-rekap-page">
        @if ($classes->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada data kelas.</p>
                <p class="presensi-help">Minta admin menambahkan kelas sebelum melihat rekap absensi piket.</p>
            </div>
        @else
            <div class="rekap-kelas-bar">
                <label class="rekap-filter" for="rekap-piket-bulan">
                    <span>Bulan</span>
                    <select id="rekap-piket-bulan" wire:change="pilihBulan($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas">
                        @foreach ($bulanOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value === $bulan)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="rekap-filter" for="rekap-piket-tahun">
                    <span>Tahun</span>
                    <select id="rekap-piket-tahun" wire:change="pilihTahun($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas">
                        @foreach ($tahunOptions as $year)
                            <option value="{{ $year }}" @selected($year === $tahun)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="rekap-filter" for="rekap-piket-kelas">
                    <span>Kelas</span>
                    <select id="rekap-piket-kelas" wire:change="pilihKelas($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas">
                        @foreach ($classes as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $kelasId)>{{ $option->nama_kelas }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($class)
                    <div class="rekap-export">
                        <x-filament::button wire:click="ekspor" color="success" icon="heroicon-o-arrow-down-tray">
                            Ekspor Excel
                        </x-filament::button>
                    </div>
                @endif
            </div>

            <x-filament::section>
                <div class="rekap-identity">
                    <div class="rekap-identity-left">
                        <p><span class="rekap-identity-label">Kelas:</span> {{ $class?->nama_kelas ?? '-' }}</p>
                        <p><span class="rekap-identity-label">Jumlah Piket:</span> {{ count($columns) }}</p>
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
                    <p class="presensi-label">Belum ada siswa di kelas ini.</p>
                    <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum melihat rekap absensi piket.</p>
                </div>
            @elseif ($columns === [])
                <div class="presensi-notice">
                    <p class="presensi-label">Belum ada absensi piket tercatat untuk {{ $class?->nama_kelas ?? 'kelas ini' }} pada bulan {{ $bulanLabel }}.</p>
                    <p class="presensi-help">Rekap akan terisi setelah guru piket mencatat absensi masuk dan pulang.</p>
                    @if ($canOpenPiket)
                        <p><a class="asesmen-link" href="{{ $piketUrl }}">Buka Absensi Piket</a></p>
                    @endif
                </div>
            @else
                <p class="presensi-help">Kode masuk: H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa. Kode pulang: P = Pulang, I = Izin, S = Sakit, A = Alpa. Kode atas adalah absen masuk, kode bawah adalah absen pulang.</p>

                <div class="presensi-table-scroll" role="region" aria-label="Rekap absensi piket kelas {{ $class?->nama_kelas }} bulan {{ $bulanLabel }}" tabindex="0">
                    <table class="asesmen-matrix rekap-matrix piket-matrix">
                        <caption class="presensi-sr-only">Rekap absensi piket kelas {{ $class?->nama_kelas }} bulan {{ $bulanLabel }} semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <colgroup>
                            <col class="rekap-col-no">
                            <col class="rekap-col-nis">
                            <col class="rekap-col-nama">
                            <col class="rekap-col-lp">
                            @foreach ($columns as $column)
                                <col class="rekap-col-session">
                            @endforeach
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="rekap-col-count">
                            <col class="piket-col-pulang">
                            <col class="rekap-col-percent">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="rekap-col-no rekap-sticky rekap-vertical">No. Urut</th>
                                <th scope="col" rowspan="2" class="rekap-col-nis rekap-sticky rekap-vertical">Nomor Induk</th>
                                <th scope="col" rowspan="2" class="rekap-col-nama rekap-sticky rekap-vertical">Nama Murid</th>
                                <th scope="col" rowspan="2" class="rekap-col-lp rekap-sticky rekap-vertical">LP</th>
                                <th scope="col" colspan="{{ count($columns) }}" class="rekap-group">Tanggal Piket</th>
                                <th scope="col" colspan="5" class="rekap-group">Absensi Masuk</th>
                                <th scope="col" rowspan="2" class="piket-col-pulang">Pulang</th>
                                <th scope="col" rowspan="2" class="rekap-percent">% Kehadiran</th>
                            </tr>
                            <tr>
                                @foreach ($columns as $column)
                                    <th scope="col" class="rekap-pertemuan">
                                        <span class="rekap-session-number">{{ $column['nomor'] }}</span>
                                        <span class="rekap-session-date">{{ $column['label'] }}</span>
                                        <span class="presensi-sr-only">Piket {{ $column['nomor'] }}, {{ $column['label'] }}</span>
                                    </th>
                                @endforeach
                                <th scope="col" class="rekap-count piket-head piket-head-h" title="Hadir">H</th>
                                <th scope="col" class="rekap-count piket-head piket-head-t" title="Terlambat">T</th>
                                <th scope="col" class="rekap-count piket-head piket-head-i" title="Izin">I</th>
                                <th scope="col" class="rekap-count piket-head piket-head-s" title="Sakit">S</th>
                                <th scope="col" class="rekap-count piket-head piket-head-a" title="Alpa">A</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="piket-siswa-{{ $row['student']->id }}">
                                    <td class="rekap-col-no rekap-sticky">{{ $loop->iteration }}</td>
                                    <td class="rekap-col-nis rekap-sticky">{{ $row['student']->nis ?: '-' }}</td>
                                    <td class="rekap-col-nama rekap-sticky rekap-name">{{ $row['student']->nama }}</td>
                                    <td class="rekap-col-lp rekap-sticky">{{ $row['student']->jenisKelaminKode() }}</td>
                                    @foreach ($columns as $column)
                                        @php
                                            $cell = $row['codes'][$column['id']] ?? ['masuk' => null, 'pulang' => null];
                                        @endphp
                                        <td class="piket-cell">
                                            <div class="piket-stack">
                                                @if ($cell['masuk'] !== null)
                                                    <span @class(['piket-code', 'piket-'.strtolower($cell['masuk'])]) title="Masuk">{{ $cell['masuk'] }}</span>
                                                @endif
                                                @if ($cell['pulang'] !== null)
                                                    <span @class(['piket-code', 'piket-'.strtolower($cell['pulang'])]) title="Pulang">{{ $cell['pulang'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="rekap-count">{{ $row['masuk_counts']['H'] }}</td>
                                    <td class="rekap-count">{{ $row['masuk_counts']['T'] }}</td>
                                    <td class="rekap-count">{{ $row['masuk_counts']['I'] }}</td>
                                    <td class="rekap-count">{{ $row['masuk_counts']['S'] }}</td>
                                    <td class="rekap-count">{{ $row['masuk_counts']['A'] }}</td>
                                    <td class="piket-col-pulang">{{ $row['pulang_count'] }}</td>
                                    <td class="rekap-percent-cell">{{ $row['percent'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Persentase kehadiran dihitung dari absen masuk yang tercatat: (Hadir + Terlambat) ÷ total absen masuk × 100. Tanggal tanpa presensi tidak menurunkan persentase.</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
