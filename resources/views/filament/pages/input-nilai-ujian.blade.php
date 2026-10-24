<x-filament-panels::page>
    <div class="materi-page ujian-list-page">
        <x-filament::section heading="Daftar kelas dan mata pelajaran" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            @if ($rows->isEmpty())
                <div class="materi-empty">
                    <p class="materi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                    <p class="materi-help">Nilai ujian mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                </div>
            @else
                <div class="materi-table-scroll" role="region" aria-label="Daftar kelas dan mata pelajaran untuk nilai ujian" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar kelas dan mata pelajaran untuk nilai ujian semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col" class="ujian-class-col">Kelas</th>
                                <th scope="col" class="ujian-subject-col">Mata Pelajaran</th>
                                <th scope="col" class="ujian-student-col">Siswa</th>
                                <th scope="col" class="ujian-component-count-col">Jumlah Ujian</th>
                                <th scope="col" class="ujian-filled-col">Nilai Terisi</th>
                                <th scope="col" class="ujian-status-col">Status</th>
                                <th scope="col" class="ujian-actions-col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="nilai-ujian-{{ $row['kelas']?->id }}-{{ $row['subject']?->id }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td>{{ $row['kelas']?->nama_kelas ?? 'Kelas tidak tersedia' }}</td>
                                    <td class="materi-label">{{ $row['subject']?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</td>
                                    <td class="materi-number">{{ $row['student_count'] }}</td>
                                    <td class="materi-number">{{ $row['component_count'] }}</td>
                                    <td class="materi-number">
                                        @if ($row['possible_count'] > 0)
                                            {{ $row['filled_count'] }}/{{ $row['possible_count'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @elseif ($row['component_count'] === 0)
                                            <span class="materi-help">Belum ada ujian</span>
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="materi-help">Belum dinilai</span>
                                        @elseif ($row['filled_count'] >= $row['possible_count'])
                                            <span class="materi-label">Lengkap</span>
                                        @else
                                            <span class="materi-label">Sebagian</span>
                                        @endif
                                    </td>
                                    <td class="ujian-actions-col">
                                        @if ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @else
                                            <x-filament::button
                                                tag="a"
                                                :href="$row['url']"
                                                size="sm"
                                                :outlined="$row['filled_count'] > 0"
                                                aria-label="Isi nilai ujian {{ $row['subject']?->nama_mapel }}, kelas {{ $row['kelas']?->nama_kelas }}"
                                            >
                                                {{ $row['filled_count'] > 0 ? 'Lengkapi' : 'Isi nilai' }}
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="materi-help">Nilai dan catatan disimpan per kelas, mata pelajaran, dan periode berjalan. Tambahkan komponen ujian sendiri pada halaman isi nilai.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
