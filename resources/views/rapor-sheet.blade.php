<article class="rapor-sheet" aria-label="Rapor {{ $rapor['student']['nama'] }}">
    <header class="rapor-sheet-header">
        <div class="rapor-sheet-cop">
            @if (filled($rapor['school_logo_url'] ?? null))
                <img
                    class="rapor-sheet-logo"
                    src="{{ $rapor['school_logo_url'] }}"
                    alt="Logo {{ $rapor['school_name'] }}"
                >
            @endif
            <div class="rapor-sheet-school">
                <p class="rapor-school">{{ $rapor['school_name'] }}</p>
                @if ($rapor['school_address'] !== '')
                    <p class="rapor-school-address">{{ $rapor['school_address'] }}</p>
                @endif
            </div>
        </div>
        <h2 class="rapor-sheet-title">LAPORAN HASIL BELAJAR</h2>
        <p class="rapor-sheet-subtitle">
            UJIAN AKHIR SEMESTER {{ mb_strtoupper($rapor['semester']) }} {{ $rapor['tahun_ajaran'] }}
        </p>
    </header>

    <dl class="rapor-identity">
        <div>
            <dt>Nama</dt>
            <dd>{{ $rapor['student']['nama'] }}</dd>
        </div>
        <div>
            <dt>NISM</dt>
            <dd>{{ $rapor['student']['nis'] ?? '—' }}</dd>
        </div>
        <div>
            <dt>Kelas</dt>
            <dd>{{ $rapor['class'] }}</dd>
        </div>
        <div>
            <dt>NISN</dt>
            <dd>{{ $rapor['student']['nisn'] ?? '—' }}</dd>
        </div>
        <div>
            <dt>Tahun Pelajaran</dt>
            <dd>{{ $rapor['tahun_ajaran'] }}</dd>
        </div>
        <div>
            <dt>No. Absen</dt>
            <dd>{{ $rapor['student']['no_absen'] }}</dd>
        </div>
    </dl>

    <table class="rapor-sheet-table rapor-score-table">
        <caption class="presensi-sr-only">Nilai mata pelajaran {{ $rapor['student']['nama'] }}</caption>
        <thead>
            <tr>
                <th scope="col" class="rapor-no-col">No</th>
                <th scope="col">Mata Pelajaran</th>
                <th scope="col" class="rapor-kkm-col">KKM</th>
                <th scope="col" class="rapor-score-col">Angka</th>
                <th scope="col" class="rapor-letter-col">Huruf</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rapor['subjects'] as $index => $subject)
                <tr>
                    <td class="rapor-no-col presensi-numeric">{{ $index + 1 }}</td>
                    <td class="rapor-subject-name">{{ $subject['nama'] }}</td>
                    <td class="rapor-kkm-col presensi-numeric">{{ $subject['kkm'] ?? '—' }}</td>
                    <td @class([
                        'rapor-score-col',
                        'presensi-numeric',
                        'rapor-below-kkm' => $subject['angka'] !== null
                            && $subject['kkm'] !== null
                            && $subject['angka'] < $subject['kkm'],
                    ])>{{ $subject['angka'] ?? '—' }}</td>
                    <td class="rapor-letter-col">{{ $subject['huruf'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="rapor-empty-cell">Belum ada mata pelajaran untuk kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="rapor-summary">
        <div>
            <span>Jumlah Nilai</span>
            <strong>{{ $rapor['jumlah'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Rata-rata</span>
            <strong>{{ $rapor['rata_rata'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Peringkat Kelas</span>
            <strong>
                @if ($rapor['peringkat'] !== null)
                    {{ $rapor['peringkat'] }} dari {{ $rapor['jumlah_siswa'] }}
                @else
                    —
                @endif
            </strong>
        </div>
        <div>
            <span>Peminatan</span>
            <strong>{{ $rapor['peminatan'] !== '' ? $rapor['peminatan'] : '—' }}</strong>
        </div>
    </div>

    <div class="rapor-sheet-columns">
        <section class="rapor-block">
            <h3>Kepribadian</h3>
            <table class="rapor-sheet-table rapor-small-table">
                <thead>
                    <tr>
                        <th scope="col">Aspek</th>
                        <th scope="col" class="rapor-score-col">Angka</th>
                        <th scope="col" class="rapor-letter-col">Huruf</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rapor['kepribadian'] as $aspect)
                        <tr>
                            <td class="rapor-subject-name">{{ $aspect['label'] }}</td>
                            <td class="rapor-score-col presensi-numeric">{{ $aspect['angka'] ?? '—' }}</td>
                            <td class="rapor-letter-col">{{ $aspect['huruf'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="rapor-block">
            <h3>Ketidakhadiran</h3>
            <table class="rapor-sheet-table rapor-small-table">
                <thead>
                    <tr>
                        <th scope="col">Keterangan</th>
                        <th scope="col" class="rapor-score-col">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="rapor-subject-name">Sakit</td>
                        <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['sakit'] }}</td>
                    </tr>
                    <tr>
                        <td class="rapor-subject-name">Izin</td>
                        <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['izin'] }}</td>
                    </tr>
                    <tr>
                        <td class="rapor-subject-name">Alpa</td>
                        <td class="rapor-score-col presensi-numeric">{{ $rapor['ketidakhadiran']['alpa'] }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>

    <p class="rapor-note">{{ $rapor['catatan'] }}</p>

    <div class="rapor-signatures">
        <div>
            <span>Kepala Madrasah</span>
            <strong>{{ $rapor['kepala_madrasah'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Wali Kelas</span>
            <strong>{{ $rapor['wali_kelas'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Orang Tua/Wali</span>
            <strong>{{ $rapor['orang_tua'] ?: '................' }}</strong>
        </div>
    </div>
</article>
