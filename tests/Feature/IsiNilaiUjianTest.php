<?php

use App\Filament\Pages\InputNilaiUjian;
use App\Filament\Pages\IsiNilaiUjian;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\NilaiUjian;
use App\Models\NilaiUjianNilai;
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
function nilaiUjianTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function nilaiUjianSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

function nilaiUjianPage(int $classId, int $subjectId): Testable
{
    return Livewire::test(IsiNilaiUjian::class, ['kelas' => $classId, 'mapel' => $subjectId]);
}

/** @param array<string, mixed> $attributes */
function nilaiUjianExam(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): NilaiUjian
{
    return NilaiUjian::query()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classId,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => $teacher->user->name,
        'nama_kelas' => 'Kelas VIII',
        'nama_mapel' => $subject->nama_mapel,
        'dicatat_pada' => now(),
        ...$attributes,
    ]);
}

test('is hidden from navigation and restricts access to the teachers class subject', function () {
    expect(IsiNilaiUjian::shouldRegisterNavigation())->toBeFalse();
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $url = IsiNilaiUjian::getUrl(['kelas' => $class->id, 'mapel' => $subject->id]);

    $this->get($url)->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
    Livewire::test(IsiNilaiUjian::class, ['kelas' => $class->id, 'mapel' => $subject->id])->assertForbidden();

    $this->actingAs($teacher->user);
    Livewire::test(IsiNilaiUjian::class, ['kelas' => 999999, 'mapel' => $subject->id])->assertNotFound();
    Livewire::test(IsiNilaiUjian::class, ['kelas' => $class->id, 'mapel' => 999999])->assertNotFound();
});

test('requires the class and subject pair to be taught in the running period', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $otherClass = KelasFactory::new()->create();
    $notTaughtSubject = MataPelajaranFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    nilaiUjianSchedule(nilaiUjianTeacher(), $subject, $otherClass->id);
    nilaiUjianSchedule($teacher, $subject, $otherClass->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $this->actingAs($teacher->user);

    Livewire::test(IsiNilaiUjian::class, ['kelas' => $class->id, 'mapel' => $notTaughtSubject->id])->assertNotFound();
    Livewire::test(IsiNilaiUjian::class, ['kelas' => $otherClass->id, 'mapel' => $subject->id])->assertNotFound();
});

test('renders students identity and a blank exam component', function () {
    $teacher = nilaiUjianTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Fiqih']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas IX']);
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $male = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Laki-laki', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $female = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Perempuan', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->assertOk()
        ->assertSet('kelasId', $class->id)
        ->assertSet('mataPelajaranId', $subject->id)
        ->assertSet('nilaiUjianId', null)
        ->assertSet('revisi', null)
        ->assertSet('komponens.0.nama', '')
        ->assertSet('nilai.0.'.$male->id, null)
        ->assertSet('catatan.0.'.$male->id, null)
        ->assertSee('Kelas IX')->assertSee('Fiqih')->assertSee('Guru Penilai')
        ->assertSee('Siswa Laki-laki')->assertSee('Siswa Perempuan')->assertSee('001')
        ->assertSeeInOrder(['Jumlah Siswa', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSeeInOrder(['Jumlah Ujian', '1'])
        ->assertSee('Ujian 1')
        ->assertSeeHtml('wire:submit="simpan"');
});

test('loads existing components scores and notes', function () {
    $teacher = nilaiUjianTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VIII']);
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $exam = nilaiUjianExam($teacher, $subject, $class->id);
    $first = $exam->komponens()->create(['nama' => 'UTS', 'urutan' => 1]);
    $exam->komponens()->create(['nama' => 'UAS', 'urutan' => 2]);
    NilaiUjianNilai::query()->create([
        'nilai_ujian_komponen_id' => $first->id,
        'murid_id' => $student->id,
        'nama_murid' => $student->nama,
        'nis' => $student->nis,
        'nilai' => 85,
        'catatan' => 'Hafalan lancar',
    ]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->assertSet('nilaiUjianId', $exam->id)
        ->assertSet('revisi', 1)
        ->assertSet('komponens.0.nama', 'UTS')
        ->assertSet('komponens.1.nama', 'UAS')
        ->assertSet('nilai.0.'.$student->id, 85)
        ->assertSet('nilai.1.'.$student->id, null)
        ->assertSet('catatan.0.'.$student->id, 'Hafalan lancar')
        ->assertSee('UTS')->assertSee('UAS');
});

test('saves exam components scores and notes with snapshots', function () {
    $teacher = nilaiUjianTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VIII']);
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Pertama', 'nis' => '101']);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Kedua', 'nis' => '102']);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens', [['nama' => 'UTS'], ['nama' => 'UAS']])
        ->set('nilai', [
            0 => [$first->id => 88],
            1 => [$first->id => 92, $second->id => 0],
        ])
        ->set('catatan', [
            0 => [$first->id => 'Perlu latihan soal', $second->id => 'Belum mengikuti ujian'],
        ])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Nilai ujian tersimpan')
        ->assertRedirect(InputNilaiUjian::getUrl());

    $exam = NilaiUjian::query()->sole();
    $this->assertDatabaseHas('nilai_ujians', [
        'id' => $exam->id, 'guru_id' => $teacher->id, 'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id, 'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'nama_guru' => 'Guru Penilai',
        'nama_kelas' => 'Kelas VIII', 'nama_mapel' => 'Matematika', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseCount('nilai_ujian_komponens', 2);
    $this->assertDatabaseHas('nilai_ujian_komponens', ['nilai_ujian_id' => $exam->id, 'nama' => 'UTS', 'urutan' => 1]);
    $this->assertDatabaseHas('nilai_ujian_komponens', ['nilai_ujian_id' => $exam->id, 'nama' => 'UAS', 'urutan' => 2]);
    $this->assertDatabaseCount('nilai_ujian_nilais', 4);
    $uts = $exam->komponens()->where('nama', 'UTS')->sole();
    $uas = $exam->komponens()->where('nama', 'UAS')->sole();
    $this->assertDatabaseHas('nilai_ujian_nilais', [
        'nilai_ujian_komponen_id' => $uts->id,
        'murid_id' => $first->id, 'nama_murid' => 'Siswa Pertama', 'nis' => '101',
        'nilai' => 88, 'catatan' => 'Perlu latihan soal',
    ]);
    $this->assertDatabaseHas('nilai_ujian_nilais', [
        'nilai_ujian_komponen_id' => $uas->id,
        'murid_id' => $second->id, 'nilai' => 0, 'catatan' => null,
    ]);
    $this->assertDatabaseHas('nilai_ujian_nilais', [
        'nilai_ujian_komponen_id' => $uts->id,
        'murid_id' => $second->id, 'nilai' => null, 'catatan' => 'Belum mengikuti ujian',
    ]);
});

test('supports adding and removing exam components dynamically', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->call('tambahKomponen')
        ->assertSee('Tambah ujian')
        ->assertSee('Hapus')
        ->assertSeeHtml('class="ujian-komponen-action"')
        ->assertSet('komponens.1.nama', '')
        ->assertSet('nilai.1.'.$student->id, null)
        ->set('komponens.1.nama', 'UAS')
        ->set('nilai.1.'.$student->id, 90)
        ->call('hapusKomponen', 0)
        ->assertSet('komponens.0.nama', 'UAS')
        ->assertSet('nilai.0.'.$student->id, 90)
        ->assertCount('komponens', 1);
});

test('keeps one exam component when the last one is removed', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->call('hapusKomponen', 0)
        ->assertCount('komponens', 1)
        ->assertSet('komponens.0.nama', '');
});

test('updates scores without duplicates and keeps the first recorded time', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->set('nilai.0.'.$student->id, 70)
        ->call('simpan')->assertHasNoErrors();
    $exam = NilaiUjian::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    nilaiUjianPage($class->id, $subject->id)
        ->set('nilai.0.'.$student->id, 95)
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('nilai_ujians', 1);
    $this->assertDatabaseCount('nilai_ujian_komponens', 1);
    $this->assertDatabaseCount('nilai_ujian_nilais', 1);
    $this->assertDatabaseHas('nilai_ujians', [
        'id' => $exam->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('nilai_ujian_nilais', [
        'murid_id' => $student->id, 'nilai' => 95,
    ]);
});

test('rejects a blank exam name without saving', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', '   ')
        ->call('simpan')
        ->assertHasErrors(['komponens.0.nama'])
        ->assertSee('Nama ujian wajib diisi.')
        ->assertNotNotified();

    $this->assertDatabaseCount('nilai_ujians', 0);
});

test('rejects an exam name longer than 100 characters', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', str_repeat('a', 101))
        ->call('simpan')
        ->assertHasErrors(['komponens.0.nama'])
        ->assertSee('Nama ujian maksimal 100 karakter.');

    $this->assertDatabaseCount('nilai_ujians', 0);
});

test('rejects invalid scores without saving any exam', function (mixed $value, string $message) {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->set('nilai.0.'.$student->id, $value)
        ->call('simpan')
        ->assertHasErrors(['nilai.0.'.$student->id])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('nilai_ujians', 0);
    $this->assertDatabaseCount('nilai_ujian_nilais', 0);
})->with([
    'above 100' => [101, 'Nilai harus berada antara 0 dan 100.'],
    'negative' => [-1, 'Nilai harus berada antara 0 dan 100.'],
    'decimal' => ['75.5', 'Nilai harus berupa bilangan bulat.'],
    'text' => ['abc', 'Nilai harus berupa bilangan bulat.'],
]);

test('rejects a note longer than 500 characters', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->set('catatan.0.'.$student->id, str_repeat('x', 501))
        ->call('simpan')
        ->assertHasErrors(['catatan.0.'.$student->id])
        ->assertSee('Catatan maksimal 500 karakter.');

    $this->assertDatabaseCount('nilai_ujians', 0);
});

test('rejects a stale correction from a second tab', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->set('nilai.0.'.$student->id, 70)
        ->call('simpan')->assertHasNoErrors();
    $exam = NilaiUjian::query()->sole();
    $tabA = nilaiUjianPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB = nilaiUjianPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB->set('nilai.0.'.$student->id, 60);
    $tabA->set('nilai.0.'.$student->id, 90)
        ->call('simpan')->assertHasNoErrors();
    $this->assertDatabaseHas('nilai_ujians', ['id' => $exam->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['komponens'])
        ->assertSee('Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.');

    $this->assertDatabaseCount('nilai_ujians', 1);
    $this->assertDatabaseCount('nilai_ujian_komponens', 1);
    $this->assertDatabaseCount('nilai_ujian_nilais', 1);
    $this->assertDatabaseHas('nilai_ujian_nilais', ['nilai' => 90]);
    $this->assertDatabaseMissing('nilai_ujian_nilais', ['nilai' => 60]);
});

test('ignores score keys outside the current student matrix', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens.0.nama', 'UTS')
        ->set('nilai.0.999999', 80)
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('nilai_ujians', 1);
    $this->assertDatabaseCount('nilai_ujian_nilais', 0);
});

test('protects the locked class subject and exam properties', function (string $property) {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    $component = nilaiUjianPage($class->id, $subject->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'mataPelajaranId', 'nilaiUjianId', 'revisi']);

test('escapes student identity text in the matrix', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});

test('shows a notice when the class has no students', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->assertSee('Belum ada siswa di kelas ini.')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('computes averages from filled scores only', function () {
    $teacher = nilaiUjianTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianSchedule($teacher, $subject, $class->id);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    nilaiUjianPage($class->id, $subject->id)
        ->set('komponens', [['nama' => 'UTS'], ['nama' => 'UAS']])
        ->set('nilai', [
            0 => [$first->id => 80, $second->id => 70],
            1 => [$first->id => 90],
        ])
        ->assertSee('85,00')
        ->assertSee('70,00');
});
