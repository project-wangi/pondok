<x-filament-panels::page>
    <div class="presensi-page gaji-page">
        <div class="gaji-toolbar">
            <label class="gaji-field" for="gaji-bulan">
                <span>Bulan</span>
                <select id="gaji-bulan" wire:model.live="bulan">
                    @foreach ($bulanOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="gaji-field" for="gaji-tahun">
                <span>Tahun</span>
                <select id="gaji-tahun" wire:model.live="tahun">
                    @foreach ($tahunOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <p class="gaji-note">
                Honor mengajar Rp10.000/JP (40 menit) · transport Rp1.000/km/hari hadir · rapat Rp20.000 ·
                piket Rp10.000 · potongan donasi Rp50.000/anak. Peminatan memakai nominal jenis × jumlah absensi peminatan.
                Hari hadir dihitung dari sesi mengajar berpresensi, absensi guru (masuk), piket, peminatan, dan rapat.
            </p>

            <div class="gaji-export">
                <x-filament::button wire:click="ekspor" color="success" icon="heroicon-o-arrow-down-tray">
                    Ekspor Excel
                </x-filament::button>
            </div>
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
                <span>Total gaji</span>
                <strong>{{ $rupiah($rekap['totals']['total']) }}</strong>
            </div>
        </div>

        @if ($rekap['rows'] === [])
            <div class="presensi-notice">
                <p class="presensi-label">Belum ada data guru.</p>
                <p class="presensi-help">Tambahkan guru melalui menu Guru sebelum menghitung gaji.</p>
            </div>
        @else
            <div class="presensi-table-scroll" role="region" aria-label="Rekap gaji guru {{ $rekap['periode'] }}" tabindex="0">
                <table class="presensi-table gaji-table">
                    <caption class="presensi-sr-only">Rekap gaji guru bulan {{ $rekap['periode'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="gaji-no">No</th>
                            <th scope="col" class="gaji-name">Nama</th>
                            <th scope="col" class="gaji-money">Mengajar</th>
                            <th scope="col" class="gaji-money">Transport</th>
                            <th scope="col" class="gaji-money">Jabatan</th>
                            <th scope="col" class="gaji-money">Rapat</th>
                            <th scope="col" class="gaji-money">Peminatan</th>
                            <th scope="col" class="gaji-money">Piket</th>
                            <th scope="col" class="gaji-money">Potongan Donasi</th>
                            <th scope="col" class="gaji-money">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekap['rows'] as $row)
                            <tr wire:key="gaji-guru-{{ $row['guru_id'] }}">
                                <td class="gaji-no presensi-numeric">{{ $loop->iteration }}</td>
                                <td class="gaji-name">{{ $row['nama'] }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['mengajar'] > 0 ? $rupiah($row['mengajar']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['transport'] > 0 ? $rupiah($row['transport']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['jabatan'] > 0 ? $rupiah($row['jabatan']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['rapat'] > 0 ? $rupiah($row['rapat']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['peminatan'] > 0 ? $rupiah($row['peminatan']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['piket'] > 0 ? $rupiah($row['piket']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric">{{ $row['donasi'] > 0 ? '- '.$rupiah($row['donasi']) : '-' }}</td>
                                <td class="gaji-money presensi-numeric gaji-total">{{ $row['total'] > 0 ? $rupiah($row['total']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2" class="gaji-footer-label">Total</th>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['mengajar']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['transport']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['jabatan']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['rapat']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['peminatan']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['piket']) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rekap['totals']['donasi'] > 0 ? '- '.$rupiah($rekap['totals']['donasi']) : $rupiah(0) }}</td>
                            <td class="gaji-money presensi-numeric">{{ $rupiah($rekap['totals']['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="gaji-help">
                JP mengajar hanya dihitung dari sesi yang sudah memiliki presensi siswa
                (durasi jam mulai sampai jam selesai, 1 JP = 40 menit). Transport = jarak rumah × Rp1.000 ×
                jumlah hari hadir, yaitu tanggal unik saat guru mengajar yang sudah berpresensi siswa,
                absensi guru (masuk), piket, peminatan, atau rapat. Piket dihitung dari jumlah hari piket yang dicatat.
                Peminatan = nominal jenis × jumlah absensi peminatan pada bulan berjalan. Potongan donasi = jumlah anak pada
                donasi bulan berjalan × Rp50.000 dan mengurangi total gaji. Jabatan bersifat bulanan, jadi tetap muncul pada
                bulan tanpa kegiatan.
            </p>

            <footer class="gaji-signatures">
                <div class="gaji-signature">
                    <p class="gaji-sign-role">Bendahara</p>
                    <div class="gaji-sign-space" aria-hidden="true"></div>
                    <p class="gaji-sign-name">
                        @if (filled($bendahara))
                            {{ $bendahara }}
                        @else
                            <span class="presensi-sr-only">Nama dan tanda tangan Bendahara</span>
                        @endif
                    </p>
                </div>

                <div class="gaji-signature">
                    <p class="gaji-sign-role">Kepala Sekolah</p>
                    <div class="gaji-sign-space" aria-hidden="true"></div>
                    <p class="gaji-sign-name">
                        @if (filled($kepalaSekolah))
                            {{ $kepalaSekolah }}
                        @else
                            <span class="presensi-sr-only">Nama dan tanda tangan Kepala Sekolah</span>
                        @endif
                    </p>
                </div>
            </footer>
        @endif
    </div>
</x-filament-panels::page>
