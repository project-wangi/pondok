<?php

namespace Database\Seeders;

use App\Models\AbsenPeminatan;
use App\Models\AbsenPiket;
use App\Models\AsesmenFormatif;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPeminatan;
use App\Models\JadwalPiket;
use App\Models\JenisPeminatan;
use App\Models\JurnalMengajar;
use App\Models\JurnalMengajarBaris;
use App\Models\Kelas;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SesiMengajar;
use App\Models\SikapKeaktifan;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data akademik contoh: guru, kelas VII sampai XII, mata pelajaran, santri, jadwal,
 * presensi siswa, absensi piket, absensi peminatan, lingkup materi, jurnal mengajar,
 * asesmen formatif, serta sikap dan keaktifan.
 *
 * Hari libur mingguan adalah Jumat. Tidak ada jadwal, presensi, piket, peminatan,
 * jurnal, maupun asesmen yang dibuat pada hari Jumat. Absensi peminatan hanya
 * dilaksanakan pada hari Kamis.
 */
class DataAkademikSeeder extends Seeder
{
    /** @var list<string> */
    private const HARI_LIBUR = ['Jumat'];

    /** @var list<string> */
    private const HARI_SEKOLAH = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Sabtu', 'Minggu'];

    /** @var list<string> */
    private const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** @var list<array{0: string, 1: string}> */
    private const SLOT_WAKTU = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:15:00', '11:45:00'],
    ];

    /**
     * Susunan kelas, guru pengampu, dan mata pelajaran per slot. Setiap kelas memakai
     * satu guru sehingga tidak ada guru yang mengajar dua kelas pada jam yang sama.
     *
     * @var array<string, array{guru: string, mapel: list<string>}>
     */
    private const KELAS = [
        'VII' => ['guru' => 'ahmad', 'mapel' => ["Al-Qur'an (Tahfizh)", 'Bahasa Arab', 'Matematika']],
        'VIII' => ['guru' => 'siti', 'mapel' => ['Fiqih', 'Bahasa Indonesia', 'Bahasa Inggris']],
        'IX' => ['guru' => 'rizki', 'mapel' => ["Al-Qur'an (Tahfizh)", 'Matematika', 'Bahasa Inggris']],
        'X' => ['guru' => 'fatimah', 'mapel' => ["Al-Qur'an (Tahfizh)", 'Fiqih', 'Matematika']],
        'XI' => ['guru' => 'abdullah', 'mapel' => ['Bahasa Arab', 'Bahasa Indonesia', 'Bahasa Inggris']],
        'XII' => ['guru' => 'khadijah', 'mapel' => ['Matematika', 'Bahasa Inggris', 'Bahasa Arab']],
    ];

    /** @var array<string, int> */
    private const TINGKAT = [
        'VII' => 7,
        'VIII' => 8,
        'IX' => 9,
        'X' => 10,
        'XI' => 11,
        'XII' => 12,
    ];

    /** @var array<string, int> */
    private const USIA_KELAS = [
        'VII' => 13,
        'VIII' => 14,
        'IX' => 15,
        'X' => 16,
        'XI' => 17,
        'XII' => 18,
    ];

    /** @var array<string, list<string>> */
    private const TOPIK_MATERI = [
        "Al-Qur'an (Tahfizh)" => ['Makharijul huruf', 'Hukum tajwid dasar', 'Hafalan surah pendek', "Muraja'ah juz 30"],
        'Bahasa Arab' => ['Kosakata harian', "Fi'il madhi dan mudhari'", 'Percakapan perkenalan', 'Membaca teks pendek'],
        'Fiqih' => ['Thaharah', 'Shalat berjamaah', 'Puasa', 'Zakat'],
        'Matematika' => ['Bilangan bulat', 'Aljabar dasar', 'Persamaan linear', 'Geometri bidang'],
        'Bahasa Indonesia' => ['Teks deskripsi', 'Struktur kalimat', 'Menulis paragraf', 'Membaca pemahaman'],
        'Bahasa Inggris' => ['Greetings', 'Simple present tense', 'Reading comprehension', 'Writing short text'],
    ];

    /** @var array<string, string> */
    private const PIKET_HARIAN = [
        'Senin' => 'ahmad',
        'Selasa' => 'siti',
        'Rabu' => 'rizki',
        'Kamis' => 'fatimah',
        'Sabtu' => 'abdullah',
        'Minggu' => 'khadijah',
    ];

    /** Peminatan hanya dijadwalkan dan dicatat pada hari Kamis. */
    private const HARI_PEMINATAN = 'Kamis';

    /** @var list<array{jenis: string, nominal: int, guru: string}> */
    private const PEMINATAN = [
        ['jenis' => "Tahfizh Al-Qur'an", 'nominal' => 150000, 'guru' => 'ahmad'],
        ['jenis' => 'Bahasa Arab', 'nominal' => 100000, 'guru' => 'siti'],
        ['jenis' => 'Bahasa Inggris', 'nominal' => 125000, 'guru' => 'khadijah'],
    ];

    /** @var list<string> */
    private const NAMA_DEPAN = ['Ahmad', 'Muhammad', 'Abdullah', 'Umar', 'Ali', 'Hasan', 'Fatimah', 'Aisyah', 'Khadijah', 'Zainab', 'Maryam', 'Salsabila'];

    /** @var list<string> */
    private const NAMA_BELAKANG = ['Fauzi', 'Ramadhan', 'Hidayat', 'Nurhaliza', 'Maulana', 'Pratama', 'Zahra', 'Rahmawati', 'Saputra', 'Aulia', 'Wibowo', 'Hakim'];

    public function run(): void
    {
        $period = JadwalPelajaran::activePeriod();

        $teachers = $this->seedTeachers();
        $classes = $this->seedClasses();
        $subjects = $this->seedSubjects();
        $students = $this->seedStudents($classes);
        $schedules = $this->seedSchedules($teachers, $classes, $subjects, $period);
        $materials = $this->seedMaterials($schedules, $period);

        $this->seedAttendance($schedules, $students, $period);
        $this->seedJournals($schedules, $materials, $period);
        $this->seedFormativeAssessments($schedules, $students, $materials, $period);
        $this->seedAttitudes($schedules, $students, $period);
        $this->seedPicket($teachers, $classes, $students);
        $this->seedSpecializations($teachers, $students);

        $this->command?->info('Data akademik contoh berhasil dibuat untuk kelas VII sampai XII. Hari libur: Jumat.');
        $this->command?->info('Akun guru (kata sandi: password):');

        foreach (['ustadz.ahmad', 'ustadzah.siti', 'ustadz.rizki', 'ustadzah.fatimah', 'ustadz.abdullah', 'ustadzah.khadijah'] as $email) {
            $this->command?->info('- '.$email.'@pondok.test');
        }
    }

    /**
     * @return array<string, array{id: int, user_id: int, nama: string}>
     */
    private function seedTeachers(): array
    {
        $teachers = [];

        $data = [
            'ahmad' => ['nama' => 'Ust. Ahmad Fauzi', 'email' => 'ustadz.ahmad@pondok.test'],
            'siti' => ['nama' => 'Ustzh. Siti Aminah', 'email' => 'ustadzah.siti@pondok.test'],
            'rizki' => ['nama' => 'Ust. Muhammad Rizki', 'email' => 'ustadz.rizki@pondok.test'],
            'fatimah' => ['nama' => 'Ustzh. Fatimah Zahra', 'email' => 'ustadzah.fatimah@pondok.test'],
            'abdullah' => ['nama' => 'Ust. Abdullah Hakim', 'email' => 'ustadz.abdullah@pondok.test'],
            'khadijah' => ['nama' => 'Ustzh. Khadijah Nur', 'email' => 'ustadzah.khadijah@pondok.test'],
        ];

        foreach ($data as $key => $row) {
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                ['name' => $row['nama'], 'password' => 'password'],
            );

            $guru = Guru::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'jenis_kelamin' => str_contains($row['nama'], 'Ustzh') ? 'Perempuan' : 'Laki-laki',
                    'agama' => 'Islam',
                ],
            );

            $teachers[$key] = ['id' => $guru->id, 'user_id' => $user->id, 'nama' => $user->name];
        }

        return $teachers;
    }

    /**
     * @return array<string, array{id: int, nama: string}>
     */
    private function seedClasses(): array
    {
        $classes = [];

        foreach (array_keys(self::KELAS) as $nama) {
            $kelas = Kelas::firstOrCreate(['nama_kelas' => $nama]);
            $classes[$nama] = ['id' => $kelas->id, 'nama' => $kelas->nama_kelas];
        }

        return $classes;
    }

    /**
     * @return array<string, array{id: int, nama: string}>
     */
    private function seedSubjects(): array
    {
        $subjects = [];

        foreach (array_keys(self::TOPIK_MATERI) as $nama) {
            $mapel = MataPelajaran::firstOrCreate(['nama_mapel' => $nama]);
            $subjects[$nama] = ['id' => $mapel->id, 'nama' => $mapel->nama_mapel];
        }

        return $subjects;
    }

    /**
     * @param  array<string, array{id: int, nama: string}>  $classes
     * @return array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>
     */
    private function seedStudents(array $classes): array
    {
        $students = [];

        foreach (array_values($classes) as $classIndex => $class) {
            $students[$class['id']] = [];

            for ($i = 1; $i <= 12; $i++) {
                $tingkat = self::TINGKAT[$class['nama']];

                $murid = Murid::updateOrCreate(
                    ['email' => sprintf('murid.%s.%02d@pondok.test', strtolower($class['nama']), $i)],
                    [
                        'kelas_id' => $class['id'],
                        'nama' => self::NAMA_DEPAN[$i - 1].' '.self::NAMA_BELAKANG[(($i - 1) * 2 + $classIndex) % count(self::NAMA_BELAKANG)],
                        'nis' => '2026'.str_pad((string) ($tingkat * 100 + $i), 5, '0', STR_PAD_LEFT),
                        'nisn' => (string) (1000000000 + $tingkat * 100 + $i),
                        'jenis_kelamin' => $i <= 6 ? 'Laki-laki' : 'Perempuan',
                        'tempat_lahir' => 'Surabaya',
                        'tanggal_lahir' => CarbonImmutable::now()->subYears(self::USIA_KELAS[$class['nama']] ?? 16)->subDays($classIndex * 40 + $i)->toDateString(),
                        'agama' => 'Islam',
                        'alamat' => 'Jl. Pendidikan No. '.$i.', Surabaya',
                        'nohp_siswa' => '0812'.str_pad((string) ($classIndex * 1000 + $i), 8, '0', STR_PAD_LEFT),
                    ],
                );

                $students[$class['id']][] = [
                    'id' => $murid->id,
                    'nama' => $murid->nama,
                    'nis' => $murid->nis,
                    'jenis_kelamin' => $murid->jenis_kelamin,
                    'kelas' => $class['nama'],
                ];
            }
        }

        return $students;
    }

    /**
     * @param  array<string, array{id: int, user_id: int, nama: string}>  $teachers
     * @param  array<string, array{id: int, nama: string}>  $classes
     * @param  array<string, array{id: int, nama: string}>  $subjects
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>
     */
    private function seedSchedules(array $teachers, array $classes, array $subjects, array $period): array
    {
        $schedules = [];

        foreach (self::KELAS as $namaKelas => $info) {
            $class = $classes[$namaKelas];
            $teacher = $teachers[$info['guru']];

            foreach (self::HARI_SEKOLAH as $hari) {
                foreach ($info['mapel'] as $slotIndex => $subjectName) {
                    $subject = $subjects[$subjectName];
                    [$jamMulai, $jamSelesai] = self::SLOT_WAKTU[$slotIndex];

                    $schedule = JadwalPelajaran::firstOrCreate(
                        [
                            'kelas_id' => $class['id'],
                            'guru_id' => $teacher['id'],
                            'mata_pelajaran' => $subject['id'],
                            'hari' => $hari,
                            'semester' => $period['semester'],
                            'tahun_ajaran' => $period['tahun_ajaran'],
                        ],
                        ['jam_mulai' => $jamMulai, 'jam_selesai' => $jamSelesai],
                    );

                    $schedules[] = [
                        'model' => $schedule,
                        'guru' => $teacher,
                        'kelas' => $class,
                        'mapel' => $subject,
                        'hari' => $hari,
                        'jam_mulai' => (string) ($schedule->jam_mulai ?? $jamMulai),
                        'jam_selesai' => (string) ($schedule->jam_selesai ?? $jamSelesai),
                    ];
                }
            }
        }

        return $schedules;
    }

    /**
     * @param  list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>  $schedules
     * @param  array{semester: string, tahun_ajaran: string}  $period
     * @return array<string, list<array{id: int, materi: string, tujuan: list<array{id: int, teks: string}>}>>
     */
    private function seedMaterials(array $schedules, array $period): array
    {
        $materials = [];

        foreach ($schedules as $schedule) {
            $key = $schedule['guru']['id'].'-'.$schedule['kelas']['id'].'-'.$schedule['mapel']['id'];

            if (isset($materials[$key])) {
                continue;
            }

            $materials[$key] = [];
            $topics = self::TOPIK_MATERI[$schedule['mapel']['nama']]
                ?? ['Materi pengantar', 'Latihan terbimbing', 'Evaluasi materi', 'Pengayaan'];

            foreach ($topics as $index => $topic) {
                $lingkup = LingkupMateri::updateOrCreate(
                    [
                        'guru_id' => $schedule['guru']['id'],
                        'kelas_id' => $schedule['kelas']['id'],
                        'mata_pelajaran_id' => $schedule['mapel']['id'],
                        'semester' => $period['semester'],
                        'tahun_ajaran' => $period['tahun_ajaran'],
                        'urutan' => $index + 1,
                    ],
                    ['materi' => $topic],
                );

                $goals = [];

                foreach ($this->tujuanPembelajaran($topic) as $goalIndex => $teks) {
                    $tujuan = TujuanPembelajaran::updateOrCreate(
                        ['lingkup_materi_id' => $lingkup->id, 'urutan' => $goalIndex + 1],
                        ['teks' => $teks],
                    );

                    $goals[] = ['id' => $tujuan->id, 'teks' => $teks];
                }

                $materials[$key][] = ['id' => $lingkup->id, 'materi' => $topic, 'tujuan' => $goals];
            }
        }

        return $materials;
    }

    /**
     * @param  list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>  $schedules
     * @param  array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>  $students
     * @param  array{semester: string, tahun_ajaran: string}  $period
     */
    private function seedAttendance(array $schedules, array $students, array $period): void
    {
        $now = now();

        foreach ($schedules as $schedule) {
            foreach ($this->recentDates($schedule['hari']) as $date) {
                $session = $this->createOncePerDate(
                    SesiMengajar::class,
                    ['jadwal_pelajaran_id' => $schedule['model']->id],
                    $date,
                    [
                        'guru_id' => $schedule['guru']['id'],
                        'kelas_id' => $schedule['kelas']['id'],
                        'pencatat_id' => $schedule['guru']['user_id'],
                        'revisi' => 1,
                        'semester' => $period['semester'],
                        'tahun_ajaran' => $period['tahun_ajaran'],
                        'nama_guru' => $schedule['guru']['nama'],
                        'nama_kelas' => $schedule['kelas']['nama'],
                        'nama_mapel' => $schedule['mapel']['nama'],
                        'jam_mulai' => $schedule['jam_mulai'],
                        'jam_selesai' => $schedule['jam_selesai'],
                        'dicatat_pada' => $date->setTimeFromTimeString($schedule['jam_selesai']),
                    ],
                );

                if ($session === null) {
                    continue;
                }

                $rows = [];

                foreach ($students[$schedule['kelas']['id']] as $index => $student) {
                    $status = $this->statusPresensi($index);

                    $rows[] = [
                        'sesi_mengajar_id' => $session->id,
                        'murid_id' => $student['id'],
                        'nama_murid' => $student['nama'],
                        'nis' => $student['nis'],
                        'status' => $status,
                        'catatan' => $status === 'Hadir' ? null : 'Keterangan '.$status.' dari wali santri',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $this->insertChunked('presensi_murids', $rows);
            }
        }
    }

    /**
     * @param  list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>  $schedules
     * @param  array<string, list<array{id: int, materi: string, tujuan: list<array{id: int, teks: string}>}>>  $materials
     * @param  array{semester: string, tahun_ajaran: string}  $period
     */
    private function seedJournals(array $schedules, array $materials, array $period): void
    {
        foreach ($schedules as $schedule) {
            $key = $schedule['guru']['id'].'-'.$schedule['kelas']['id'].'-'.$schedule['mapel']['id'];
            $group = $materials[$key] ?? [];

            if ($group === []) {
                continue;
            }

            foreach ($this->recentDates($schedule['hari']) as $dateIndex => $date) {
                $journal = $this->createOncePerDate(
                    JurnalMengajar::class,
                    ['jadwal_pelajaran_id' => $schedule['model']->id],
                    $date,
                    [
                        'guru_id' => $schedule['guru']['id'],
                        'kelas_id' => $schedule['kelas']['id'],
                        'mata_pelajaran_id' => $schedule['mapel']['id'],
                        'pencatat_id' => $schedule['guru']['user_id'],
                        'revisi' => 1,
                        'semester' => $period['semester'],
                        'tahun_ajaran' => $period['tahun_ajaran'],
                        'nama_guru' => $schedule['guru']['nama'],
                        'nama_kelas' => $schedule['kelas']['nama'],
                        'nama_mapel' => $schedule['mapel']['nama'],
                        'jam_mulai' => $schedule['jam_mulai'],
                        'jam_selesai' => $schedule['jam_selesai'],
                        'dicatat_pada' => $date->setTimeFromTimeString($schedule['jam_selesai']),
                    ],
                );

                if ($journal === null) {
                    continue;
                }

                $lingkup = $group[$dateIndex % count($group)];
                $tujuan = $lingkup['tujuan'][0] ?? ['id' => null, 'teks' => 'siswa memahami materi'];

                JurnalMengajarBaris::create([
                    'jurnal_mengajar_id' => $journal->id,
                    'lingkup_materi_id' => $lingkup['id'],
                    'tujuan_pembelajaran_id' => $tujuan['id'],
                    'lingkup_materi' => $lingkup['materi'],
                    'tujuan_pembelajaran' => $tujuan['teks'],
                    'kegiatan' => 'Membahas '.$lingkup['materi'].' melalui penjelasan, tanya jawab, dan latihan terbimbing.',
                    'catatan' => 'Sebagian santri perlu pengulangan pada bagian tertentu.',
                    'tindak_lanjut' => "Latihan mandiri dan muraja'ah sebelum pertemuan berikutnya.",
                    'urutan' => 1,
                ]);
            }
        }
    }

    /**
     * @param  list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>  $schedules
     * @param  array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>  $students
     * @param  array<string, list<array{id: int, materi: string, tujuan: list<array{id: int, teks: string}>}>>  $materials
     * @param  array{semester: string, tahun_ajaran: string}  $period
     */
    private function seedFormativeAssessments(array $schedules, array $students, array $materials, array $period): void
    {
        $now = now();

        foreach ($schedules as $schedule) {
            $key = $schedule['guru']['id'].'-'.$schedule['kelas']['id'].'-'.$schedule['mapel']['id'];

            if (! isset($materials[$key])) {
                continue;
            }

            $assessment = AsesmenFormatif::firstOrCreate(
                [
                    'guru_id' => $schedule['guru']['id'],
                    'kelas_id' => $schedule['kelas']['id'],
                    'mata_pelajaran_id' => $schedule['mapel']['id'],
                    'semester' => $period['semester'],
                    'tahun_ajaran' => $period['tahun_ajaran'],
                ],
                [
                    'pencatat_id' => $schedule['guru']['user_id'],
                    'revisi' => 1,
                    'nama_guru' => $schedule['guru']['nama'],
                    'nama_kelas' => $schedule['kelas']['nama'],
                    'nama_mapel' => $schedule['mapel']['nama'],
                    'dicatat_pada' => $now,
                ],
            );

            if (! $assessment->wasRecentlyCreated) {
                continue;
            }

            $rows = [];

            foreach ($students[$schedule['kelas']['id']] as $studentIndex => $student) {
                foreach ($materials[$key] as $materialIndex => $lingkup) {
                    foreach ($lingkup['tujuan'] as $goalIndex => $tujuan) {
                        $rows[] = [
                            'asesmen_formatif_id' => $assessment->id,
                            'murid_id' => $student['id'],
                            'lingkup_materi_id' => $lingkup['id'],
                            'tujuan_pembelajaran_id' => $tujuan['id'],
                            'nama_murid' => $student['nama'],
                            'nis' => $student['nis'],
                            'jenis_kelamin' => $student['jenis_kelamin'],
                            'lingkup_materi' => $lingkup['materi'],
                            'tujuan_pembelajaran' => $tujuan['teks'],
                            'nilai' => 70 + (($studentIndex * 3 + $materialIndex + $goalIndex) % 31),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            $this->insertChunked('asesmen_formatif_nilais', $rows);
        }
    }

    /**
     * @param  list<array{model: JadwalPelajaran, guru: array{id: int, user_id: int, nama: string}, kelas: array{id: int, nama: string}, mapel: array{id: int, nama: string}, hari: string, jam_mulai: string, jam_selesai: string}>  $schedules
     * @param  array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>  $students
     * @param  array{semester: string, tahun_ajaran: string}  $period
     */
    private function seedAttitudes(array $schedules, array $students, array $period): void
    {
        $now = now();
        $sikap = ['A', 'A', 'B', 'A', 'B', 'B', 'A', 'C', 'B', 'A', 'B', 'A'];
        $keaktifan = ['A', 'B', 'A', 'B', 'A', 'A', 'B', 'B', 'A', 'C', 'B', 'A'];

        foreach ($schedules as $schedule) {
            $record = SikapKeaktifan::firstOrCreate(
                [
                    'guru_id' => $schedule['guru']['id'],
                    'kelas_id' => $schedule['kelas']['id'],
                    'mata_pelajaran_id' => $schedule['mapel']['id'],
                    'semester' => $period['semester'],
                    'tahun_ajaran' => $period['tahun_ajaran'],
                ],
                [
                    'pencatat_id' => $schedule['guru']['user_id'],
                    'revisi' => 1,
                    'nama_guru' => $schedule['guru']['nama'],
                    'nama_kelas' => $schedule['kelas']['nama'],
                    'nama_mapel' => $schedule['mapel']['nama'],
                    'dicatat_pada' => $now,
                ],
            );

            if (! $record->wasRecentlyCreated) {
                continue;
            }

            $rows = [];

            foreach ($students[$schedule['kelas']['id']] as $index => $student) {
                $rows[] = [
                    'sikap_keaktifan_id' => $record->id,
                    'murid_id' => $student['id'],
                    'nama_murid' => $student['nama'],
                    'nis' => $student['nis'],
                    'jenis_kelamin' => $student['jenis_kelamin'],
                    'sikap' => $sikap[$index % count($sikap)],
                    'keaktifan' => $keaktifan[$index % count($keaktifan)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->insertChunked('sikap_keaktifan_nilais', $rows);
        }
    }

    /**
     * @param  array<string, array{id: int, user_id: int, nama: string}>  $teachers
     * @param  array<string, array{id: int, nama: string}>  $classes
     * @param  array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>  $students
     */
    private function seedPicket(array $teachers, array $classes, array $students): void
    {
        $now = now();

        foreach (self::PIKET_HARIAN as $hari => $teacherKey) {
            JadwalPiket::firstOrCreate(
                ['hari' => $hari, 'guru_id' => $teachers[$teacherKey]['id']],
                ['ket' => 'Piket harian kelas'],
            );
        }

        foreach ($this->recentSchoolDates() as $date) {
            $hari = self::NAMA_HARI[$date->dayOfWeek];
            $teacher = $teachers[self::PIKET_HARIAN[$hari] ?? 'ahmad'];

            foreach ($classes as $class) {
                $absen = $this->createOncePerDate(
                    AbsenPiket::class,
                    ['kelas_id' => $class['id']],
                    $date,
                    [
                        'guru_id' => $teacher['id'],
                        'pencatat_id' => $teacher['user_id'],
                        'revisi' => 1,
                        'nama_guru' => $teacher['nama'],
                        'nama_kelas' => $class['nama'],
                        'dicatat_pada' => $date->setTime(7, 0),
                    ],
                );

                if ($absen === null) {
                    continue;
                }

                $rows = [];

                foreach ($students[$class['id']] as $index => $student) {
                    $masuk = $this->statusMasukPiket($index);

                    $rows[] = [
                        'absen_piket_id' => $absen->id,
                        'murid_id' => $student['id'],
                        'nama_murid' => $student['nama'],
                        'nis' => $student['nis'],
                        'masuk' => $masuk,
                        'pulang' => in_array($masuk, ['Hadir', 'Terlambat'], true) ? 'Pulang' : $masuk,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $this->insertChunked('absen_piket_murids', $rows);
            }
        }
    }

    /**
     * @param  array<string, array{id: int, user_id: int, nama: string}>  $teachers
     * @param  array<int, list<array{id: int, nama: string, nis: ?string, jenis_kelamin: string, kelas: string}>>  $students
     */
    private function seedSpecializations(array $teachers, array $students): void
    {
        $now = now();
        $specializations = [];

        foreach (self::PEMINATAN as $row) {
            $jenis = JenisPeminatan::firstOrCreate(
                ['nama_peminatan' => $row['jenis']],
                ['nominal' => $row['nominal']],
            );

            $specializations[$row['jenis']] = [
                'id' => $jenis->id,
                'nama' => $jenis->nama_peminatan,
                'guru' => $teachers[$row['guru']],
            ];

            JadwalPeminatan::firstOrCreate(
                ['hari' => self::HARI_PEMINATAN, 'jenis_peminatan_id' => $jenis->id],
                [
                    'guru_id' => $teachers[$row['guru']]['id'],
                    'jam_mulai' => '15:30:00',
                    'jam_selesai' => '17:00:00',
                ],
            );
        }

        $names = array_keys($specializations);
        $counter = 0;

        foreach ($students as $roster) {
            foreach ($roster as $student) {
                DB::table('jenis_peminatan_murid')->insertOrIgnore([
                    'murid_id' => $student['id'],
                    'jenis_peminatan_id' => $specializations[$names[$counter % count($names)]]['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $counter++;
            }
        }

        foreach ($specializations as $info) {
            $roster = Murid::query()
                ->whereHas('jenisPeminatans', fn ($query) => $query->where('jenis_peminatans.id', $info['id']))
                ->with('kelas')
                ->orderBy('id')
                ->get();

            foreach ($this->recentDates(self::HARI_PEMINATAN) as $date) {
                $absen = $this->createOncePerDate(
                    AbsenPeminatan::class,
                    ['jenis_peminatan_id' => $info['id']],
                    $date,
                    [
                        'guru_id' => $info['guru']['id'],
                        'pencatat_id' => $info['guru']['user_id'],
                        'revisi' => 1,
                        'jam_mulai' => '15:30:00',
                        'jam_selesai' => '17:00:00',
                        'nama_guru' => $info['guru']['nama'],
                        'nama_peminatan' => $info['nama'],
                        'dicatat_pada' => $date->setTime(17, 0),
                    ],
                );

                if ($absen === null) {
                    continue;
                }

                $rows = [];

                foreach ($roster as $index => $murid) {
                    $rows[] = [
                        'absen_peminatan_id' => $absen->id,
                        'murid_id' => $murid->id,
                        'nama_murid' => $murid->nama,
                        'nis' => $murid->nis,
                        'nama_kelas' => $murid->kelas?->nama_kelas,
                        'status' => $this->statusPresensi($index),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $this->insertChunked('absen_peminatan_murids', $rows);
            }
        }
    }

    /**
     * Buat satu baris per kombinasi kunci dan tanggal, tanpa duplikasi.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function createOncePerDate(string $model, array $keys, CarbonImmutable $date, array $values): ?Model
    {
        $exists = $model::query()
            ->where($keys)
            ->whereDate('tanggal', $date->toDateString())
            ->exists();

        if ($exists) {
            return null;
        }

        return $model::create([
            ...$keys,
            'tanggal' => $date->toDateString(),
            ...$values,
        ]);
    }

    /** @return list<string> */
    private function tujuanPembelajaran(string $topic): array
    {
        $lower = mb_strtolower($topic);

        return [
            'siswa dapat menjelaskan '.$lower,
            'siswa dapat menerapkan '.$lower.' dalam latihan',
            'siswa dapat menyelesaikan soal tentang '.$lower,
        ];
    }

    /**
     * Dua tanggal terakhir untuk hari tertentu. Hari libur tidak pernah menghasilkan tanggal.
     *
     * @return list<CarbonImmutable>
     */
    private function recentDates(string $hari, int $count = 2): array
    {
        if (in_array($hari, self::HARI_LIBUR, true)) {
            return [];
        }

        $target = array_search($hari, self::NAMA_HARI, true);

        if ($target === false) {
            return [];
        }

        $dates = [];
        $cursor = CarbonImmutable::now()->startOfDay();
        $guard = 0;

        while (count($dates) < $count && $guard < 90) {
            if ($cursor->dayOfWeek === $target) {
                $dates[] = $cursor;
            }

            $cursor = $cursor->subDay();
            $guard++;
        }

        return $dates;
    }

    /**
     * Enam hari sekolah terakhir, tanpa hari libur.
     *
     * @return list<CarbonImmutable>
     */
    private function recentSchoolDates(int $count = 6): array
    {
        $dates = [];
        $cursor = CarbonImmutable::now()->startOfDay();
        $guard = 0;

        while (count($dates) < $count && $guard < 90) {
            if (! in_array(self::NAMA_HARI[$cursor->dayOfWeek], self::HARI_LIBUR, true)) {
                $dates[] = $cursor;
            }

            $cursor = $cursor->subDay();
            $guard++;
        }

        return $dates;
    }

    private function statusPresensi(int $index): string
    {
        return ['Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Sakit', 'Hadir', 'Hadir', 'Izin', 'Alpa'][$index % 12];
    }

    private function statusMasukPiket(int $index): string
    {
        return ['Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Hadir', 'Terlambat', 'Hadir', 'Hadir', 'Izin', 'Sakit'][$index % 12];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows, int $size = 50): void
    {
        foreach (array_chunk($rows, $size) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
