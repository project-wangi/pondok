<?php

use App\Filament\Pages\InputNilaiUjian;
use App\Filament\Pages\IsiNilaiUjian;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\NilaiUjianFactory;
use Database\Factories\NilaiUjianKomponenFactory;
use Database\Factories\NilaiUjianNilaiFactory;
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

function nilaiUjianOverviewTeacher(): Guru
{
    return GuruFactory::new()->create();
}

/** @param array<string, mixed> $attributes */
function nilaiUjianOverviewSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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
    expect(InputNilaiUjian::getNavigationGroup())->toBe('Rapor')
        ->and(InputNilaiUjian::getNavigationLabel())->toBe('Nilai Ujian')
        ->and(InputNilaiUjian::getSlug())->toBe('nilai-ujian');
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.nilai-ujian'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.nilai-ujian'))->assertForbidden();
    Livewire::test(InputNilaiUjian::class)->assertForbidden();
});

test('shows an empty state when there is no teaching schedule', function () {
    $this->actingAs(nilaiUjianOverviewTeacher()->user);

    Livewire::test(InputNilaiUjian::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.');
});

test('lists class and subject rows with real counts status and links', function () {
    $teacher = nilaiUjianOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $emptySubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    nilaiUjianOverviewSchedule($teacher, $subject, $classA->id);
    nilaiUjianOverviewSchedule($teacher, $subject, $classA->id);
    nilaiUjianOverviewSchedule($teacher, $subject, $classB->id);
    nilaiUjianOverviewSchedule($teacher, $emptySubject, $classA->id);
    nilaiUjianOverviewSchedule($teacher, $oldSubject, $classA->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    nilaiUjianOverviewSchedule(nilaiUjianOverviewTeacher(), MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']), $classA->id);
    $first = MuridFactory::new()->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classB->id]);
    $exam = NilaiUjianFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classA->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    $component = NilaiUjianKomponenFactory::new()->create([
        'nilai_ujian_id' => $exam->id,
        'urutan' => 1,
        'nama' => 'UTS',
    ]);
    NilaiUjianNilaiFactory::new()->create([
        'nilai_ujian_komponen_id' => $component->id,
        'murid_id' => $first->id,
        'nilai' => 80,
    ]);
    $this->actingAs($teacher->user);

    $page = Livewire::test(InputNilaiUjian::class)
        ->assertSee('Kelas A')->assertSee('Kelas B')->assertSee('Matematika')->assertSee('Bahasa Arab')
        ->assertDontSee('Mapel Semester Lama')->assertDontSee('Mapel Guru Lain')
        ->assertSee('Sebagian')->assertSee('Belum ada ujian')
        ->assertSeeHtml('href="'.e(IsiNilaiUjian::getUrl(['kelas' => $classA->id, 'mapel' => $subject->id])).'"')
        ->assertSeeHtml('href="'.e(IsiNilaiUjian::getUrl(['kelas' => $classB->id, 'mapel' => $subject->id])).'"')
        ->assertDontSeeHtml('wire:submit="simpan"');

    $rows = $page->viewData('rows');

    expect($rows)->toHaveCount(3);

    $filled = $rows->first(fn (array $row): bool => $row['kelas']->id === $classA->id && $row['subject']->id === $subject->id);
    $withoutExam = $rows->first(fn (array $row): bool => $row['kelas']->id === $classB->id && $row['subject']->id === $subject->id);

    expect($filled['student_count'])->toBe(2)
        ->and($filled['component_count'])->toBe(1)
        ->and($filled['filled_count'])->toBe(1)
        ->and($filled['possible_count'])->toBe(2)
        ->and($withoutExam['component_count'])->toBe(0)
        ->and($withoutExam['filled_count'])->toBe(0);
});

test('shows the score completion status from stored exams', function () {
    $teacher = nilaiUjianOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    nilaiUjianOverviewSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $exam = NilaiUjianFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    $component = NilaiUjianKomponenFactory::new()->create([
        'nilai_ujian_id' => $exam->id,
        'urutan' => 1,
        'nama' => 'UAS',
    ]);
    NilaiUjianNilaiFactory::new()->create([
        'nilai_ujian_komponen_id' => $component->id,
        'murid_id' => $student->id,
        'nilai' => 90,
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(InputNilaiUjian::class)
        ->assertSee('Lengkap')
        ->assertSee('1/1');
});
