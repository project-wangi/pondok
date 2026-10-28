<x-filament-panels::page>
    <div class="presensi-page gaji-page slip-gaji-page">
        <div class="gaji-toolbar slip-gaji-toolbar">
            <label class="gaji-field" for="slip-gaji-bulan">
                <span>Bulan</span>
                <select id="slip-gaji-bulan" wire:model.live="bulan">
                    @foreach ($bulanOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="gaji-field" for="slip-gaji-tahun">
                <span>Tahun</span>
                <select id="slip-gaji-tahun" wire:model.live="tahun">
                    @foreach ($tahunOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <div class="slip-gaji-actions">
                <x-filament::button
                    tag="a"
                    :href="route('slip-gaji.print', ['bulan' => $bulan, 'tahun' => $tahun])"
                    target="_blank"
                    rel="noopener"
                    color="gray"
                    icon="heroicon-o-printer"
                >
                    Cetak slip
                </x-filament::button>
            </div>
        </div>

        <section class="slip-gaji-card slip-gaji-container" aria-labelledby="slip-gaji-title">
            <header class="slip-gaji-header">
                <div>
                    <p class="slip-gaji-school">{{ $sekolah }}</p>
                    <h2 id="slip-gaji-title" class="slip-gaji-title">Slip Gaji Guru</h2>
                </div>
                <div class="slip-gaji-period">
                    <span>Periode</span>
                    <strong>{{ $rincian['periode'] }}</strong>
                </div>
            </header>

            <dl class="slip-gaji-identity">
                <div>
                    <dt>Nama guru</dt>
                    <dd>{{ $rincian['nama'] }}</dd>
                </div>
                <div>
                    <dt>Jarak rumah</dt>
                    <dd>{{ $angka($rincian['jarak_rumah']) }} km</dd>
                </div>
                <div>
                    <dt>JP mengajar</dt>
                    <dd>{{ $angka($rincian['jp']) }} JP</dd>
                </div>
                <div>
                    <dt>Hari hadir</dt>
                    <dd>{{ $rincian['hari_hadir'] }} hari</dd>
                </div>
            </dl>

            <div class="presensi-table-scroll">
                <table class="presensi-table slip-gaji-table">
                    <caption class="presensi-sr-only">Rincian slip gaji {{ $rincian['nama'] }} periode {{ $rincian['periode'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Komponen</th>
                            <th scope="col">Rincian</th>
                            <th scope="col">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($komponen as $item)
                            <tr wire:key="slip-komponen-{{ $loop->index }}">
                                <td>{{ $item['label'] }}</td>
                                <td>{{ $item['rincian'] }}</td>
                                <td>{{ $item['jumlah'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">Total diterima</th>
                            <td>{{ $rupiah($rincian['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="slip-gaji-note">
                Slip ini dihitung otomatis dari sesi mengajar yang sudah ada presensi siswa,
                kehadiran rapat, hari piket, tunjangan jabatan, dan peminatan pada periode tersebut.
                Dicetak {{ now()->locale('id')->translatedFormat('d F Y H:i') }}.
            </p>
        </section>
    </div>
</x-filament-panels::page>
