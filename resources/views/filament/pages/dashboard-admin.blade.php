<div class="presensi-page admin-home">
    <section class="admin-hero" aria-label="Ringkasan hari ini">
        <div class="admin-hero-main">
            <p class="admin-hero-greeting">{{ $greeting }},</p>
            <p class="admin-hero-name">{{ $nama }}</p>
            <p class="admin-hero-meta">{{ $tanggalLabel }} · Semester {{ $semester }} {{ $tahunAjaran }}</p>
        </div>

        <dl class="admin-hero-stats">
            <div>
                <dt>Jadwal hari ini</dt>
                <dd>{{ $scheduleCount }}</dd>
            </div>
            <div>
                <dt>Presensi tercatat</dt>
                <dd>{{ $recordedCount }}/{{ $scheduleCount }}</dd>
            </div>
            <div>
                <dt>Guru piket</dt>
                <dd>{{ $piket->count() }}</dd>
            </div>
        </dl>
    </section>

    @if ($hari === 'Jumat')
        <div class="admin-notice">
            Hari ini Jumat, hari libur mingguan. Tidak ada jadwal pelajaran yang dibuat.
        </div>
    @endif

    <section class="admin-stat-cards" aria-label="Ringkasan data sekolah">
        <a class="admin-stat-card is-siswa" href="{{ $links['murid'] }}">
            <span class="admin-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5 12 5l9 4.5-9 4.5-9-4.5Z" />
                    <path d="M7 12v4.2c0 .9 2.2 2.3 5 2.3s5-1.4 5-2.3V12" />
                </svg>
            </span>
            <span class="admin-stat-body">
                <span class="admin-stat-label">Total Siswa</span>
                <span class="admin-stat-value">{{ number_format($counts['murid'], 0, ',', '.') }}</span>
                <span class="admin-stat-sub">kelola data siswa</span>
            </span>
            <span class="admin-stat-arrow" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h13" />
                    <path d="m13 7 5 5-5 5" />
                </svg>
            </span>
        </a>

        <a class="admin-stat-card is-guru" href="{{ $links['guru'] }}">
            <span class="admin-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="8" r="3" />
                    <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
                    <path d="M16 5.4a3 3 0 0 1 0 5.2" />
                    <path d="M17.6 19a5.4 5.4 0 0 0-2.1-4.2" />
                </svg>
            </span>
            <span class="admin-stat-body">
                <span class="admin-stat-label">Total Guru</span>
                <span class="admin-stat-value">{{ number_format($counts['guru'], 0, ',', '.') }}</span>
                <span class="admin-stat-sub">kelola data guru</span>
            </span>
            <span class="admin-stat-arrow" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h13" />
                    <path d="m13 7 5 5-5 5" />
                </svg>
            </span>
        </a>

        <a class="admin-stat-card is-kelas" href="{{ $links['kelas'] }}">
            <span class="admin-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="4" width="14" height="17" rx="2" />
                    <path d="M9 4.5h6" />
                    <path d="M9 9.5h6M9 13.5h6M9 17.5h4" />
                </svg>
            </span>
            <span class="admin-stat-body">
                <span class="admin-stat-label">Kelas Aktif</span>
                <span class="admin-stat-value">{{ number_format($counts['kelas'], 0, ',', '.') }}</span>
                <span class="admin-stat-sub">kelola kelas</span>
            </span>
            <span class="admin-stat-arrow" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h13" />
                    <path d="m13 7 5 5-5 5" />
                </svg>
            </span>
        </a>

        <a class="admin-stat-card is-kehadiran" href="#kehadiran-siswa">
            <span class="admin-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 17l5-5 4 3 6-7" />
                    <path d="M14 8h4v4" />
                    <path d="M3 21h18" />
                </svg>
            </span>
            <span class="admin-stat-body">
                <span class="admin-stat-label">Persentase Kehadiran</span>
                @if ($attendancePercent !== null)
                    <span class="admin-stat-value">{{ $attendancePercent }}%</span>
                    <span class="admin-stat-sub">kehadiran hari ini</span>
                @else
                    <span class="admin-stat-value">—</span>
                    <span class="admin-stat-sub">belum ada presensi hari ini</span>
                @endif
            </span>
            <span class="admin-stat-arrow" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h13" />
                    <path d="m13 7 5 5-5 5" />
                </svg>
            </span>
        </a>
    </section>

    <div class="admin-grid">
        <div class="admin-main">
            <section id="kehadiran-siswa" class="admin-card admin-anchor" aria-labelledby="admin-attendance-title">
                <header class="admin-card-head">
                    <h2 id="admin-attendance-title" class="admin-card-title">Kehadiran siswa hari ini</h2>
                    <span class="admin-help">{{ $recordedCount }} dari {{ $scheduleCount }} sesi sudah dicatat</span>
                </header>

                @if ($attendanceTotal === 0)
                    <p class="admin-empty">
                        Belum ada presensi siswa yang tercatat hari ini. Presensi diisi guru melalui
                        halaman Presensi Siswa, lalu jumlahnya muncul di sini.
                    </p>
                @else
                    <div class="admin-attendance">
                        <div>
                            <p class="admin-attendance-percent">{{ $attendancePercent }}%</p>
                            <p class="admin-attendance-sub">
                                hadir dari {{ number_format($attendanceTotal, 0, ',', '.') }} catatan presensi
                            </p>
                        </div>

                        <div class="admin-bar" role="img" aria-label="Hadir {{ $attendanceCounts['Hadir'] }}, Izin {{ $attendanceCounts['Izin'] }}, Sakit {{ $attendanceCounts['Sakit'] }}, Alpa {{ $attendanceCounts['Alpa'] }}">
                            @foreach ($attendanceCounts as $status => $count)
                                @if ($count > 0)
                                    <span class="admin-bar-{{ strtolower($status) }}" style="width: {{ round($count / $attendanceTotal * 100, 2) }}%"></span>
                                @endif
                            @endforeach
                        </div>

                        <ul class="admin-legend">
                            @foreach ($attendanceCounts as $status => $count)
                                <li>
                                    <span class="admin-dot admin-dot-{{ strtolower($status) }}" aria-hidden="true"></span>
                                    {{ $status }} <strong>{{ number_format($count, 0, ',', '.') }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="admin-card" aria-labelledby="admin-schedule-title">
                <header class="admin-card-head">
                    <h2 id="admin-schedule-title" class="admin-card-title">Jadwal hari ini</h2>
                    <a class="admin-link" href="{{ $links['jadwal'] }}">Kelola jadwal</a>
                </header>

                @if ($schedules->isEmpty())
                    <p class="admin-empty">
                        Tidak ada jadwal pelajaran untuk hari ini pada semester {{ $semester }}.
                        Tambahkan jadwal lewat menu Jadwal Pelajaran.
                    </p>
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <caption class="presensi-sr-only">Jadwal pelajaran hari ini</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Jam</th>
                                    <th scope="col">Kelas</th>
                                    <th scope="col">Mata pelajaran</th>
                                    <th scope="col">Guru</th>
                                    <th scope="col">Presensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($schedules as $jadwal)
                                    @php($session = $sessions->get($jadwal->id))
                                    @php($recorded = $session !== null && $session->presensi_murids_count > 0)
                                    <tr>
                                        <td class="admin-time">
                                            {{ substr((string) $jadwal->jam_mulai, 0, 5) }}–{{ substr((string) $jadwal->jam_selesai, 0, 5) }}
                                        </td>
                                        <td>{{ $jadwal->kelas?->nama_kelas ?? '-' }}</td>
                                        <td>{{ $jadwal->mataPelajaran?->nama_mapel ?? '-' }}</td>
                                        <td>{{ $jadwal->guru?->user?->name ?? '-' }}</td>
                                        <td>
                                            @if ($recorded)
                                                <span class="admin-tag admin-tag-done">Tercatat · {{ $session->presensi_murids_count }}</span>
                                            @else
                                                <span class="admin-tag admin-tag-pending">Belum</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="admin-side">
            <section class="admin-card" aria-labelledby="admin-salary-title">
                <h2 id="admin-salary-title" class="admin-card-title">Gaji guru bulan ini</h2>
                <p class="admin-amount">Rp {{ number_format($salaryTotal, 0, ',', '.') }}</p>
                <p class="admin-help">{{ $teacherCount }} guru · {{ $bulanLabel }}</p>
                <a class="admin-link" href="{{ $links['gaji'] }}">Lihat rincian gaji</a>
            </section>

            <section class="admin-card" aria-labelledby="admin-piket-title">
                <h2 id="admin-piket-title" class="admin-card-title">Piket hari ini</h2>

                @forelse ($piket as $jadwalPiket)
                    <div class="admin-row">
                        <span>{{ $jadwalPiket->guru?->user?->name ?? 'Guru' }}</span>
                        @if ($piketRecorded->has($jadwalPiket->guru_id))
                            <span class="admin-tag admin-tag-done">Sudah mencatat</span>
                        @else
                            <span class="admin-tag admin-tag-pending">Belum mencatat</span>
                        @endif
                    </div>
                @empty
                    <p class="admin-empty">Tidak ada jadwal piket hari ini.</p>
                @endforelse
            </section>

            <section class="admin-card" aria-labelledby="admin-meeting-title">
                <h2 id="admin-meeting-title" class="admin-card-title">Rapat berikutnya</h2>

                @forelse ($meetings as $rapat)
                    <div class="admin-agenda">
                        <span class="admin-agenda-date">{{ $rapat['tanggal'] }} · {{ $rapat['jam'] }}</span>
                        <span>{{ $rapat['agenda'] }}</span>
                    </div>
                @empty
                    <p class="admin-empty">Belum ada rapat terjadwal.</p>
                @endforelse
            </section>


        </aside>
    </div>
</div>
