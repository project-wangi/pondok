<?php

use App\Filament\Pages\InputERapor;
use App\Filament\Pages\IsiERapor;
use App\Models\ERapor;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ERaporFactory;
use Database\Factories\ERaporNilaiFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\NilaiUjianFactory;
use Database\Factories\NilaiUjianKomponenFactory;
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

function raporOverviewTeacher(): Guru
{
    return GuruFactory::new()->create();
}

/** @param array<string, mixed> $attributes */
function raporOverviewSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

test('shows the menu in the Rapor group', function () {
    expect(InputERapor::getNavigationGroup())->toBe('Rapor')
        ->and(InputERapor::getNavigationLabel())->toBe('e-Rapor')
        ->and(InputERapor::getSlug())->toBe('e-rapor');
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.e-rapor'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.e-rapor'))->assertForbidden();
    Livewire::test(InputERapor::class)->assertForbidden();
});

test('shows an empty state when there is no teaching schedule', function () {
    $this->actingAs(raporOverviewTeacher()->user);

    Livewire::test(InputERapor::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.');
});

test('lists class and subject rows with real counts status and links', function () {
    $teacher = raporOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $emptySubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    raporOverviewSchedule($teacher, $subject, $classA->id);
    raporOverviewSchedule($teacher, $subject, $classA->id);
    raporOverviewSchedule($teacher, $subject, $classB->id);
    raporOverviewSchedule($teacher, $emptySubject, $classA->id);
    raporOverviewSchedule($teacher, $oldSubject, $classA->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    raporOverviewSchedule(raporOverviewTeacher(), MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']), $classA->id);
    $student = MuridFactory::new()->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classB->id]);
    $exam = NilaiUjianFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classA->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    NilaiUjianKomponenFactory::new()->create(['nilai_ujian_id' => $exam->id, 'urutan' => 1, 'nama' => 'membaca']);
    NilaiUjianKomponenFactory::new()->create(['nilai_ujian_id' => $exam->id, 'urutan' => 2, 'nama' => 'menulis']);
    $rapor = ERaporFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classA->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    ERaporNilaiFactory::new()->create([
        'e_rapor_id' => $rapor->id,
        'murid_id' => $student->id,
        'asts' => 70,
        'asas' => 80,
    ]);
    $this->actingAs($teacher->user);

    $page = Livewire::test(InputERapor::class)
        ->assertSee('Kelas A')->assertSee('Kelas B')->assertSee('Tahfidz')->assertSee('Bahasa Arab')
        ->assertDontSee('Mapel Semester Lama')->assertDontSee('Mapel Guru Lain')
        ->assertSee('Lengkap')->assertSee('Belum diisi')
        ->assertSeeHtml('href="'.e(IsiERapor::getUrl(['kelas' => $classA->id, 'mapel' => $subject->id])).'"')
        ->assertDontSeeHtml('wire:submit="simpan"');

    $rows = $page->viewData('rows');

    expect($rows)->toHaveCount(3);

    $completed = $rows->first(fn (array $row): bool => $row['kelas']->id === $classA->id && $row['subject']->id === $subject->id);
    $without = $rows->first(fn (array $row): bool => $row['kelas']->id === $classB->id && $row['subject']->id === $subject->id);

    expect($completed['student_count'])->toBe(2)
        ->and($completed['component_count'])->toBe(2)
        ->and($completed['filled_count'])->toBe(2)
        ->and($completed['possible_count'])->toBe(4)
        ->and($without['component_count'])->toBe(0)
        ->and($without['filled_count'])->toBe(0);
});

test('marks a row complete when every student has both manual scores', function () {
    $teacher = raporOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    raporOverviewSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $rapor = ERaporFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    ERaporNilaiFactory::new()->create([
        'e_rapor_id' => $rapor->id,
        'murid_id' => $student->id,
        'asts' => 70,
        'asas' => 80,
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(InputERapor::class)
        ->assertSee('Lengkap')
        ->assertSee('2/2');

    expect(ERapor::query()->count())->toBe(1);
});
