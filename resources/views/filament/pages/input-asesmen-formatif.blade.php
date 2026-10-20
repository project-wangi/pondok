<x-filament-panels::page>
    <div class="materi-page">
        <x-filament::section heading="Daftar kelas dan mata pelajaran" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            @if ($rows->isEmpty())
                <div class="materi-empty">
                    <p class="materi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                    <p class="materi-help">Asesmen formatif mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                </div>
            @else
                <div class="materi-table-scroll" role="region" aria-label="Daftar kelas dan mata pelajaran untuk asesmen formatif" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar kelas dan mata pelajaran untuk asesmen formatif semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col" class="materi-class-col">Kelas</th>
                                <th scope="col" class="materi-subject-col">Mata Pelajaran</th>
                                <th scope="col" class="materi-material-count-col">Siswa</th>
                                <th scope="col" class="materi-purpose-count-col">Tujuan Pembelajaran</th>
                                <th scope="col" class="materi-status-col">Status</th>
                                <th scope="col" class="materi-actions-column">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="asesmen-{{ $row['kelas']?->id }}-{{ $row['subject']?->id }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td>{{ $row['kelas']?->nama_kelas ?? 'Kelas tidak tersedia' }}</td>
                                    <td class="materi-label">{{ $row['subject']?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</td>
                                    <td class="materi-number">{{ $row['student_count'] }}</td>
                                    <td class="materi-number">{{ $row['tp_count'] }}</td>
                                    <td>
                                        @if ($row['tp_count'] === 0)
                                            <span class="materi-help">Lingkup materi belum diisi</span>
                                        @elseif ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="materi-help">Belum dinilai</span>
                                        @elseif ($row['filled_count'] >= $row['possible_count'])
                                            <span class="materi-label">Lengkap</span>
                                        @else
                                            <div>
                                                <span class="materi-label">Sebagian</span>
                                                <span class="materi-help">{{ $row['filled_count'] }}/{{ $row['possible_count'] }} nilai</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="materi-row-action">
                                        @if ($row['tp_count'] === 0)
                                            <x-filament::button
                                                tag="a"
                                                :href="$row['lingkup_url']"
                                                size="sm"
                                                color="gray"
                                                :outlined="true"
                                                aria-label="Isi lingkup materi {{ $row['subject']?->nama_mapel }}"
                                            >
                                                Isi TP
                                            </x-filament::button>
                                        @elseif ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @else
                                            <x-filament::button
                                                tag="a"
                                                :href="$row['url']"
                                                size="sm"
                                                :outlined="$row['filled_count'] > 0"
                                                aria-label="Nilai asesmen formatif {{ $row['subject']?->nama_mapel }}, kelas {{ $row['kelas']?->nama_kelas }}"
                                            >
                                                {{ $row['filled_count'] > 0 ? 'Lengkapi' : 'Nilai' }}
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="materi-help">Nilai dihitung per kelas dan mata pelajaran. Isi lingkup materi terlebih dahulu sebelum menilai tujuan pembelajaran.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
