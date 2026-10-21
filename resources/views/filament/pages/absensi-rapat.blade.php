<x-filament-panels::page>
    <div class="presensi-page rapat-page">
        @if ($errors->any())
            <div class="presensi-errors" role="alert">
                <p class="presensi-label">Absensi rapat belum dapat diproses. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filament::section heading="Absensi rapat hari ini" :description="$today">
            <div class="presensi-stack">
                @if ($rows->isEmpty())
                    <div class="presensi-notice">
                        <p class="presensi-label">Tidak ada rapat untuk Anda pada hari ini.</p>
                        <p class="presensi-help">Rapat yang muncul di sini hanya rapat hari ini yang mencantumkan Anda sebagai anggota. Hubungi admin jika jadwal rapat belum sesuai.</p>
                    </div>
                @else
                    <p class="presensi-help">{{ $rows->count() }} rapat hari ini · {{ $rows->filter(fn (array $row): bool => $row['absen'] !== null)->count() }} sudah absen.</p>
                    <div class="presensi-table-scroll" role="region" aria-label="Tabel jadwal rapat hari ini" tabindex="0">
                        <table class="presensi-table rapat-table">
                            <caption class="presensi-sr-only">Jadwal rapat hari ini, {{ $today }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="presensi-number">No</th>
                                    <th scope="col" class="rapat-time-col">Waktu</th>
                                    <th scope="col" class="rapat-agenda-col">Agenda</th>
                                    <th scope="col" class="rapat-status-col">Status</th>
                                    <th scope="col" class="rapat-action-col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr wire:key="rapat-{{ $row['key'] }}">
                                        <td class="presensi-number">{{ $loop->iteration }}</td>
                                        <td class="rapat-time-col">
                                            <span class="presensi-label">{{ substr((string) $row['jam_mulai'], 0, 5) }} - {{ substr((string) $row['jam_selesai'], 0, 5) }}</span>
                                        </td>
                                        <td class="rapat-agenda-col">{{ $row['agenda'] }}</td>
                                        <td class="rapat-status-col">
                                            @if ($row['absen'] !== null)
                                                <div class="presensi-cell-stack">
                                                    <span class="presensi-label">Sudah absen</span>
                                                    <span class="presensi-help">Pukul {{ $row['absen']->dicatat_pada?->format('H:i') }}</span>
                                                </div>
                                            @else
                                                <span class="presensi-label">Belum absen</span>
                                            @endif
                                        </td>
                                        <td class="rapat-action-col">
                                            @if ($row['absen'] !== null)
                                                <span class="presensi-help">—</span>
                                            @else
                                                <x-filament::button
                                                    type="button"
                                                    size="sm"
                                                    wire:click="pilihRapat({{ $row['rapat_id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="rapatId,pilihRapat,absen"
                                                    :loading-indicator="false"
                                                    aria-label="Absen rapat {{ $row['agenda'] }}"
                                                >
                                                    Absen
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="presensi-help">Absensi rapat bersifat mandiri dan hanya dapat dilakukan satu kali untuk setiap rapat. Selfie disimpan sebagai bukti kehadiran.</p>
                @endif
            </div>
        </x-filament::section>

        <x-filament::modal
            id="absensi-rapat-modal"
            width="lg"
            :close-by-clicking-away="false"
            :close-by-escaping="true"
            :close-button="true"
        >
            <x-slot name="heading">Absensi rapat</x-slot>
            <x-slot name="description">
                @if ($selected)
                    {{ $selected->agenda }} · {{ $today }} · {{ substr((string) $selected->jam_mulai, 0, 5) }} - {{ substr((string) $selected->jam_selesai, 0, 5) }}
                @endif
            </x-slot>

            <div class="presensi-stack">
                @if ($errors->any())
                    <div class="presensi-errors" role="alert">
                        <p class="presensi-label">Absensi rapat belum tersimpan. Periksa isian berikut.</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($selected)
                    <form wire:submit="absen" class="presensi-stack" wire:key="rapat-form-{{ $selected->id }}">
                        <p class="presensi-help">Ambil selfie langsung dari kamera perangkat. Unggah foto dari galeri tidak tersedia.</p>

                        <div
                            class="presensi-stack rapat-camera"
                            x-data="{
                                stream: null,
                                error: null,
                                uploading: false,
                                captured: false,
                                async start() {
                                    this.error = null;
                                    this.stop();
                                    if (! navigator.mediaDevices?.getUserMedia) {
                                        this.error = 'Perangkat ini tidak mendukung kamera. Gunakan perangkat dengan kamera dan izinkan akses kamera.';
                                        return;
                                    }
                                    try {
                                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                                        this.$refs.video.srcObject = this.stream;
                                        await this.$refs.video.play();
                                        this.captured = false;
                                        this.stopHandler = () => this.stop();
                                        window.addEventListener('rapat-camera-stop', this.stopHandler);
                                    } catch (exception) {
                                        this.error = 'Kamera tidak dapat diakses. Izinkan akses kamera pada peramban lalu coba lagi.';
                                    }
                                },
                                stop() {
                                    if (this.stream) {
                                        this.stream.getTracks().forEach((track) => track.stop());
                                        this.stream = null;
                                    }
                                    if (this.$refs.video) {
                                        this.$refs.video.srcObject = null;
                                    }
                                    if (this.stopHandler) {
                                        window.removeEventListener('rapat-camera-stop', this.stopHandler);
                                        this.stopHandler = null;
                                    }
                                },
                                capture() {
                                    const video = this.$refs.video;
                                    if (! video || ! video.videoWidth) {
                                        this.error = 'Kamera belum siap. Tunggu sebentar lalu coba lagi.';
                                        return;
                                    }
                                    const canvas = document.createElement('canvas');
                                    canvas.width = video.videoWidth;
                                    canvas.height = video.videoHeight;
                                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                                    canvas.toBlob((blob) => {
                                        if (! blob) {
                                            this.error = 'Gagal mengambil foto. Coba lagi.';
                                            return;
                                        }
                                        this.uploading = true;
                                        this.error = null;
                                        const file = new File([blob], 'selfie-rapat.jpg', { type: 'image/jpeg' });
                                        $wire.upload('foto', file,
                                            () => { this.uploading = false; this.captured = true; this.stop(); },
                                            () => { this.uploading = false; this.captured = false; this.error = 'Gagal mengunggah foto. Periksa koneksi lalu coba lagi.'; },
                                            () => {},
                                        );
                                    }, 'image/jpeg', 0.9);
                                },
                                retake() {
                                    this.captured = false;
                                    $wire.set('foto', null);
                                    this.start();
                                },
                            }"
                            x-on:close-modal.window="if ($event.detail.id === 'absensi-rapat-modal') stop()"
                            x-on:rapat-camera-stop.window="stop()"
                            x-on:beforeunload.window="stop()"
                        >
                            <div class="rapat-camera-stage">
                                <video
                                    x-ref="video"
                                    class="rapat-camera-video"
                                    autoplay
                                    playsinline
                                    muted
                                    x-show="stream && ! captured"
                                ></video>

                                @if ($foto && $foto->isPreviewable())
                                    <img class="rapat-preview" src="{{ $foto->temporaryUrl() }}" alt="Pratinjau selfie absensi rapat">
                                @endif

                                @if (! $foto)
                                    <div class="rapat-camera-placeholder" x-show="! stream && ! captured">
                                        <p class="presensi-label">Kamera belum dinyalakan</p>
                                        <p class="presensi-help">Tekan Nyalakan kamera, lalu ambil selfie.</p>
                                    </div>
                                @endif
                            </div>

                            <div class="rapat-camera-actions">
                                <x-filament::button
                                    type="button"
                                    x-show="! stream && ! captured"
                                    x-on:click="start()"
                                    :loading-indicator="false"
                                >
                                    Nyalakan kamera
                                </x-filament::button>
                                <x-filament::button
                                    type="button"
                                    x-show="stream && ! captured"
                                    x-on:click="capture()"
                                    x-bind:disabled="uploading"
                                    :loading-indicator="false"
                                >
                                    Ambil selfie
                                </x-filament::button>
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    :outlined="true"
                                    x-show="captured"
                                    x-on:click="retake()"
                                    :loading-indicator="false"
                                >
                                    Ambil ulang
                                </x-filament::button>
                            </div>

                            <p x-show="uploading" class="presensi-help" role="status">Mengunggah selfie...</p>
                            <p x-show="error" x-text="error" class="presensi-error" role="alert"></p>

                            <div id="rapat-foto-error">
                                @error('foto')
                                    <p class="presensi-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if ($foto)
                            <p class="presensi-help">Selfie siap dikirim. Periksa hasilnya sebelum menekan Kirim absensi.</p>
                        @endif

                        <p class="presensi-help" role="status" wire:loading wire:target="absen">Menyimpan absensi rapat...</p>
                        <button type="submit" class="presensi-sr-only" tabindex="-1" aria-hidden="true">Kirim absensi rapat</button>
                    </form>
                @else
                    <p class="presensi-notice">Tidak ada rapat yang dipilih. Tutup jendela ini, lalu tekan Absen pada tabel rapat.</p>
                @endif
            </div>

            <x-slot name="footer">
                <div class="presensi-modal-footer">
                    <p class="presensi-help">
                        @if ($selected)
                            {{ $foto ? 'Selfie siap. Tekan Kirim absensi untuk menyimpan.' : 'Nyalakan kamera dan ambil selfie terlebih dahulu.' }}
                        @else
                            Tutup jendela ini untuk memilih rapat.
                        @endif
                    </p>
                    <div class="presensi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="tutupRapat"
                            wire:loading.attr="disabled"
                            wire:target="absen,tutupRapat"
                            :loading-indicator="false"
                        >
                            Tutup
                        </x-filament::button>
                        @if ($selected)
                            <x-filament::button
                                type="button"
                                wire:click="absen"
                                :loading-indicator="false"
                                :disabled="! $foto"
                                wire:loading.attr="disabled"
                                wire:target="absen,foto,tutupRapat"
                            >
                                Kirim absensi
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
