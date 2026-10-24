<?php

use App\Filament\Pages\InputERapor;
use App\Filament\Pages\IsiERapor;
use App\Models\AsesmenFormatif;
use App\Models\AsesmenFormatifNilai;
use App\Models\ERapor;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\NilaiUjian;
use App\Models\SesiMengajar;
use App\Models\SikapKeaktifan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

/** @param array<string, mixed> $attributes */
function raporTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function raporSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
{
    return JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $subject->id,
        'kelas_id' => $classId,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        ...$attributes,
    ]);
}

function raporPage(int $classId, int $subjectId): Testable
{
    return Livewire::test(IsiERapor::class, ['kelas' => $classId, 'mapel' => $subjectId]);
}

/** @param array<string, mixed> $attributes */
function raporRecord(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): ERapor
{
    return ERapor::query()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classId,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => $teacher->user->name,
        'nama_kelas' => 'Kelas X',
        'nama_mapel' => $subject->nama_mapel,
        'dicatat_pada' => now(),
        ...$attributes,
    ]);
}

/** @param list<int> $scores */
function raporFormative(Guru $teacher, MataPelajaran $subject, int $classId, Murid $student, array $scores): void
{
    $assessment = AsesmenFormatif::query()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classId,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => $teacher->user->name,
        'nama_kelas' => 'Kelas X',
        'nama_mapel' => $subject->nama_mapel,
        'dicatat_pada' => now(),
    ]);

    foreach ($scores as $score) {
        AsesmenFormatifNilai::query()->create([
            'asesmen_formatif_id' => $assessment->id,
            'murid_id' => $student->id,
            'nama_murid' => $student->nama,
            'nis' => $student->nis,
            'lingkup_materi' => 'materi',
            'tujuan_pembelajaran' => 'tujuan',
            'nilai' => $score,
        ]);
    }
}

/** @param list<array<int, int>> $componentScores */
function raporExam(Guru $teacher, MataPelajaran $subject, int $classId, array $componentScores): void
{
    $exam = NilaiUjian::query()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classId,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => $teacher->user->name,
        'nama_kelas' => 'Kelas X',
        'nama_mapel' => $subject->nama_mapel,
        'dicatat_pada' => now(),
    ]);

    foreach ($componentScores as $index => $scores) {
        $component = $exam->komponens()->create(['nama' => 'Ujian '.($index + 1), 'urutan' => $index + 1]);

        foreach ($scores as $studentId => $score) {
            $student = Murid::query()->find($studentId);
            $component->nilais()->create([
                'murid_id' => $studentId,
                'nama_murid' => $student?->nama ?? 'Siswa',
                'nis' => $student?->nis,
                'nilai' => $score,
            ]);
        }
    }
}

/** @param list<string> $statuses */
function raporAttendance(JadwalPelajaran $schedule, Murid $student, array $statuses): void
{
    foreach ($statuses as $index => $status) {
        $session = SesiMengajar::query()->create([
            'jadwal_pelajaran_id' => $schedule->id,
            'guru_id' => $schedule->guru_id,
            'kelas_id' => $schedule->kelas_id,
            'pencatat_id' => $schedule->guru?->user_id,
            'revisi' => 1,
            'tanggal' => '2026-10-0'.($index + 1),
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'nama_guru' => $schedule->guru?->user?->name ?? 'Guru',
            'nama_kelas' => $schedule->kelas?->nama_kelas ?? 'Kelas',
            'nama_mapel' => $schedule->mataPelajaran?->nama_mapel ?? 'Mapel',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '09:00:00',
            'dicatat_pada' => now(),
        ]);

        $session->presensiMurids()->create([
            'murid_id' => $student->id,
            'nama_murid' => $student->nama,
            'nis' => $student->nis,
            'status' => $status,
        ]);
    }
}

function raporSikap(Guru $teacher, MataPelajaran $subject, int $classId, Murid $student, string $sikap, string $keaktifan): void
{
    $entry = SikapKeaktifan::query()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classId,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => $teacher->user->name,
        'nama_kelas' => 'Kelas X',
        'nama_mapel' => $subject->nama_mapel,
        'dicatat_pada' => now(),
    ]);

    $entry->nilais()->create([
        'murid_id' => $student->id,
        'nama_murid' => $student->nama,
        'nis' => $student->nis,
        'jenis_kelamin' => $student->jenis_kelamin,
        'sikap' => $sikap,
        'keaktifan' => $keaktifan,
    ]);
}

test('is hidden from navigation and restricts access to the teachers class subject', function () {
    expect(IsiERapor::shouldRegisterNavigation())->toBeFalse();
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $url = IsiERapor::getUrl(['kelas' => $class->id, 'mapel' => $subject->id]);

    $this->get($url)->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
    Livewire::test(IsiERapor::class, ['kelas' => $class->id, 'mapel' => $subject->id])->assertForbidden();

    $this->actingAs($teacher->user);
    Livewire::test(IsiERapor::class, ['kelas' => 999999, 'mapel' => $subject->id])->assertNotFound();
    Livewire::test(IsiERapor::class, ['kelas' => $class->id, 'mapel' => 999999])->assertNotFound();
});

test('requires the class and subject pair to be taught in the running period', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $otherClass = KelasFactory::new()->create();
    $notTaughtSubject = MataPelajaranFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    raporSchedule(raporTeacher(), $subject, $otherClass->id);
    raporSchedule($teacher, $subject, $otherClass->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $this->actingAs($teacher->user);

    Livewire::test(IsiERapor::class, ['kelas' => $class->id, 'mapel' => $notTaughtSubject->id])->assertNotFound();
    Livewire::test(IsiERapor::class, ['kelas' => $otherClass->id, 'mapel' => $subject->id])->assertNotFound();
});

test('computes formative sumatif attendance sikap and keaktifan automatically', function () {
    $teacher = raporTeacher(['user_id' => User::factory()->create(['name' => 'Guru Rapor'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $schedule = raporSchedule($teacher, $subject, $class->id);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Ahmad', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Bilal', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    raporFormative($teacher, $subject, $class->id, $first, [80, 90]);
    raporExam($teacher, $subject, $class->id, [
        [$first->id => 70],
        [$first->id => 80],
    ]);
    raporAttendance($schedule, $first, ['Hadir', 'Alpa']);
    raporSikap($teacher, $subject, $class->id, $first, 'A', 'B');
    $this->actingAs($teacher->user);

    $page = raporPage($class->id, $subject->id)
        ->assertOk()
        ->assertSee('Kelas X')->assertSee('Tahfidz')->assertSee('Guru Rapor')
        ->assertSeeInOrder(['Jumlah Siswa', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSeeInOrder(['Jenis Ujian', '2'])
        ->assertSee('Nomor Induk')->assertSee('Formatif (F)')
        ->assertSee('Nilai Capaian Pembelajaran')->assertSee('Nilai Pendukung')
        ->assertSee('UH1')->assertSee('UH2')
        ->assertSee('Sumatif Lingkup Materi/Ujian Harian')
        ->assertSee('Rata-rata Sumatif Lingkup Materi')
        ->assertSee('Sumatif Tengah Semester (ASTS)')
        ->assertSee('Sumatif Akhir Semester (ASAS)')
        ->assertSee('Konversi Nilai Kehadiran (Angka)')
        ->assertSee('Nilai Sikap (Angka)')->assertSee('Nilai Keaktifan (Angka)')
        ->assertSee('Nilai Akhir Rapor')->assertSee('Ket. (Tuntas/Belum)')->assertSee('Peringkat')
        ->assertSeeHtml('wire:submit="simpan"');

    $rows = collect($page->viewData('rows'));
    $firstRow = $rows->firstWhere('student.id', $first->id);
    $secondRow = $rows->firstWhere('student.id', $second->id);

    expect($firstRow['formatif'])->toBe(85)
        ->and($firstRow['uh'])->toBe([70, 80])
        ->and($firstRow['sumatif'])->toBe(75)
        ->and($firstRow['kehadiran'])->toBe(50)
        ->and($firstRow['sikap'])->toBe(100)
        ->and($firstRow['keaktifan'])->toBe(90)
        ->and($firstRow['nilai_akhir'])->toBe(36)
        ->and($firstRow['ket'])->toBe('Belum')
        ->and($firstRow['peringkat'])->toBe(1)
        ->and($secondRow['formatif'])->toBeNull()
        ->and($secondRow['sumatif'])->toBeNull()
        ->and($secondRow['nilai_akhir'])->toBe(0)
        ->and($secondRow['ket'])->toBe('Belum')
        ->and($secondRow['peringkat'])->toBeNull();
});

test('shows a notice when the class has no students', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->assertSee('Belum ada siswa di kelas ini.')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('renders empty placeholder columns when no exam components exist', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    $page = raporPage($class->id, $subject->id)
        ->assertSee('UH')
        ->assertDontSee('UH1');

    expect($page->viewData('components'))->toHaveCount(0);
});

test('loads saved weights kkm and manual scores then uses them for the final score', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $rapor = raporRecord($teacher, $subject, $class->id, [
        'revisi' => 3,
        'bobot_formatif' => 0,
        'bobot_sumatif' => 0,
        'bobot_asts' => 0,
        'bobot_asas' => 100,
        'bobot_kehadiran' => 0,
        'bobot_sikap' => 0,
        'bobot_keaktifan' => 0,
        'kkm' => 80,
    ]);
    $rapor->nilais()->create([
        'murid_id' => $student->id,
        'nama_murid' => $student->nama,
        'nis' => $student->nis,
        'asts' => 70,
        'asas' => 90,
    ]);
    $this->actingAs($teacher->user);

    $page = raporPage($class->id, $subject->id)
        ->assertSet('eRaporId', $rapor->id)
        ->assertSet('revisi', 3)
        ->assertSet('bobotFormatif', '0')
        ->assertSet('bobotSumatif', '0')
        ->assertSet('bobotAsts', '0')
        ->assertSet('bobotAsas', '100')
        ->assertSet('bobotKehadiran', '0')
        ->assertSet('bobotSikap', '0')
        ->assertSet('bobotKeaktifan', '0')
        ->assertSet('kkm', '80')
        ->assertSet('nilai.'.$student->id.'.asts', 70)
        ->assertSet('nilai.'.$student->id.'.asas', 90);

    $row = collect($page->viewData('rows'))->firstWhere('student.id', $student->id);

    expect($row['nilai_akhir'])->toBe(90)
        ->and($row['ket'])->toBe('Tuntas')
        ->and($page->viewData('totalBobot'))->toBe(100);
});

test('saves manual scores with snapshots and default weights', function () {
    $teacher = raporTeacher(['user_id' => User::factory()->create(['name' => 'Guru Rapor'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    raporSchedule($teacher, $subject, $class->id);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Pertama', 'nis' => '101']);
    MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Kedua', 'nis' => '102']);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set('nilai.'.$first->id.'.asts', 60)
        ->set('nilai.'.$first->id.'.asas', 70)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('e-Rapor tersimpan')
        ->assertRedirect(InputERapor::getUrl());

    $rapor = ERapor::query()->sole();
    $this->assertDatabaseHas('e_rapors', [
        'id' => $rapor->id, 'guru_id' => $teacher->id, 'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id, 'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'nama_guru' => 'Guru Rapor',
        'nama_kelas' => 'Kelas X', 'nama_mapel' => 'Matematika',
        'bobot_formatif' => 10, 'bobot_sumatif' => 15, 'bobot_asts' => 20, 'bobot_asas' => 35,
        'bobot_kehadiran' => 5, 'bobot_sikap' => 5, 'bobot_keaktifan' => 10, 'kkm' => 75,
        'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseCount('e_rapor_nilais', 1);
    $this->assertDatabaseHas('e_rapor_nilais', [
        'e_rapor_id' => $rapor->id, 'murid_id' => $first->id,
        'nama_murid' => 'Siswa Pertama', 'nis' => '101', 'asts' => 60, 'asas' => 70,
    ]);
});

test('updates manual scores and weights without duplicates and keeps the recorded time', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    raporPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.asts', 60)
        ->set('nilai.'.$student->id.'.asas', 70)
        ->call('simpan')->assertHasNoErrors();
    $rapor = ERapor::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    raporPage($class->id, $subject->id)
        ->assertSet('nilai.'.$student->id.'.asts', 60)
        ->set('nilai.'.$student->id.'.asts', 80)
        ->set('nilai.'.$student->id.'.asas', 85)
        ->set('bobotFormatif', '5')
        ->set('bobotAsas', '40')
        ->set('kkm', '70')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('e_rapors', 1);
    $this->assertDatabaseCount('e_rapor_nilais', 1);
    $this->assertDatabaseHas('e_rapors', [
        'id' => $rapor->id, 'revisi' => 2, 'bobot_formatif' => 5, 'bobot_asas' => 40, 'kkm' => 70,
        'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('e_rapor_nilais', [
        'e_rapor_id' => $rapor->id, 'murid_id' => $student->id, 'asts' => 80, 'asas' => 85,
    ]);
});

test('removes stored manual scores when both fields are cleared', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    raporPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.asts', 60)
        ->set('nilai.'.$student->id.'.asas', 70)
        ->call('simpan')->assertHasNoErrors();

    raporPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.asts', null)
        ->set('nilai.'.$student->id.'.asas', null)
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('e_rapor_nilais', 0);
    $this->assertDatabaseHas('e_rapors', ['revisi' => 2]);
});

test('rejects weights that do not total 100 percent', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set('bobotAsas', '30')
        ->call('simpan')
        ->assertHasErrors(['bobot'])
        ->assertSee('Total bobot harus 100%. Saat ini 95%.')
        ->assertNotNotified();

    $this->assertDatabaseCount('e_rapors', 0);
});

test('rejects invalid weight values', function (string $property, mixed $value, string $message) {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set($property, $value)
        ->call('simpan')
        ->assertHasErrors([$property])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('e_rapors', 0);
})->with([
    'text' => ['bobotFormatif', 'abc', 'Bobot harus berupa bilangan bulat.'],
    'above 100' => ['bobotAsas', '101', 'Bobot harus berada antara 0 dan 100.'],
    'blank' => ['bobotSikap', '', 'Bobot sikap wajib diisi.'],
]);

test('rejects invalid manual scores without saving', function (string $field, mixed $value, string $message) {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.'.$field, $value)
        ->call('simpan')
        ->assertHasErrors(['nilai.'.$student->id.'.'.$field])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('e_rapors', 0);
    $this->assertDatabaseCount('e_rapor_nilais', 0);
})->with([
    'asts above 100' => ['asts', '101', 'Nilai ASTS harus berada antara 0 dan 100.'],
    'asas negative' => ['asas', '-1', 'Nilai ASAS harus berada antara 0 dan 100.'],
    'asts decimal' => ['asts', '75.5', 'Nilai ASTS harus berupa bilangan bulat.'],
    'asas text' => ['asas', 'abc', 'Nilai ASAS harus berupa bilangan bulat.'],
]);

test('rejects an invalid kkm', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set('kkm', '101')
        ->call('simpan')
        ->assertHasErrors(['kkm'])
        ->assertSee('Batas nilai ketuntasan harus berada antara 0 dan 100.');

    $this->assertDatabaseCount('e_rapors', 0);
});

test('rejects a stale correction from a second tab', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    raporPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.asts', 60)
        ->call('simpan')->assertHasNoErrors();
    $rapor = ERapor::query()->sole();
    $tabA = raporPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB = raporPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB->set('nilai.'.$student->id.'.asts', 50);
    $tabA->set('nilai.'.$student->id.'.asts', 90)
        ->call('simpan')->assertHasNoErrors();
    $this->assertDatabaseHas('e_rapors', ['id' => $rapor->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['nilai'])
        ->assertSee('Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.');

    $this->assertDatabaseCount('e_rapors', 1);
    $this->assertDatabaseCount('e_rapor_nilais', 1);
    $this->assertDatabaseHas('e_rapor_nilais', ['asts' => 90]);
});

test('ignores score keys outside the current student matrix', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->set('nilai.999999.asts', 80)
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('e_rapors', 1);
    $this->assertDatabaseCount('e_rapor_nilais', 0);
});

test('protects the locked class subject and rapor properties', function (string $property) {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    $component = raporPage($class->id, $subject->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'mataPelajaranId', 'eRaporId', 'revisi']);

test('escapes student identity text in the matrix', function () {
    $teacher = raporTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $this->actingAs($teacher->user);

    raporPage($class->id, $subject->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
