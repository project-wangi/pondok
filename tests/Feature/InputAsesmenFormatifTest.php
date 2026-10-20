<?php

use App\Filament\Pages\InputAsesmenFormatif;
use App\Filament\Pages\IsiAsesmenFormatif;
use App\Filament\Pages\IsiLingkupMateri;
use App\Models\AsesmenFormatif;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AsesmenFormatifFactory;
use Database\Factories\AsesmenFormatifNilaiFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\LingkupMateriFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\TujuanPembelajaranFactory;
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

function asesmenOverviewTeacher(): Guru
{
    return GuruFactory::new()->create();
}

function asesmenOverviewSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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
function asesmenOverviewMaterial(Guru $teacher, MataPelajaran $subject, int $order, array $goals): void
{
    $material = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->create([
        'materi' => 'materi '.$order,
        'urutan' => $order,
    ]);

    foreach ($goals as $index => $goal) {
        TujuanPembelajaranFactory::new()->create([
            'lingkup_materi_id' => $material->id,
            'urutan' => $index + 1,
            'teks' => $goal,
        ]);
    }
}

test('shows the menu in the Pembelajaran group', function () {
    expect(InputAsesmenFormatif::getNavigationGroup())->toBe('Pembelajaran')
        ->and(InputAsesmenFormatif::getNavigationLabel())->toBe('Input Asesmen Formatif');
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.input-asesmen-formatif'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.input-asesmen-formatif'))->assertForbidden();
    Livewire::test(InputAsesmenFormatif::class)->assertForbidden();
});

test('shows an empty state when there is no teaching schedule', function () {
    $this->actingAs(asesmenOverviewTeacher()->user);

    Livewire::test(InputAsesmenFormatif::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.');
});

test('lists class and subject rows with real counts status and links', function () {
    $teacher = asesmenOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $emptySubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    asesmenOverviewSchedule($teacher, $subject, $classA->id);
    asesmenOverviewSchedule($teacher, $subject, $classA->id);
    asesmenOverviewSchedule($teacher, $subject, $classB->id);
    asesmenOverviewSchedule($teacher, $emptySubject, $classA->id);
    asesmenOverviewSchedule($teacher, $oldSubject, $classA->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    asesmenOverviewSchedule(asesmenOverviewTeacher(), MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']), $classA->id);
    MuridFactory::new()->count(2)->create(['kelas_id' => $classA->id]);
    MuridFactory::new()->create(['kelas_id' => $classB->id]);
    asesmenOverviewMaterial($teacher, $subject, 1, ['tujuan satu', 'tujuan dua']);
    asesmenOverviewMaterial($teacher, $subject, 2, ['tujuan tiga']);
    $assessment = AsesmenFormatifFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classA->id,
        'mata_pelajaran_id' => $subject->id,
    ]);
    AsesmenFormatifNilaiFactory::new()->count(2)->create(['asesmen_formatif_id' => $assessment->id]);
    $this->actingAs($teacher->user);

    Livewire::test(InputAsesmenFormatif::class)
        ->assertSee('Kelas A')->assertSee('Kelas B')->assertSee('Matematika')->assertSee('Bahasa Arab')
        ->assertDontSee('Mapel Semester Lama')->assertDontSee('Mapel Guru Lain')
        ->assertSee('Sebagian')->assertSee('Belum dinilai')
        ->assertSeeHtml('href="'.e(IsiAsesmenFormatif::getUrl(['kelas' => $classA->id, 'mapel' => $subject->id])).'"')
        ->assertSeeHtml('href="'.e(IsiLingkupMateri::getUrl(['mapel' => $emptySubject->id])).'"')
        ->assertDontSeeHtml(IsiAsesmenFormatif::getUrl(['kelas' => $classA->id, 'mapel' => $emptySubject->id]))
        ->assertDontSeeHtml('wire:submit="simpan"');

    expect(AsesmenFormatif::query()->count())->toBe(1);
});

test('shows the score completion status from stored assessments', function () {
    $teacher = asesmenOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    asesmenOverviewSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    asesmenOverviewMaterial($teacher, $subject, 1, ['tujuan satu']);
    $assessment = AsesmenFormatifFactory::new()->create([
        'guru_id' => $teacher->id, 'kelas_id' => $class->id, 'mata_pelajaran_id' => $subject->id,
    ]);
    AsesmenFormatifNilaiFactory::new()->create(['asesmen_formatif_id' => $assessment->id]);
    $this->actingAs($teacher->user);

    Livewire::test(InputAsesmenFormatif::class)->assertSee('Lengkap');
});
