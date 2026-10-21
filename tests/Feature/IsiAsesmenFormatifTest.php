<?php

use App\Filament\Pages\InputAsesmenFormatif;
use App\Filament\Pages\IsiAsesmenFormatif;
use App\Filament\Pages\IsiLingkupMateri;
use App\Models\AsesmenFormatif;
use App\Models\AsesmenFormatifNilai;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\LingkupMateriFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\TujuanPembelajaranFactory;
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
function asesmenTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function asesmenSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

/** @param list<string> $goals */
function asesmenMaterial(Guru $teacher, MataPelajaran $subject, array $goals = ['tujuan satu', 'tujuan dua']): LingkupMateri
{
    $classId = JadwalPelajaran::query()
        ->where('guru_id', $teacher->id)
        ->where('mata_pelajaran', $subject->id)
        ->where('semester', 'Ganjil')
        ->where('tahun_ajaran', '2026/2027')
        ->value('kelas_id');

    $material = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)
        ->state(['kelas_id' => $classId])
        ->create([
            'materi' => 'kontrak pembelajaran',
            'urutan' => 1,
        ]);

    foreach ($goals as $index => $goal) {
        TujuanPembelajaranFactory::new()->create([
            'lingkup_materi_id' => $material->id,
            'urutan' => $index + 1,
            'teks' => $goal,
        ]);
    }

    return $material->load('tujuanPembelajarans');
}

function asesmenPage(int $classId, int $subjectId): Testable
{
    return Livewire::test(IsiAsesmenFormatif::class, ['kelas' => $classId, 'mapel' => $subjectId]);
}

/** @return array<string, mixed> */
function asesmenScoreData(AsesmenFormatif $assessment, Murid $student, TujuanPembelajaran $purpose, int $score): array
{
    return [
        'asesmen_formatif_id' => $assessment->id,
        'murid_id' => $student->id,
        'lingkup_materi_id' => $purpose->lingkup_materi_id,
        'tujuan_pembelajaran_id' => $purpose->id,
        'nama_murid' => $student->nama,
        'nis' => $student->nis,
        'jenis_kelamin' => $student->jenis_kelamin,
        'lingkup_materi' => 'kontrak pembelajaran',
        'tujuan_pembelajaran' => $purpose->teks,
        'nilai' => $score,
    ];
}

test('is hidden from navigation and restricts access to the teachers class subject', function () {
    expect(IsiAsesmenFormatif::shouldRegisterNavigation())->toBeFalse();
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $url = IsiAsesmenFormatif::getUrl(['kelas' => $class->id, 'mapel' => $subject->id]);

    $this->get($url)->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
    Livewire::test(IsiAsesmenFormatif::class, ['kelas' => $class->id, 'mapel' => $subject->id])->assertForbidden();

    $this->actingAs($teacher->user);
    Livewire::test(IsiAsesmenFormatif::class, ['kelas' => 999999, 'mapel' => $subject->id])->assertNotFound();
    Livewire::test(IsiAsesmenFormatif::class, ['kelas' => $class->id, 'mapel' => 999999])->assertNotFound();
});

test('requires the class and subject pair to be taught in the running period', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $otherClass = KelasFactory::new()->create();
    $notTaughtSubject = MataPelajaranFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    asesmenSchedule(asesmenTeacher(), $subject, $otherClass->id);
    asesmenSchedule($teacher, $subject, $otherClass->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $this->actingAs($teacher->user);

    Livewire::test(IsiAsesmenFormatif::class, ['kelas' => $class->id, 'mapel' => $notTaughtSubject->id])->assertNotFound();
    Livewire::test(IsiAsesmenFormatif::class, ['kelas' => $otherClass->id, 'mapel' => $subject->id])->assertNotFound();
});

test('renders students purposes existing scores and identity counts', function () {
    $teacher = asesmenTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VII']);
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject, ['siswa mengenal makhraj', 'siswa menerapkan makhraj']);
    $male = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Laki-laki', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $female = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Perempuan', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    $assessment = AsesmenFormatif::query()->create([
        'guru_id' => $teacher->id, 'kelas_id' => $class->id, 'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id, 'revisi' => 1, 'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027',
        'nama_guru' => 'Guru Penilai', 'nama_kelas' => 'Kelas VII', 'nama_mapel' => 'Tahfidz', 'dicatat_pada' => now(),
    ]);
    AsesmenFormatifNilai::query()->create(asesmenScoreData($assessment, $male, $material->tujuanPembelajarans[0], 85));
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->assertOk()
        ->assertSet('asesmenId', $assessment->id)
        ->assertSet('revisi', 1)
        ->assertSet('nilai.'.$male->id.'.'.$material->tujuanPembelajarans[0]->id, 85)
        ->assertSet('nilai.'.$male->id.'.'.$material->tujuanPembelajarans[1]->id, null)
        ->assertSee('Kelas VII')->assertSee('Tahfidz')->assertSee('Guru Penilai')
        ->assertSee('Siswa Laki-laki')->assertSee('Siswa Perempuan')->assertSee('001')
        ->assertSeeHtml('<td>L</td>')->assertSeeHtml('<td>P</td>')
        ->assertSeeInOrder(['Jumlah Siswa', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSee('TP1')->assertSee('TP2')
        ->assertSee('Jenis Kelamin')
        ->assertSee('Tercapai')
        ->assertSeeHtml('wire:submit="simpan"');
});

test('saves scores with snapshots ignores blank scores and redirects back', function () {
    $teacher = asesmenTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VIII']);
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Pertama', 'nis' => '101']);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Kedua', 'nis' => '102']);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->set('nilai', [
            $first->id => [
                $material->tujuanPembelajarans[0]->id => 85,
                $material->tujuanPembelajarans[1]->id => 0,
            ],
            $second->id => [
                $material->tujuanPembelajarans[0]->id => null,
                $material->tujuanPembelajarans[1]->id => null,
            ],
        ])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Nilai formatif tersimpan')
        ->assertRedirect(InputAsesmenFormatif::getUrl());

    $assessment = AsesmenFormatif::query()->sole();
    $this->assertDatabaseHas('asesmen_formatifs', [
        'id' => $assessment->id, 'guru_id' => $teacher->id, 'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id, 'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'nama_guru' => 'Guru Penilai',
        'nama_kelas' => 'Kelas VIII', 'nama_mapel' => 'Matematika', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseCount('asesmen_formatif_nilais', 2);
    $this->assertDatabaseHas('asesmen_formatif_nilais', [
        'asesmen_formatif_id' => $assessment->id, 'murid_id' => $first->id,
        'lingkup_materi_id' => $material->id, 'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[0]->id,
        'nama_murid' => 'Siswa Pertama', 'nis' => '101', 'lingkup_materi' => 'kontrak pembelajaran',
        'tujuan_pembelajaran' => 'tujuan satu', 'nilai' => 85,
    ]);
    $this->assertDatabaseHas('asesmen_formatif_nilais', [
        'asesmen_formatif_id' => $assessment->id, 'murid_id' => $first->id,
        'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[1]->id, 'nilai' => 0,
    ]);
    $this->assertDatabaseMissing('asesmen_formatif_nilais', ['murid_id' => $second->id]);
});

test('updates scores without duplicates and keeps the first recorded time', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    asesmenPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.'.$material->tujuanPembelajarans[0]->id, 70)
        ->call('simpan')->assertHasNoErrors();
    $assessment = AsesmenFormatif::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    asesmenPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.'.$material->tujuanPembelajarans[0]->id, 95)
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('asesmen_formatifs', 1);
    $this->assertDatabaseCount('asesmen_formatif_nilais', 1);
    $this->assertDatabaseHas('asesmen_formatifs', [
        'id' => $assessment->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('asesmen_formatif_nilais', [
        'asesmen_formatif_id' => $assessment->id, 'murid_id' => $student->id, 'nilai' => 95,
    ]);
});

test('rejects invalid scores without saving any assessment', function (mixed $value, string $message) {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $purpose = $material->tujuanPembelajarans[0];
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.'.$purpose->id, $value)
        ->call('simpan')
        ->assertHasErrors(['nilai.'.$student->id.'.'.$purpose->id])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('asesmen_formatifs', 0);
    $this->assertDatabaseCount('asesmen_formatif_nilais', 0);
})->with([
    'above 100' => [101, 'Nilai harus berada antara 0 dan 100.'],
    'negative' => [-1, 'Nilai harus berada antara 0 dan 100.'],
    'decimal' => ['75.5', 'Nilai harus berupa bilangan bulat.'],
    'text' => ['abc', 'Nilai harus berupa bilangan bulat.'],
]);

test('shows a link to fill purposes when the subject has no material', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->assertSee('Belum ada tujuan pembelajaran')
        ->assertSeeHtml('href="'.e(IsiLingkupMateri::getUrl(['kelas' => $class->id, 'mapel' => $subject->id])).'"')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('shows a notice when the class has no students', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    asesmenMaterial($teacher, $subject);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->assertSee('Belum ada siswa di kelas ini.')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('computes real material averages overall averages and ranks', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject, ['tujuan satu', 'tujuan dua']);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->set('nilai', [
            $first->id => [
                $material->tujuanPembelajarans[0]->id => 80,
                $material->tujuanPembelajarans[1]->id => 100,
            ],
            $second->id => [
                $material->tujuanPembelajarans[0]->id => 70,
                $material->tujuanPembelajarans[1]->id => 70,
            ],
        ])
        ->assertSee('90,00')->assertSee('70,00')
        ->assertSee('Tercapai')->assertSee('Belum')
        ->assertSeeInOrder(['Rata-rata', 'Keterangan', 'Peringkat'])
        ->assertSee('1')->assertSee('2');
});

test('uses 75 as the minimum passing average', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject, ['tujuan satu']);
    $passing = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Ambang']);
    $failing = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Di Bawah']);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->set('nilai', [
            $passing->id => [$material->tujuanPembelajarans[0]->id => 75],
            $failing->id => [$material->tujuanPembelajarans[0]->id => 74],
        ])
        ->assertSee('75,00')->assertSee('74,00')
        ->assertSeeInOrder(['Siswa Ambang', '75,00', 'Tercapai', 'Siswa Di Bawah', '74,00', 'Belum']);
});

test('rejects a stale correction from a second tab', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    $material = asesmenMaterial($teacher, $subject);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    asesmenPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.'.$material->tujuanPembelajarans[0]->id, 70)
        ->call('simpan')->assertHasNoErrors();
    $assessment = AsesmenFormatif::query()->sole();
    $tabA = asesmenPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB = asesmenPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB->set('nilai.'.$student->id.'.'.$material->tujuanPembelajarans[0]->id, 60);
    $tabA->set('nilai.'.$student->id.'.'.$material->tujuanPembelajarans[0]->id, 90)
        ->call('simpan')->assertHasNoErrors();
    $this->assertDatabaseHas('asesmen_formatifs', ['id' => $assessment->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['nilai'])
        ->assertSee('Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.');

    $this->assertDatabaseCount('asesmen_formatifs', 1);
    $this->assertDatabaseCount('asesmen_formatif_nilais', 1);
    $this->assertDatabaseHas('asesmen_formatif_nilais', ['nilai' => 90]);
    $this->assertDatabaseMissing('asesmen_formatif_nilais', ['nilai' => 60]);
});

test('ignores score keys outside the current student and purpose matrix', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    asesmenMaterial($teacher, $subject);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->set('nilai.'.$student->id.'.999999', 80)
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('asesmen_formatifs', 1);
    $this->assertDatabaseCount('asesmen_formatif_nilais', 0);
});

test('protects the locked class subject and assessment properties', function (string $property) {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    asesmenMaterial($teacher, $subject);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    $component = asesmenPage($class->id, $subject->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'mataPelajaranId', 'asesmenId', 'revisi']);

test('escapes student identity text in the matrix', function () {
    $teacher = asesmenTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenSchedule($teacher, $subject, $class->id);
    asesmenMaterial($teacher, $subject);
    MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $this->actingAs($teacher->user);

    asesmenPage($class->id, $subject->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
