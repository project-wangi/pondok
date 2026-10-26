<x-filament-panels::page>
    <div class="presensi-page gaji-page rekap-mengajar-page">
        <div class="gaji-toolbar">
            <label class="gaji-field" for="rekap-mengajar-bulan">
                <span>Bulan</span>
                <select id="rekap-mengajar-bulan" wire:model.live="bulan">
                    @foreach ($bulanOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="gaji-field" for="rekap-mengajar-tahun">
                <span>Tahun</span>
                <select id="rekap-mengajar-tahun" wire:model.live="tahun">
                    @foreach ($tahunOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <p class="gaji-note">
                Angka dalam JP (jam mengajar yang tercatat). Sel abu-abu berarti guru tidak
                memiliki jadwal pada kelas tersebut di semester ini.
            </p>
        </div>

        <div class="gaji-summary">
            <div>
                <span>Periode</span>
                <strong>{{ $rekap['periode'] }}</strong>
            </div>
            <div>
                <span>Jumlah guru</span>
                <strong>{{ count($rekap['rows']) }}</strong>
            </div>
            <div>
                <span>Total JP</span>
                <strong>{{ $formatJp($rekap['totals']['total']) }}</strong>
            </div>
        </div>

        @if ($rekap['rows'] === [] || $rekap['columns'] === [])
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada data untuk ditampilkan.</p>
                <p class="presensi-help">Tambahkan guru, kelas, dan jadwal pelajaran terlebih dahulu.</p>
            </div>
        @else
            <div class="presensi-table-scroll" role="region" aria-label="Rekap mengajar guru {{ $rekap['periode'] }}" tabindex="0">
                <table class="presensi-table rekap-mengajar-table">
                    <caption class="presensi-sr-only">Rekap mengajar guru bulan {{ $rekap['periode'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" rowspan="2" class="rekap-mengajar-no">No</th>
                            <th scope="col" rowspan="2" class="rekap-mengajar-name">Nama</th>
                            <th scope="colgroup" colspan="{{ count($rekap['columns']) }}">Jumlah Kehadiran (JP)</th>
                            <th scope="col" rowspan="2" class="rekap-mengajar-total">Total</th>
                        </tr>
                        <tr>
                            @foreach ($rekap['columns'] as $column)
                                <th scope="col" class="rekap-mengajar-jp">{{ $column['nama'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekap['rows'] as $row)
                            <tr wire:key="rekap-mengajar-{{ $row['guru_id'] }}">
                                <td class="rekap-mengajar-no presensi-numeric">{{ $loop->iteration }}</td>
                                <td class="rekap-mengajar-name">{{ $row['nama'] }}</td>
                                @foreach ($rekap['columns'] as $column)
                                    @php($cell = $row['cells'][$column['id']])
                                    <td @class([
                                        'rekap-mengajar-jp',
                                        'presensi-numeric',
                                        'rekap-mengajar-na' => ! $cell['scheduled'],
                                    ])>
                                        {{ $cell['scheduled'] && $cell['hours'] > 0 ? $formatJp($cell['hours']) : '' }}
                                    </td>
                                @endforeach
                                <td class="rekap-mengajar-total presensi-numeric">{{ $formatJp($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2" class="rekap-mengajar-name">Total</th>
                            @foreach ($rekap['columns'] as $column)
                                <td class="rekap-mengajar-jp presensi-numeric">{{ $formatJp($rekap['totals']['kelas'][$column['id']]) }}</td>
                            @endforeach
                            <td class="rekap-mengajar-total presensi-numeric">{{ $formatJp($rekap['totals']['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="gaji-help">
                JP dihitung dari durasi setiap sesi mengajar yang tercatat pada bulan berjalan
                (jam mulai sampai jam selesai). Sel kosong berarti guru punya jadwal di kelas
                tersebut tetapi belum ada sesi tercatat pada bulan ini. Baris Total menjumlahkan
                JP per guru, baris paling bawah menjumlahkan JP per kelas.
            </p>
        @endif
    </div>
</x-filament-panels::page>
