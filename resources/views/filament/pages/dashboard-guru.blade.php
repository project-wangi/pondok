<div class="presensi-page guru-home">
    <section class="guru-hero" aria-label="Ringkasan hari ini">
        <div class="guru-hero-main">
            <p class="guru-hero-greeting">{{ $greeting }},</p>
            <p class="guru-hero-name">{{ $guruNama }}</p>
            <p class="presensi-help">{{ $tanggalLabel }} · Semester {{ $semester }} {{ $tahunAjaran }}</p>
        </div>

        <dl class="guru-summary">
            <div class="guru-summary-item">
                <dt>Jadwal hari ini</dt>
                <dd>{{ $scheduleCount }}</dd>
            </div>
            <div class="guru-summary-item">
                <dt>Presensi tercatat</dt>
                <dd>{{ $recordedCount }}/{{ $scheduleCount }}</dd>
            </div>
            <div class="guru-summary-item">
                <dt>Rapat hari ini</dt>
                <dd>{{ $rapats->count() }}</dd>
            </div>
        </dl>
    </section>

    <div class="guru-grid">
        <section class="guru-card">
            <header class="guru-card-head">
                <h2 class="guru-card-title">Jadwal mengajar hari ini</h2>
                <a class="guru-link" href="{{ $links['presensi'] }}">Buka presensi siswa</a>
            </header>

            @if ($schedules->isEmpty())
                <div class="guru-empty">
                    <p class="presensi-label">Tidak ada jadwal mengajar hari ini.</p>
                    <p class="presensi-help">Jadwal mengikuti periode semester berjalan. Hubungi admin jika ada jadwal yang belum tercantum.</p>
                </div>
            @else
                <ol class="guru-schedule-list">
                    @foreach ($schedules as $jadwal)
                        <li class="guru-schedule">
                            <div class="guru-time">
                                <span class="guru-time-main">{{ substr((string) $jadwal->jam_mulai, 0, 5) }}</span>
                                <span class="presensi-help">{{ substr((string) $jadwal->jam_selesai, 0, 5) }}</span>
                            </div>
                            <div class="guru-subject">
                                <span class="presensi-label">{{ $jadwal->mataPelajaran?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</span>
                                <span class="presensi-help">{{ $jadwal->kelas?->nama_kelas ?? 'Kelas tidak tersedia' }}</span>
                            </div>
                            <div class="guru-status">
                                @if ($sessions->has($jadwal->id))
                                    <span class="guru-tag guru-tag-done">Presensi tercatat</span>
                                @else
                                    <span class="guru-tag">Belum presensi</span>
                                @endif
                            </div>
                            <div class="guru-actions">
                                <a class="guru-action" href="{{ $links['presensi'] }}">Presensi</a>
                                <a class="guru-action guru-action-muted" href="{{ $links['jurnal'] }}">Jurnal</a>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <aside class="guru-side">
            <section class="guru-card">
                <h2 class="guru-card-title">Agenda hari ini</h2>

                <div class="guru-agenda">
                    <div class="guru-agenda-item">
                        <span class="presensi-label">Piket</span>
                        @if ($piket)
                            <span class="presensi-help">{{ $piket->ket ?: 'Sesuai jadwal piket hari ini.' }}</span>
                            <a class="guru-link" href="{{ $links['piket'] }}">Buka absensi piket</a>
                        @else
                            <span class="presensi-help">Tidak ada jadwal piket hari ini.</span>
                        @endif
                    </div>

                    <div class="guru-agenda-item">
                        <span class="presensi-label">Rapat</span>
                        @forelse ($rapats as $rapat)
                            <div class="guru-rapat">
                                <span class="presensi-help">{{ substr((string) $rapat->jam_mulai, 0, 5) }} - {{ substr((string) $rapat->jam_selesai, 0, 5) }}</span>
                                <span>{{ $rapat->agenda }}</span>
                            </div>
                        @empty
                            <span class="presensi-help">Tidak ada rapat hari ini.</span>
                        @endforelse
                        @if ($rapats->isNotEmpty())
                            <a class="guru-link" href="{{ $links['rapat'] }}">Buka absensi rapat</a>
                        @endif
                    </div>
                </div>
            </section>

            <section class="guru-card">
                <h2 class="guru-card-title">Donasi</h2>
                @if ($donasi)
                    <p class="guru-donasi-value">Rp {{ number_format($donasi->nominal, 0, ',', '.') }}</p>
                    <p class="presensi-help">{{ $donasi->jumlah_anak }} anak · {{ $donasi->tanggal?->format('d/m/Y') ?? '-' }}</p>
                    <a class="guru-link" href="{{ $links['donasi'] }}">Ubah data donasi</a>
                @else
                    <p class="presensi-help">Data donasi belum diisi.</p>
                    <a class="guru-link" href="{{ $links['donasi'] }}">Isi data donasi</a>
                @endif
            </section>

            <section class="guru-card">
                <h2 class="guru-card-title">Menu cepat</h2>
                <nav class="guru-links" aria-label="Menu cepat guru">
                    <a class="guru-action" href="{{ $links['lingkup'] }}">Lingkup materi</a>
                    <a class="guru-action" href="{{ $links['asesmen'] }}">Asesmen formatif</a>
                    <a class="guru-action" href="{{ $links['sikap'] }}">Sikap dan keaktifan</a>
                    <a class="guru-action" href="{{ $links['peminatan'] }}">Absensi peminatan</a>
                </nav>
            </section>
        </aside>
    </div>
</div>
