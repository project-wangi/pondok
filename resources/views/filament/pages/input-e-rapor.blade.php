<x-filament-panels::page>
    <div class="materi-page rapor-list-page">
        <x-filament::section heading="Daftar kelas dan mata pelajaran" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            @if ($rows->isEmpty())
                <div class="materi-empty">
                    <p class="materi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                    <p class="materi-help">e-Rapor mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                </div>
            @else
                <div class="materi-table-scroll" role="region" aria-label="Daftar kelas dan mata pelajaran untuk e-Rapor" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar kelas dan mata pelajaran untuk e-Rapor semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col" class="rapor-class-col">Kelas</th>
                                <th scope="col" class="rapor-subject-col">Mata Pelajaran</th>
                                <th scope="col" class="rapor-student-col">Siswa</th>
                                <th scope="col" class="rapor-uh-col">Jenis Ujian</th>
                                <th scope="col" class="rapor-filled-col">ASTS dan ASAS</th>
                                <th scope="col" class="rapor-status-col">Status</th>
                                <th scope="col" class="rapor-actions-col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="rapor-{{ $row['kelas']?->id }}-{{ $row['subject']?->id }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td>{{ $row['kelas']?->nama_kelas ?? 'Kelas tidak tersedia' }}</td>
                                    <td class="materi-label">{{ $row['subject']?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</td>
                                    <td class="materi-number">{{ $row['student_count'] }}</td>
                                    <td class="materi-number">
                                        @if ($row['component_count'] > 0)
                                            {{ $row['component_count'] }}
                                        @else
                                            <span class="materi-help">Belum ada</span>
                                        @endif
                                    </td>
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
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="materi-help">Belum diisi</span>
                                        @elseif ($row['filled_count'] >= $row['possible_count'])
                                            <span class="materi-label">Lengkap</span>
                                        @else
                                            <span class="materi-label">Sebagian</span>
                                        @endif
                                    </td>
                                    <td class="rapor-actions-col">
                                        @if ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @else
                                            <x-filament::button
                                                tag="a"
                                                :href="$row['url']"
                                                size="sm"
                                                :outlined="$row['filled_count'] > 0"
                                                aria-label="Isi e-Rapor {{ $row['subject']?->nama_mapel }}, kelas {{ $row['kelas']?->nama_kelas }}"
                                            >
                                                {{ $row['filled_count'] > 0 ? 'Lengkapi' : 'Isi rapor' }}
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="materi-help">Nilai formatif, ujian harian, kehadiran, sikap, dan keaktifan dihitung otomatis dari menu masing-masing. Guru hanya mengisi nilai ASTS dan ASAS.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
