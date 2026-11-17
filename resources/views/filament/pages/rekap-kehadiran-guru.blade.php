<x-filament-panels::page>
    <div class="presensi-page asesmen-page rekap-page guru-rekap-page">
        <div class="rekap-kelas-bar">
            <label class="rekap-filter" for="rekap-guru-bulan">
                <span>Bulan</span>
                <select
                    id="rekap-guru-bulan"
                    wire:change="pilihBulan($event.target.value)"
                    wire:loading.attr="disabled"
                    wire:target="pilihBulan,pilihTahun"
                >
                    @foreach ($bulanOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === $bulan)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="rekap-filter" for="rekap-guru-tahun">
                <span>Tahun</span>
                <select
                    id="rekap-guru-tahun"
                    wire:change="pilihTahun($event.target.value)"
                    wire:loading.attr="disabled"
                    wire:target="pilihBulan,pilihTahun"
                >
                    @foreach ($tahunOptions as $year)
                        <option value="{{ $year }}" @selected($year === $tahun)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            @if ($laporan['columns'] !== [])
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
                    <p><span class="rekap-identity-label">Periode:</span> {{ $laporan['periode'] }}</p>
                    <p><span class="rekap-identity-label">Hari tercatat:</span> {{ $laporan['hariTercatat'] }}</p>
                    <p><span class="rekap-identity-label">Total hari hadir:</span> {{ $laporan['totals']['hadir'] }}</p>
                </div>
                <div class="rekap-identity-right">
                    <p><span class="rekap-identity-label">Guru hadir:</span> {{ $laporan['guruHadir'] }}</p>
                    <p><span class="rekap-identity-label">Jumlah guru:</span> {{ $laporan['guruTotal'] }}</p>
                </div>
            </div>
        </x-filament::section>

        @if ($laporan['rows'] === [])
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada data guru.</p>
                <p class="presensi-help">Tambahkan guru terlebih dahulu sebelum melihat rekap kehadiran.</p>
            </div>
        @elseif ($laporan['columns'] === [])
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada kehadiran guru tercatat pada bulan {{ $laporan['periode'] }}.</p>
                <p class="presensi-help">
                    Kehadiran diambil dari sesi mengajar yang sudah berpresensi, absensi guru harian,
                    piket, peminatan, dan rapat pada bulan tersebut.
                </p>
            </div>
        @else
            <p class="presensi-help">
                Tanda centang berarti guru hadir pada tanggal tersebut. Kehadiran memakai sumber yang sama dengan
                perhitungan uang transport: mengajar yang sudah berpresensi siswa, absensi guru harian, piket,
                peminatan, dan rapat. Tanggal yang sama dihitung satu kali.
            </p>

            <div class="presensi-table-scroll" role="region" aria-label="Rekap kehadiran guru bulan {{ $laporan['periode'] }}" tabindex="0">
                <table class="asesmen-matrix rekap-matrix guru-rekap-matrix">
                    <caption class="presensi-sr-only">Rekap kehadiran guru bulan {{ $laporan['periode'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" rowspan="2" class="rekap-col-no">No</th>
                            <th scope="col" rowspan="2" class="rekap-col-nama">Nama Guru</th>
                            <th scope="col" colspan="{{ count($laporan['columns']) }}" class="rekap-group">Tanggal</th>
                            <th scope="col" rowspan="2" class="rekap-count">Hari Hadir</th>
                        </tr>
                        <tr>
                            @foreach ($laporan['columns'] as $column)
                                <th scope="col" class="rekap-pertemuan">
                                    <span class="rekap-session-number">{{ $column['nomor'] }}</span>
                                    <span class="rekap-session-date">{{ $column['hari'] }}, {{ $column['label'] }}</span>
                                    <span class="presensi-sr-only">Tanggal {{ $column['hari'] }}, {{ $column['label'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($laporan['rows'] as $row)
                            <tr wire:key="rekap-guru-{{ $row['guru_id'] }}">
                                <td class="rekap-col-no">{{ $loop->iteration }}</td>
                                <td class="rekap-col-nama rekap-name">{{ $row['nama'] }}</td>
                                @foreach ($laporan['columns'] as $column)
                                    @php($sumber = $row['cells'][$column['tanggal']] ?? [])
                                    <td class="guru-rekap-cell">
                                        @if ($sumber !== [])
                                            <span class="guru-rekap-mark" title="{{ implode(', ', $sumber) }}">
                                                <span aria-hidden="true">✓</span>
                                                <span class="presensi-sr-only">{{ implode(', ', $sumber) }}</span>
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="rekap-count presensi-numeric">{{ $row['hadir'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2" class="rekap-name">Total</th>
                            <td colspan="{{ count($laporan['columns']) }}"></td>
                            <td class="rekap-count presensi-numeric">{{ $laporan['totals']['hadir'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="presensi-help">
                Arahkan kursor ke tanda centang untuk melihat sumber kehadirannya. Jumlah Hari Hadir pada kolom
                terakhir sama dengan hari hadir yang dipakai untuk menghitung uang transport di menu Gaji Guru.
            </p>
        @endif
    </div>
</x-filament-panels::page>
