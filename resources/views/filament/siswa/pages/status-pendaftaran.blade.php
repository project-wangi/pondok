@php
    $murid = $calon->murid;

    $statusColor = match ($calon->status) {
        \App\Models\CalonSiswa::STATUS_LULUS => 'success',
        \App\Models\CalonSiswa::STATUS_TIDAK_LULUS => 'danger',
        \App\Models\CalonSiswa::STATUS_DITERIMA => 'info',
        default => 'warning',
    };

    $statusMessage = match ($calon->status) {
        \App\Models\CalonSiswa::STATUS_LULUS => 'Selamat, Anda dinyatakan lulus seleksi. Silakan menunggu informasi pendaftaran ulang dari sekolah.',
        \App\Models\CalonSiswa::STATUS_TIDAK_LULUS => 'Mohon maaf, Anda belum lulus seleksi pada kesempatan ini.',
        \App\Models\CalonSiswa::STATUS_DITERIMA => 'Anda sudah tercatat sebagai siswa. Data kelas dan peminatan dapat dilihat di bawah.',
        default => 'Pendaftaran Anda sedang diproses. Hasil seleksi akan tampil di halaman ini.',
    };

    $belumLengkap = $calon->dataBelumLengkap();

    $dataPendaftar = array_filter([
        'Nama Lengkap' => $calon->nama,
        'Jenis Kelamin' => \App\Models\CalonSiswa::JENIS_KELAMIN[$calon->jenis_kelamin] ?? $calon->jenis_kelamin,
        'NISN' => $calon->nisn,
        'Email' => $calon->email,
        'WhatsApp' => $calon->nohp,
        'Tanggal Daftar' => $calon->created_at->locale('id')->translatedFormat('d F Y H:i'),
    ], fn (mixed $value): bool => filled($value));
@endphp

<x-filament-panels::page>
    <div class="siswa-panel">
        <div class="siswa-columns">
            <x-filament::section>
                <div class="siswa-head">
                    <div>
                        <p class="siswa-label">Nomor Pendaftaran</p>
                        <p class="siswa-nomor">{{ $calon->nomor_pendaftaran }}</p>
                    </div>

                    <x-filament::badge :color="$statusColor" size="lg">{{ $calon->status }}</x-filament::badge>
                </div>

                <p class="siswa-text">{{ $statusMessage }}</p>

                @if ($calon->nilai_tes !== null)
                    <p class="siswa-text">Nilai tes masuk: <strong>{{ $calon->nilai_tes }}</strong></p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Kelengkapan Data">
                @if ($belumLengkap === [])
                    <p class="siswa-text">
                        Semua data administrasi dan dokumen wajib sudah lengkap.
                        @if ($calon->dilengkapi_pada)
                            Dilengkapi pada {{ $calon->dilengkapi_pada->locale('id')->translatedFormat('d F Y H:i') }}.
                        @endif
                    </p>
                @else
                    <p class="siswa-text">
                        Masih ada {{ count($belumLengkap) }} bagian yang belum lengkap:
                        <strong>{{ implode(', ', $belumLengkap) }}</strong>.
                    </p>
                @endif

                <div class="siswa-actions">
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Siswa\Pages\DataPendaftaran::getUrl(panel: 'siswa')"
                        :color="$belumLengkap === [] ? 'gray' : 'primary'"
                    >
                        {{ $belumLengkap === [] ? 'Perbarui Data' : 'Lengkapi Data' }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>

        <div class="siswa-columns">
            <x-filament::section heading="Data Pendaftar">
                <dl class="siswa-grid">
                    @foreach ($dataPendaftar as $label => $value)
                        <div class="siswa-item">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>

            <x-filament::section heading="Dokumen Pendaftaran">
                <ul class="siswa-docs">
                    @foreach (\App\Models\CalonSiswa::DOKUMEN as $field => $label)
                        <li @class(['siswa-doc', 'is-done' => filled($calon->{$field})])>
                            <span class="siswa-doc-name">{{ $label }}</span>
                            <span class="siswa-doc-state">
                                {{ filled($calon->{$field}) ? 'Sudah diunggah' : 'Belum diunggah' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </div>

        @if ($murid)
            <x-filament::section heading="Data Siswa">
                <dl class="siswa-grid">
                    <div class="siswa-item">
                        <dt>NIS</dt>
                        <dd>{{ $murid->nis ?: '-' }}</dd>
                    </div>
                    <div class="siswa-item">
                        <dt>NISN</dt>
                        <dd>{{ $murid->nisn ?: '-' }}</dd>
                    </div>
                    <div class="siswa-item">
                        <dt>Kelas</dt>
                        <dd>{{ $murid->kelas?->nama_kelas ?? '-' }}</dd>
                    </div>
                    <div class="siswa-item">
                        <dt>Peminatan</dt>
                        <dd>{{ $murid->jenisPeminatans->pluck('nama_peminatan')->implode(', ') ?: '-' }}</dd>
                    </div>
                </dl>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
