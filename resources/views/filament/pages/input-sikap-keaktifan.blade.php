<x-filament-panels::page>
    <div class="materi-page">
        <x-filament::section heading="Daftar kelas dan mata pelajaran" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            @if ($rows->isEmpty())
                <div class="materi-empty">
                    <p class="materi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                    <p class="materi-help">Nilai sikap dan keaktifan mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                </div>
            @else
                <div class="materi-table-scroll" role="region" aria-label="Daftar kelas dan mata pelajaran untuk sikap dan keaktifan" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar kelas dan mata pelajaran untuk sikap dan keaktifan semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col" class="materi-class-col">Kelas</th>
                                <th scope="col" class="materi-subject-col">Mata Pelajaran</th>
                                <th scope="col" class="materi-material-count-col">Siswa</th>
                                <th scope="col" class="materi-status-col">Status</th>
                                <th scope="col" class="materi-actions-column">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="sikap-{{ $row['kelas']?->id }}-{{ $row['subject']?->id }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td>{{ $row['kelas']?->nama_kelas ?? 'Kelas tidak tersedia' }}</td>
                                    <td class="materi-label">{{ $row['subject']?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</td>
                                    <td class="materi-number">{{ $row['student_count'] }}</td>
                                    <td>
                                        @if ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @elseif ($row['filled_count'] === 0)
                                            <span class="materi-help">Belum diisi</span>
                                        @elseif ($row['filled_count'] >= $row['student_count'])
                                            <span class="materi-label">Lengkap</span>
                                        @else
                                            <div>
                                                <span class="materi-label">Sebagian</span>
                                                <span class="materi-help">{{ $row['filled_count'] }}/{{ $row['student_count'] }} siswa</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="materi-row-action">
                                        @if ($row['student_count'] === 0)
                                            <span class="materi-help">Belum ada siswa</span>
                                        @else
                                            <x-filament::button
                                                tag="a"
                                                :href="$row['url']"
                                                size="sm"
                                                :outlined="$row['filled_count'] > 0"
                                                aria-label="Nilai sikap dan keaktifan {{ $row['subject']?->nama_mapel }}, kelas {{ $row['kelas']?->nama_kelas }}"
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
                <p class="materi-help">Kategori huruf A sampai E otomatis dikonversi ke angka: A 100, B 90, C 80, D 60, dan E 40.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
