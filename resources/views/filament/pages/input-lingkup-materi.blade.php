<x-filament-panels::page>
    <div class="materi-page">
        <x-filament::section heading="Daftar mata pelajaran" :description="'Periode berjalan: semester '.$period['semester'].' tahun ajaran '.$period['tahun_ajaran'].'.'">
            @if ($rows->isEmpty())
                <div class="materi-empty">
                    <p class="materi-label">Tidak ada mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}.</p>
                    <p class="materi-help">Lingkup materi mengikuti jadwal mengajar pada periode berjalan. Hubungi admin jika jadwal Anda belum tercantum.</p>
                </div>
            @else
                <div class="materi-table-scroll" role="region" aria-label="Daftar mata pelajaran untuk lingkup materi" tabindex="0">
                    <table class="materi-table materi-overview-table">
                        <caption class="materi-sr-only">Daftar mata pelajaran yang diajar pada semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col" class="materi-subject-col">Mata Pelajaran</th>
                                <th scope="col" class="materi-class-col">Kelas</th>
                                <th scope="col" class="materi-material-count-col">Lingkup Materi</th>
                                <th scope="col" class="materi-purpose-count-col">Tujuan Pembelajaran</th>
                                <th scope="col" class="materi-status-col">Status</th>
                                <th scope="col" class="materi-actions-column">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="materi-mapel-{{ $row['subject']->id }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td class="materi-label">{{ $row['subject']->nama_mapel }}</td>
                                    <td>{{ $row['classes']->isEmpty() ? 'Belum ada kelas' : $row['classes']->implode(', ') }}</td>
                                    <td class="materi-number">{{ $row['lingkup_count'] }}</td>
                                    <td class="materi-number">{{ $row['tujuan_count'] }}</td>
                                    <td>
                                        @if ($row['lingkup_count'] > 0)
                                            <span class="materi-label">Sudah diisi</span>
                                        @else
                                            <span class="materi-help">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td class="materi-row-action">
                                        <x-filament::button
                                            tag="a"
                                            :href="$row['url']"
                                            size="sm"
                                            :outlined="$row['lingkup_count'] > 0"
                                            aria-label="Isi lingkup materi {{ $row['subject']->nama_mapel }}"
                                        >
                                            {{ $row['lingkup_count'] > 0 ? 'Lengkapi' : 'Isi' }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="materi-help">Pilih Isi atau Lengkapi untuk membuka halaman pengisian lingkup materi dan tujuan pembelajaran.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
