<x-filament-panels::page>
    <div class="presensi-page asesmen-page rekap-page">
        @if ($classes->isEmpty())
            <div class="presensi-notice">
                <p class="presensi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                <p class="presensi-help">Rekap absensi mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
            </div>
        @else
            <div class="rekap-kelas-bar">
                <label class="rekap-filter" for="rekap-bulan">
                    <span>Bulan</span>
                    <select id="rekap-bulan" wire:change="pilihBulan($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas,pilihMapel">
                        @foreach ($bulanOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value === $bulan)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="rekap-filter" for="rekap-tahun">
                    <span>Tahun</span>
                    <select id="rekap-tahun" wire:change="pilihTahun($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas,pilihMapel">
                        @foreach ($tahunOptions as $year)
                            <option value="{{ $year }}" @selected($year === $tahun)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="rekap-filter" for="rekap-kelas">
                    <span>Kelas</span>
                    <select id="rekap-kelas" wire:change="pilihKelas($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas,pilihMapel">
                        @foreach ($classes as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $kelasId)>{{ $option->nama_kelas }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($subjects->count() > 1)
                    <label class="rekap-filter" for="rekap-mapel">
                        <span>Mata Pelajaran</span>
                        <select id="rekap-mapel" wire:change="pilihMapel($event.target.value)" wire:loading.attr="disabled" wire:target="pilihBulan,pilihTahun,pilihKelas,pilihMapel">
                            @foreach ($subjects as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $mataPelajaranId)>{{ $option->nama_mapel }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($class && $subject)
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
                        <p><span class="rekap-identity-label">Mapel:</span> {{ $subject?->nama_mapel ?? '-' }}</p>
                        <p><span class="rekap-identity-label">Guru:</span> {{ $guruName ?? '-' }}</p>
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
                    <p class="presensi-help">Minta admin menempatkan siswa ke kelas sebelum melihat rekap absensi.</p>
                </div>
            @elseif ($columns === [])
                <div class="presensi-notice">
                    <p class="presensi-label">Belum ada presensi tercatat untuk {{ $subject?->nama_mapel ?? 'mata pelajaran ini' }} di {{ $class?->nama_kelas ?? 'kelas ini' }} pada bulan {{ $bulanLabel }}.</p>
                    <p class="presensi-help">Rekap akan terisi setelah guru mencatat presensi pada menu Presensi Siswa.</p>
                    @if ($canOpenPresensi)
                        <p><a class="asesmen-link" href="{{ $presensiUrl }}">Buka Presensi Siswa</a></p>
                    @endif
                </div>
            @else
                <div class="presensi-table-scroll" role="region" aria-label="Rekap absensi {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }} bulan {{ $bulanLabel }}" tabindex="0">
                    <table class="asesmen-matrix rekap-matrix">
                        <caption class="presensi-sr-only">Rekap absensi {{ $subject?->nama_mapel }} kelas {{ $class?->nama_kelas }} bulan {{ $bulanLabel }} semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
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
                            <col class="rekap-col-percent">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="rekap-col-no rekap-sticky rekap-vertical">No. Urut</th>
                                <th scope="col" rowspan="2" class="rekap-col-nis rekap-sticky rekap-vertical">Nomor Induk</th>
                                <th scope="col" rowspan="2" class="rekap-col-nama rekap-sticky rekap-vertical">Nama Murid</th>
                                <th scope="col" rowspan="2" class="rekap-col-lp rekap-sticky rekap-vertical">LP</th>
                                <th scope="col" colspan="{{ count($columns) }}" class="rekap-group">Tanggal-Pertemuan ke-</th>
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
                                <th scope="col" class="rekap-count rekap-head rekap-head-s" title="Sakit">S</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-i" title="Izin">I</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-a" title="Alpa">A</th>
                                <th scope="col" class="rekap-count rekap-head rekap-head-h" title="Hadir">H</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="rekap-siswa-{{ $row['student']->id }}">
                                    <td class="rekap-col-no rekap-sticky">{{ $loop->iteration }}</td>
                                    <td class="rekap-col-nis rekap-sticky">{{ $row['student']->nis ?: '-' }}</td>
                                    <td class="rekap-col-nama rekap-sticky rekap-name">{{ $row['student']->nama }}</td>
                                    <td class="rekap-col-lp rekap-sticky">{{ $row['student']->jenisKelaminKode() }}</td>
                                    @foreach ($columns as $column)
                                        @php
                                            $code = $row['statuses'][$column['id']] ?? null;
                                        @endphp
                                        <td @class(['rekap-cell', 'rekap-status-'.strtolower((string) $code) => $code !== null])>{{ $code }}</td>
                                    @endforeach
                                    <td class="rekap-count">{{ $row['counts']['S'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['I'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['A'] }}</td>
                                    <td class="rekap-count">{{ $row['counts']['H'] }}</td>
                                    <td class="rekap-percent-cell">{{ $row['percent'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="presensi-help">Persentase kehadiran dihitung dari jumlah status yang tercatat (Hadir + Sakit + Izin + Alpa). Pertemuan yang belum memiliki presensi dianggap belum diisi dan tidak menurunkan persentase.</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
