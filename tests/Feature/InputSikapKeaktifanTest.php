<?php

use App\Filament\Pages\InputSikapKeaktifan;
use App\Filament\Pages\IsiSikapKeaktifan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\SikapKeaktifan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\SikapKeaktifanFactory;
use Database\Factories\SikapKeaktifanNilaiFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

function sikapOverviewTeacher(): Guru
{
    return GuruFactory::new()->create();
}

function sikapOverviewSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

test('shows the menu in the Pembelajaran group', function () {
    expect(InputSikapKeaktifan::getNavigationGroup())->toBe('Pembelajaran')
        ->and(InputSikapKeaktifan::getNavigationLabel())->toBe('Sikap dan Keaktifan')
        ->and(InputSikapKeaktifan::getSlug())->toBe('sikap-keaktifan')
        ->and(InputSikapKeaktifan::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.sikap-keaktifan'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.sikap-keaktifan'))->assertForbidden();
    Livewire::test(InputSikapKeaktifan::class)->assertForbidden();
});

test('shows an empty state when there is no teaching schedule', function () {
    $this->actingAs(sikapOverviewTeacher()->user);

    Livewire::test(InputSikapKeaktifan::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.');
});

test('lists class and subject rows with real counts status and links', function () {
    $teacher = sikapOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $emptySubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    sikapOverviewSchedule($teacher, $subject, $classA->id);
    sikapOverviewSchedule($teacher, $subject, $classA->id);
    sikapOverviewSchedule($teacher, $subject, $classB->id);
    sikapOverviewSchedule($teacher, $emptySubject, $classA->id);
    sikapOverviewSchedule($teacher, $oldSubject, $classA->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    sikapOverviewSchedule(sikapOverviewTeacher(), MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']), $classA->id);
    MuridFactory::new()->count(2)->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classB->id]);
    $entry = SikapKeaktifanFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classA->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    SikapKeaktifanNilaiFactory::new()->create(['sikap_keaktifan_id' => $entry->id]);
    $this->actingAs($teacher->user);

    Livewire::test(InputSikapKeaktifan::class)
        ->assertSee('Kelas A')->assertSee('Kelas B')->assertSee('Matematika')->assertSee('Bahasa Arab')
        ->assertDontSee('Mapel Semester Lama')->assertDontSee('Mapel Guru Lain')
        ->assertSee('Sebagian')->assertSee('1/2 siswa')
        ->assertSeeHtml('href="'.e(IsiSikapKeaktifan::getUrl(['kelas' => $classA->id, 'mapel' => $subject->id])).'"')
        ->assertDontSeeHtml('wire:submit="simpan"');

    expect(SikapKeaktifan::query()->count())->toBe(1);
});

test('shows the completion status from stored entries', function () {
    $teacher = sikapOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    sikapOverviewSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $entry = SikapKeaktifanFactory::new()->create([
        'guru_id' => $teacher->id, 'kelas_id' => $class->id, 'mata_pelajaran_id' => $subject->id,
    ]);
    SikapKeaktifanNilaiFactory::new()->create(['sikap_keaktifan_id' => $entry->id]);
    $this->actingAs($teacher->user);

    Livewire::test(InputSikapKeaktifan::class)->assertSee('Lengkap');
});
