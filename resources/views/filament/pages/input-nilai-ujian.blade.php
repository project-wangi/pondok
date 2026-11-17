<x-filament-panels::page>
    <div class="materi-page ujian-list-page rapor-admin-page">
        @if ($langkah === 'kelas')
            <x-filament::section
                heading="Pilih kelas"
                :description="'Pilih kelas untuk melihat mata pelajaran dan mengisi nilai ujian pada semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'"
            >
                @if ($kelasList->isEmpty())
                    <div class="materi-empty">
                        @if ($isAdmin)
                            <p class="materi-label">Belum ada jadwal pelajaran pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                            <p class="materi-help">Nilai ujian mengikuti jadwal pelajaran pada periode berjalan. Tambahkan jadwal melalui menu Jadwal Pelajaran.</p>
                        @else
                            <p class="materi-label">Tidak ada kelas atau mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                            <p class="materi-help">Nilai ujian mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                        @endif
                    </div>
                @else
                    <div class="rapor-class-grid">
                        @foreach ($kelasList as $item)
                            <button
                                type="button"
                                class="rapor-class-card"
                                wire:key="ujian-kelas-{{ $item['kelas']->id }}"
                                wire:click="pilihKelas({{ $item['kelas']->id }})"
                                aria-label="Lihat mata pelajaran kelas {{ $item['kelas']->nama_kelas }}"
                            >
                                <span class="rapor-class-name">{{ $item['kelas']->nama_kelas }}</span>
                                <span class="rapor-class-meta">
                                    {{ $item['jumlah_mapel'] }} mata pelajaran
                                    <span aria-hidden="true">·</span>
                                    {{ $item['jumlah_siswa'] }} siswa
                                </span>
                                <span class="rapor-class-link">Lihat mata pelajaran</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="materi-help">
                        Nilai ujian mengikuti jadwal pada periode berjalan. Pilih kelas untuk melihat mata pelajarannya.
                    </p>
                @endif
            </x-filament::section>
        @else
            <div class="rapor-back">
                <x-filament::button
                    wire:click="kembaliKeDaftarKelas"
                    color="gray"
                    size="sm"
                    :outlined="true"
                >
                    Kembali ke daftar kelas
                </x-filament::button>
            </div>

            <x-filament::section
                :heading="'Mata pelajaran kelas '.$kelas->nama_kelas"
                :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'"
            >
                <div class="materi-table-scroll" role="region" aria-label="Daftar mata pelajaran nilai ujian kelas {{ $kelas->nama_kelas }}" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar mata pelajaran nilai ujian kelas {{ $kelas->nama_kelas }} semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
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
                <p class="materi-help">
                    Nilai dan catatan disimpan per kelas, mata pelajaran, dan periode berjalan. Tambahkan komponen ujian sendiri pada halaman isi nilai.
                </p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
