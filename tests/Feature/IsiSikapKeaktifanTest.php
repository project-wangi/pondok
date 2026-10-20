<?php

use App\Filament\Pages\InputSikapKeaktifan;
use App\Filament\Pages\IsiSikapKeaktifan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SikapKeaktifan;
use App\Models\SikapKeaktifanNilai;
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
function sikapEntryTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function sikapEntrySchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

function sikapEntryPage(int $classId, int $subjectId): Testable
{
    return Livewire::test(IsiSikapKeaktifan::class, ['kelas' => $classId, 'mapel' => $subjectId]);
}

/** @return array<string, mixed> */
function sikapNilaiData(SikapKeaktifan $entry, Murid $student, ?string $sikap, ?string $keaktifan): array
{
    return [
        'sikap_keaktifan_id' => $entry->id,
        'murid_id' => $student->id,
        'nama_murid' => $student->nama,
        'nis' => $student->nis,
        'jenis_kelamin' => $student->jenis_kelamin,
        'sikap' => $sikap,
        'keaktifan' => $keaktifan,
    ];
}

test('is hidden from navigation and restricts access to the teachers class subject', function () {
    expect(IsiSikapKeaktifan::shouldRegisterNavigation())->toBeFalse();
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $url = IsiSikapKeaktifan::getUrl(['kelas' => $class->id, 'mapel' => $subject->id]);

    $this->get($url)->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
    Livewire::test(IsiSikapKeaktifan::class, ['kelas' => $class->id, 'mapel' => $subject->id])->assertForbidden();

    $this->actingAs($teacher->user);
    Livewire::test(IsiSikapKeaktifan::class, ['kelas' => 999999, 'mapel' => $subject->id])->assertNotFound();
    Livewire::test(IsiSikapKeaktifan::class, ['kelas' => $class->id, 'mapel' => 999999])->assertNotFound();
});

test('requires the class and subject pair to be taught in the running period', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $otherClass = KelasFactory::new()->create();
    $notTaughtSubject = MataPelajaranFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    sikapEntrySchedule(sikapEntryTeacher(), $subject, $otherClass->id);
    sikapEntrySchedule($teacher, $subject, $otherClass->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $this->actingAs($teacher->user);

    Livewire::test(IsiSikapKeaktifan::class, ['kelas' => $class->id, 'mapel' => $notTaughtSubject->id])->assertNotFound();
    Livewire::test(IsiSikapKeaktifan::class, ['kelas' => $otherClass->id, 'mapel' => $subject->id])->assertNotFound();
});

test('renders students existing categories conversions and identity counts', function () {
    $teacher = sikapEntryTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VII']);
    sikapEntrySchedule($teacher, $subject, $class->id);
    $male = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Laki-laki', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $female = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Perempuan', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    $entry = SikapKeaktifan::query()->create([
        'guru_id' => $teacher->id, 'kelas_id' => $class->id, 'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id, 'revisi' => 1, 'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027',
        'nama_guru' => 'Guru Penilai', 'nama_kelas' => 'Kelas VII', 'nama_mapel' => 'Tahfidz', 'dicatat_pada' => now(),
    ]);
    SikapKeaktifanNilai::query()->create(sikapNilaiData($entry, $male, 'A', 'B'));
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->assertOk()
        ->assertSet('sikapId', $entry->id)
        ->assertSet('revisi', 1)
        ->assertSet('sikap.'.$male->id, 'A')
        ->assertSet('keaktifan.'.$male->id, 'B')
        ->assertSet('sikap.'.$female->id, null)
        ->assertSet('keaktifan.'.$female->id, null)
        ->assertSee('Kelas VII')->assertSee('Tahfidz')->assertSee('Guru Penilai')
        ->assertSee('Siswa Laki-laki')->assertSee('Siswa Perempuan')->assertSee('001')
        ->assertSeeHtml('<td class="sikap-gender">L</td>')->assertSeeHtml('<td class="sikap-gender">P</td>')
        ->assertSeeInOrder(['Jumlah Siswa', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSee('Sikap')->assertSee('Keaktifan')
        ->assertSee('Nilai (Huruf)')->assertSee('Konversi (Angka)')
        ->assertSeeHtml('<td class="sikap-angka">100</td>')
        ->assertSeeHtml('<td class="sikap-angka">90</td>')
        ->assertSeeHtml('wire:submit="simpan"');
});

test('saves categories with snapshots ignores blank rows and redirects back', function () {
    $teacher = sikapEntryTeacher(['user_id' => User::factory()->create(['name' => 'Guru Penilai'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VIII']);
    sikapEntrySchedule($teacher, $subject, $class->id);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Pertama', 'nis' => '101']);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Kedua', 'nis' => '102']);
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->set('sikap', [
            $first->id => 'a',
            $second->id => '',
        ])
        ->set('keaktifan', [
            $first->id => 'C',
            $second->id => null,
        ])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Nilai sikap dan keaktifan tersimpan')
        ->assertRedirect(InputSikapKeaktifan::getUrl());

    $entry = SikapKeaktifan::query()->sole();
    $this->assertDatabaseHas('sikap_keaktifans', [
        'id' => $entry->id, 'guru_id' => $teacher->id, 'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id, 'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'nama_guru' => 'Guru Penilai',
        'nama_kelas' => 'Kelas VIII', 'nama_mapel' => 'Matematika', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseCount('sikap_keaktifan_nilais', 1);
    $this->assertDatabaseHas('sikap_keaktifan_nilais', [
        'sikap_keaktifan_id' => $entry->id, 'murid_id' => $first->id,
        'nama_murid' => 'Siswa Pertama', 'nis' => '101', 'sikap' => 'A', 'keaktifan' => 'C',
    ]);
    $this->assertDatabaseMissing('sikap_keaktifan_nilais', ['murid_id' => $second->id]);
});

test('updates categories without duplicates and keeps the first recorded time', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    sikapEntryPage($class->id, $subject->id)
        ->set('sikap.'.$student->id, 'A')
        ->set('keaktifan.'.$student->id, 'B')
        ->call('simpan')->assertHasNoErrors();
    $entry = SikapKeaktifan::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    sikapEntryPage($class->id, $subject->id)
        ->set('sikap.'.$student->id, 'D')
        ->set('keaktifan.'.$student->id, 'E')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('sikap_keaktifans', 1);
    $this->assertDatabaseCount('sikap_keaktifan_nilais', 1);
    $this->assertDatabaseHas('sikap_keaktifans', [
        'id' => $entry->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('sikap_keaktifan_nilais', [
        'sikap_keaktifan_id' => $entry->id, 'murid_id' => $student->id, 'sikap' => 'D', 'keaktifan' => 'E',
    ]);
});

test('converts every category letter to its reference score', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $students = collect(['A' => 100, 'B' => 90, 'C' => 80, 'D' => 60, 'E' => 40])
        ->map(fn (int $score, string $letter): Murid => MuridFactory::new()->create([
            'kelas_id' => $class->id,
            'nama' => 'Siswa '.$letter,
        ]));
    $this->actingAs($teacher->user);

    $page = sikapEntryPage($class->id, $subject->id);
    $values = $students->mapWithKeys(fn (Murid $student, string $letter): array => [$student->id => $letter])->all();
    $page->set('sikap', $values)->set('keaktifan', $values);

    foreach ([100, 90, 80, 60, 40] as $score) {
        $page->assertSeeHtml('<td class="sikap-angka">'.$score.'</td>');
    }
});

test('rejects categories outside the allowed letters', function (string $value) {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->set('sikap.'.$student->id, $value)
        ->call('simpan')
        ->assertHasErrors(['sikap.'.$student->id])
        ->assertSee('Kategori sikap harus salah satu dari A, B, C, D, atau E.')
        ->assertNotNotified();

    $this->assertDatabaseCount('sikap_keaktifans', 0);
    $this->assertDatabaseCount('sikap_keaktifan_nilais', 0);
})->with([
    'outside the range' => ['F'],
    'two letters' => ['AB'],
]);

test('shows a notice when the class has no students', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->assertSee('Belum ada siswa di kelas ini.')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('rejects a stale correction from a second tab', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    sikapEntryPage($class->id, $subject->id)
        ->set('sikap.'.$student->id, 'A')
        ->call('simpan')->assertHasNoErrors();
    $entry = SikapKeaktifan::query()->sole();
    $tabA = sikapEntryPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB = sikapEntryPage($class->id, $subject->id)->assertSet('revisi', 1);
    $tabB->set('sikap.'.$student->id, 'B');
    $tabA->set('sikap.'.$student->id, 'D')
        ->call('simpan')->assertHasNoErrors();
    $this->assertDatabaseHas('sikap_keaktifans', ['id' => $entry->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['sikap'])
        ->assertSee('Nilai telah diperbarui dari halaman lain. Muat ulang halaman sebelum menyimpan.');

    $this->assertDatabaseCount('sikap_keaktifans', 1);
    $this->assertDatabaseCount('sikap_keaktifan_nilais', 1);
    $this->assertDatabaseHas('sikap_keaktifan_nilais', ['sikap' => 'D']);
    $this->assertDatabaseMissing('sikap_keaktifan_nilais', ['sikap' => 'B']);
});

test('ignores category keys outside the current student list', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->set('sikap.999999', 'A')
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('sikap_keaktifans', 1);
    $this->assertDatabaseCount('sikap_keaktifan_nilais', 0);
});

test('protects the locked class subject and entry properties', function (string $property) {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    $component = sikapEntryPage($class->id, $subject->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'mataPelajaranId', 'sikapId', 'revisi']);

test('escapes student identity text in the table', function () {
    $teacher = sikapEntryTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapEntrySchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $this->actingAs($teacher->user);

    sikapEntryPage($class->id, $subject->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
