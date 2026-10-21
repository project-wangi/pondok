<?php

use App\Filament\Pages\InputLingkupMateri;
use App\Filament\Pages\IsiLingkupMateri;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\LingkupMateriFactory;
use Database\Factories\MataPelajaranFactory;
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

/** @param array<string, mixed> $attributes */
function inputOverviewTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function inputOverviewSchedule(Guru $teacher, MataPelajaran $subject, array $attributes = []): JadwalPelajaran
{
    return JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $subject->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        ...$attributes,
    ]);
}

/** @param list<string> $goals */
function inputOverviewMaterial(Guru $teacher, MataPelajaran $subject, Kelas $class, string $material, int $order, array $goals): LingkupMateri
{
    $lingkup = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->forClass($class)->create([
        'materi' => $material,
        'urutan' => $order,
    ]);

    foreach ($goals as $index => $goal) {
        TujuanPembelajaranFactory::new()->create([
            'lingkup_materi_id' => $lingkup->id,
            'urutan' => $index + 1,
            'teks' => $goal,
        ]);
    }

    return $lingkup;
}

test('shows the requested menu label under the Pembelajaran group', function () {
    expect(InputLingkupMateri::getNavigationGroup())->toBe('Pembelajaran')
        ->and(InputLingkupMateri::getNavigationLabel())->toBe('Lingkup Materi dan Tujuan Pembelajaran')
        ->and(InputLingkupMateri::getSlug())->toBe('lingkup-materi')
        ->and(InputLingkupMateri::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.lingkup-materi'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.lingkup-materi'))->assertForbidden();
    Livewire::test(InputLingkupMateri::class)->assertForbidden();
});

test('shows an honest empty state when the running period has no teaching schedule', function () {
    $this->actingAs(inputOverviewTeacher()->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.')
        ->assertSeeHtml('Lingkup Materi dan Tujuan Pembelajaran');
});

test('lists one row per class and subject with real counts and fill links', function () {
    $teacher = inputOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz Al-Qur\'an']);
    $emptySubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    $foreignSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $classA->id]);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $classA->id]);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $classB->id]);
    inputOverviewSchedule($teacher, $emptySubject, ['kelas_id' => $classA->id]);
    inputOverviewSchedule($teacher, $oldSubject, ['kelas_id' => $classA->id, 'semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    inputOverviewSchedule(inputOverviewTeacher(), $foreignSubject, ['kelas_id' => $classA->id]);
    inputOverviewMaterial($teacher, $subject, $classA, 'kontrak pembelajaran', 1, ['tujuan satu', 'tujuan dua']);
    inputOverviewMaterial($teacher, $subject, $classA, 'makharijul huruf', 2, ['tujuan tiga']);
    $this->actingAs($teacher->user);

    $component = Livewire::test(InputLingkupMateri::class)
        ->assertSee('Tahfidz Al-Qur\'an')
        ->assertSee('Bahasa Arab')
        ->assertSee('Kelas A')->assertSee('Kelas B')
        ->assertDontSee('Kelas A, Kelas B')
        ->assertSeeInOrder(['Kelas A', 'Bahasa Arab', '0', '0', 'Belum diisi'])
        ->assertSeeInOrder(['Kelas A', 'Tahfidz Al-Qur\'an', '2', '3', 'Sudah diisi'])
        ->assertSeeInOrder(['Kelas B', 'Tahfidz Al-Qur\'an', '0', '0', 'Belum diisi'])
        ->assertDontSee('Mapel Semester Lama')
        ->assertDontSee('Mapel Guru Lain')
        ->assertSeeHtml('href="'.e(IsiLingkupMateri::getUrl(['kelas' => $classA->id, 'mapel' => $subject->id])).'"')
        ->assertSeeHtml('href="'.e(IsiLingkupMateri::getUrl(['kelas' => $classA->id, 'mapel' => $emptySubject->id])).'"')
        ->assertDontSeeHtml(IsiLingkupMateri::getUrl(['kelas' => $classA->id, 'mapel' => $oldSubject->id]))
        ->assertDontSeeHtml('wire:submit="simpan"');

    expect(substr_count($component->html(), 'Isi lingkup materi'))->toBe(3);
});

test('shows zero counts for a class without material', function () {
    $teacher = inputOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Kosong']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSee('Mapel Kosong')
        ->assertSeeInOrder(['Kelas X', 'Mapel Kosong', '0', '0', 'Belum diisi']);
});

test('keeps the material status separate for each class of the same subject', function () {
    $teacher = inputOverviewTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $classA->id]);
    inputOverviewSchedule($teacher, $subject, ['kelas_id' => $classB->id]);
    inputOverviewMaterial($teacher, $subject, $classA, 'materi kelas a', 1, ['tujuan kelas a']);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSeeInOrder(['Kelas A', 'Tahfidz', '1', '1', 'Sudah diisi'])
        ->assertSeeInOrder(['Kelas B', 'Tahfidz', '0', '0', 'Belum diisi']);
});
