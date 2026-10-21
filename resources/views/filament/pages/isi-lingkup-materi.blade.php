<x-filament-panels::page>
    <div class="materi-page">
        @if ($errors->any())
            <div class="materi-errors" role="alert">
                <p class="materi-label">Data belum dapat disimpan. Periksa isian berikut.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="materi-back">
            <x-filament::button
                tag="a"
                :href="$overviewUrl"
                color="gray"
                size="sm"
                :outlined="true"
            >
                Kembali ke daftar mata pelajaran
            </x-filament::button>
        </div>

        <x-filament::section heading="Identitas pengisian" description="Data disimpan untuk guru, mata pelajaran, dan periode berjalan.">
            <div class="materi-identity">
                <div class="materi-identity-item">
                    <span class="materi-help">Nama Guru</span>
                    <span class="materi-label">{{ $guru?->user?->name ?? 'Nama guru belum tersedia' }}</span>
                </div>
                <div class="materi-identity-item">
                    <span class="materi-help">Kelas</span>
                    <span class="materi-label">{{ $class->nama_kelas }}</span>
                </div>
                <div class="materi-identity-item">
                    <span class="materi-help">Mata Pelajaran</span>
                    <span class="materi-label">{{ $subject->nama_mapel }}</span>
                </div>
                <div class="materi-identity-item">
                    <span class="materi-help">Semester</span>
                    <span class="materi-label">{{ $period['semester'] }}</span>
                </div>
                <div class="materi-identity-item">
                    <span class="materi-help">Tahun Ajaran</span>
                    <span class="materi-label">{{ $period['tahun_ajaran'] }}</span>
                </div>
            </div>
        </x-filament::section>

        <div class="materi-info" role="note">
            <p class="materi-info-title">Informasi</p>
            <ol>
                <li>Jumlah karakter Tujuan Pembelajaran maksimal {{ $maxKarakterTujuan }} karakter (menyesuaikan dengan aplikasi e-rapor).</li>
                <li>Penulisan setiap Tujuan Pembelajaran diawali dengan huruf kecil dan tidak menggunakan tanda baca titik (.) di akhir kalimat.</li>
                <li>Setiap Lingkup Materi wajib memiliki minimal satu Tujuan Pembelajaran. Jumlah tujuan dapat ditambah sesuai kebutuhan.</li>
            </ol>
        </div>

        <x-filament::section heading="Lingkup materi dan tujuan pembelajaran" :description="'Kelas '.$class->nama_kelas.'. Mata pelajaran: '.$subject->nama_mapel.'. Simpan ulang untuk memperbarui data periode ini.'">
            <form wire:submit="simpan" class="materi-form">
                <div class="materi-table-scroll" role="region" aria-label="Tabel isian lingkup materi dan tujuan pembelajaran" tabindex="0">
                    <table class="materi-table">
                        <caption class="materi-sr-only">Lingkup materi dan tujuan pembelajaran {{ $subject->nama_mapel }} kelas {{ $class->nama_kelas }} semester {{ $period['semester'] }} tahun ajaran {{ $period['tahun_ajaran'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="materi-number">No</th>
                                <th scope="col">Lingkup Materi</th>
                                <th scope="col">Tujuan Pembelajaran</th>
                                <th scope="col" class="materi-actions-column">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lingkup as $index => $row)
                                @php
                                    $purposes = is_array($row) && is_array($row['tujuan'] ?? null) ? $row['tujuan'] : [];
                                @endphp
                                <tr wire:key="materi-lingkup-{{ $index }}">
                                    <td class="materi-number">{{ $loop->iteration }}</td>
                                    <td class="materi-material">
                                        <label class="materi-sr-only" for="materi-lingkup-{{ $index }}">Lingkup materi baris {{ $loop->iteration }}</label>
                                        <textarea
                                            id="materi-lingkup-{{ $index }}"
                                            class="materi-textarea"
                                            wire:model="lingkup.{{ $index }}.materi"
                                            rows="6"
                                            maxlength="500"
                                            required
                                            aria-describedby="materi-lingkup-error-{{ $index }}"
                                            aria-invalid="{{ $errors->has('lingkup.'.$index.'.materi') ? 'true' : 'false' }}"
                                        ></textarea>
                                        <div id="materi-lingkup-error-{{ $index }}">
                                            @error('lingkup.'.$index.'.materi')
                                                <p class="materi-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </td>
                                    <td>
                                        <div class="materi-goals">
                                            @foreach ($purposes as $goal => $tujuan)
                                                <div class="materi-goal" wire:key="materi-tujuan-{{ $index }}-{{ $goal }}">
                                                    <div class="materi-goal-header">
                                                        <label class="materi-goal-label" for="materi-tujuan-{{ $index }}-{{ $goal }}">Tujuan {{ $goal + 1 }}</label>
                                                        @if (count($purposes) > 1)
                                                            <button
                                                                type="button"
                                                                class="materi-goal-remove"
                                                                wire:click="hapusTujuan({{ $index }}, {{ $goal }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="hapusTujuan,tambahTujuan,simpan"
                                                                aria-label="Hapus tujuan {{ $goal + 1 }} pada lingkup materi baris {{ $loop->parent->iteration }}"
                                                            >
                                                                Hapus
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <x-filament::input.wrapper :valid="! $errors->has('lingkup.'.$index.'.tujuan.'.$goal)">
                                                        <x-filament::input
                                                            id="materi-tujuan-{{ $index }}-{{ $goal }}"
                                                            type="text"
                                                            wire:model="lingkup.{{ $index }}.tujuan.{{ $goal }}"
                                                            maxlength="{{ $maxKarakterTujuan }}"
                                                            placeholder="Tulis tujuan dengan huruf kecil"
                                                            aria-describedby="materi-tujuan-error-{{ $index }}-{{ $goal }}"
                                                            aria-invalid="{{ $errors->has('lingkup.'.$index.'.tujuan.'.$goal) ? 'true' : 'false' }}"
                                                        />
                                                    </x-filament::input.wrapper>
                                                    <div id="materi-tujuan-error-{{ $index }}-{{ $goal }}">
                                                        @error('lingkup.'.$index.'.tujuan.'.$goal)
                                                            <p class="materi-error">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="materi-goal-add">
                                                <x-filament::button
                                                    type="button"
                                                    color="gray"
                                                    size="sm"
                                                    :outlined="true"
                                                    :loading-indicator="false"
                                                    wire:click="tambahTujuan({{ $index }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="tambahTujuan,hapusTujuan,simpan"
                                                >
                                                    Tambah tujuan
                                                </x-filament::button>
                                            </div>
                                            <div id="materi-tujuan-error-{{ $index }}">
                                                @error('lingkup.'.$index.'.tujuan')
                                                    <p class="materi-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </td>
                                    <td class="materi-row-action">
                                        <x-filament::button
                                            type="button"
                                            color="danger"
                                            size="sm"
                                            :outlined="true"
                                            :loading-indicator="false"
                                            wire:click="hapusLingkup({{ $index }})"
                                            wire:loading.attr="disabled"
                                            wire:target="hapusLingkup,tambahLingkup,simpan"
                                            aria-label="Hapus lingkup materi baris {{ $loop->iteration }}"
                                        >
                                            Hapus
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="materi-footer">
                    <p class="materi-help">Perubahan baru tersimpan setelah menekan Simpan. Baris kosong tidak dapat disimpan.</p>
                    <div class="materi-actions">
                        <x-filament::button
                            type="button"
                            color="gray"
                            :outlined="true"
                            :loading-indicator="false"
                            wire:click="tambahLingkup"
                            wire:loading.attr="disabled"
                            wire:target="tambahLingkup,hapusLingkup,simpan"
                        >
                            Tambah lingkup materi
                        </x-filament::button>
                        <x-filament::button
                            type="submit"
                            :loading-indicator="false"
                            wire:loading.attr="disabled"
                            wire:target="simpan,tambahLingkup,hapusLingkup"
                        >
                            <span wire:loading.remove wire:target="simpan">Simpan</span>
                            <span wire:loading wire:target="simpan">Menyimpan...</span>
                        </x-filament::button>
                    </div>
                </div>
                <p class="materi-help" role="status" wire:loading wire:target="simpan">Menyimpan lingkup materi dan tujuan pembelajaran.</p>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
