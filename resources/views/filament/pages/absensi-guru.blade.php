<x-filament-panels::page>
    <div class="presensi-page absen-guru-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Absensi belum dapat diproses. Periksa pesan berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filament::section heading="Absensi hari ini" :description="$today">
            <div class="presensi-stack">
                <div class="absen-guru-clock">
                    <p class="presensi-help">Waktu sekarang</p>
                    <p
                        class="absen-guru-time"
                        x-data="{
                            waktu: @js($sekarang),
                            tick() {
                                this.waktu = new Date().toLocaleTimeString('id-ID', {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    second: '2-digit',
                                    hour12: false,
                                })
                            },
                        }"
                        x-init="tick(); setInterval(() => tick(), 1000)"
                    >
                        <span x-text="waktu">{{ $sekarang }}</span>
                    </p>
                    <p class="presensi-help">{{ $guruNama }}</p>
                </div>

                <div class="absen-guru-status">
                    <div class="absen-guru-status-cell">
                        <div class="presensi-cell-stack">
                            <span class="presensi-help">Masuk</span>
                            @if ($absen?->masuk_pada)
                                <span class="presensi-label">Pukul {{ $absen->masuk_pada->format('H:i') }}</span>
                            @else
                                <span class="presensi-label">Belum absen</span>
                            @endif
                        </div>
                    </div>

                    <div class="absen-guru-status-cell">
                        <div class="presensi-cell-stack">
                            <span class="presensi-help">Pulang</span>
                            @if ($absen?->pulang_pada)
                                <span class="presensi-label">Pukul {{ $absen->pulang_pada->format('H:i') }}</span>
                            @else
                                <span class="presensi-label">Belum absen</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="presensi-actions">
                    <x-filament::button
                        type="button"
                        icon="heroicon-o-arrow-right-end-on-rectangle"
                        wire:click="masuk"
                        wire:loading.attr="disabled"
                        wire:target="masuk"
                        :disabled="$absen !== null"
                    >
                        Masuk
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-arrow-left-start-on-rectangle"
                        wire:click="pulang"
                        wire:loading.attr="disabled"
                        wire:target="pulang"
                        :disabled="$absen?->masuk_pada === null || $absen?->pulang_pada !== null"
                    >
                        Pulang
                    </x-filament::button>
                </div>

                <p class="presensi-help">
                    Absen masuk hanya tersimpan sekali per hari dan absen pulang dapat dilakukan setelahnya.
                    Kehadiran pada hari ini dihitung sebagai hari hadir untuk uang transport di menu Gaji Guru.
                </p>
            </div>
        </x-filament::section>

        <x-filament::section heading="Riwayat 7 hari terakhir" description="Termasuk hari ini.">
            @if ($riwayat === [])
                <p class="presensi-help">Belum ada absensi pada tujuh hari terakhir.</p>
            @else
                <div class="presensi-table-scroll" role="region" aria-label="Riwayat absensi tujuh hari terakhir" tabindex="0">
                    <table class="presensi-table absen-guru-table">
                        <caption class="presensi-sr-only">Riwayat absensi guru tujuh hari terakhir</caption>
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Hari</th>
                                <th scope="col">Masuk</th>
                                <th scope="col">Pulang</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayat as $row)
                                <tr wire:key="absen-guru-{{ $row['key'] }}">
                                    <td>{{ $row['tanggal'] }}</td>
                                    <td>{{ $row['hari'] }}</td>
                                    <td class="presensi-time">{{ $row['masuk'] }}</td>
                                    <td class="presensi-time">{{ $row['pulang'] }}</td>
                                    <td>{{ $row['keterangan'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
