<?php

use App\Filament\Pages\AbsensiPiket;
use App\Filament\Pages\RekapAbsensiPiket;
use App\Models\AbsenPiket;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Murid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
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

function piketRecapPage(): Testable
{
    return Livewire::test(RekapAbsensiPiket::class);
}

function piketRecapTeacher(): Guru
{
    return GuruFactory::new()->create();
}

/** @param array<int, array{masuk: string|null, pulang: string|null}> $records */
function piketRecapSession(Kelas $class, string $date, array $records): AbsenPiket
{
    $session = AbsenPiket::query()->create([
        'kelas_id' => $class->id,
        'guru_id' => null,
        'pencatat_id' => null,
        'revisi' => 1,
        'tanggal' => $date,
        'nama_guru' => 'Guru Piket',
        'nama_kelas' => $class->nama_kelas,
        'dicatat_pada' => now(),
    ]);

    foreach ($records as $studentId => $status) {
        $student = Murid::query()->find($studentId);

        $session->murids()->create([
            'murid_id' => $studentId,
            'nama_murid' => $student?->nama ?? 'Siswa',
            'nis' => $student?->nis,
            'masuk' => $status['masuk'],
            'pulang' => $status['pulang'],
        ]);
    }

    return $session;
}

test('shows the menu in the Laporan Kehadiran group', function () {
    expect(RekapAbsensiPiket::getNavigationGroup())->toBe('Laporan Kehadiran')
        ->and(RekapAbsensiPiket::getNavigationLabel())->toBe('Rekap Absensi Piket')
        ->and(RekapAbsensiPiket::getSlug())->toBe('rekap-absensi-piket')
        ->and(RekapAbsensiPiket::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.rekap-absensi-piket'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.rekap-absensi-piket'))->assertForbidden();
    Livewire::test(RekapAbsensiPiket::class)->assertForbidden();
});

test('shows an empty state when no class exists', function () {
    $this->actingAs(piketRecapTeacher()->user);

    piketRecapPage()->assertOk()->assertSee('Belum ada data kelas.');
});

test('selects the first class and renders masuk and pulang codes with counts', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $male = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Ahmad', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $female = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Fatimah', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    piketRecapSession($class, '2026-10-05', [
        $male->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang'],
        $female->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang'],
    ]);
    piketRecapSession($class, '2026-10-12', [
        $male->id => ['masuk' => 'Terlambat', 'pulang' => 'Pulang'],
        $female->id => ['masuk' => 'Alpa', 'pulang' => 'Alpa'],
    ]);
    $this->actingAs(piketRecapTeacher()->user);

    $page = piketRecapPage()
        ->assertOk()
        ->assertSet('kelasId', $class->id)
        ->assertSee('Klik Untuk Pilih Kelas')->assertSee('Kelas X')
        ->assertSee('Jumlah Piket:')->assertSeeInOrder(['Jumlah Piket:', '2'])
        ->assertSeeInOrder(['Laki-laki:', '1', 'Perempuan:', '1', 'Jml:', '2'])
        ->assertSee('Tanggal Piket')->assertSee('Absensi Masuk')->assertSee('Pulang')->assertSee('% Kehadiran')
        ->assertSee('Senin, 5 Oktober 2026')->assertSee('Senin, 12 Oktober 2026')
        ->assertSee('Ahmad')->assertSee('Fatimah')
        ->assertSee('Kode masuk: H = Hadir, T = Terlambat')
        ->assertSeeHtml('piket-h')->assertSeeHtml('piket-t')->assertSeeHtml('piket-p')->assertSeeHtml('piket-a');

    $rows = collect($page->viewData('rows'));
    $maleRow = $rows->firstWhere('student.id', $male->id);
    $femaleRow = $rows->firstWhere('student.id', $female->id);

    expect($maleRow['masuk_counts'])->toBe(['H' => 1, 'T' => 1, 'I' => 0, 'S' => 0, 'A' => 0])
        ->and($maleRow['pulang_count'])->toBe(2)
        ->and($maleRow['percent'])->toBe(100)
        ->and($femaleRow['masuk_counts'])->toBe(['H' => 1, 'T' => 0, 'I' => 0, 'S' => 0, 'A' => 1])
        ->and($femaleRow['pulang_count'])->toBe(1)
        ->and($femaleRow['percent'])->toBe(50)
        ->and($page->viewData('columns'))->toHaveCount(2);

    expect($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026')
        ->and($page->viewData('columns')[1]['label'])->toBe('Senin, 12 Oktober 2026');
});

test('percentages use only recorded masuk statuses', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    piketRecapSession($class, '2026-10-05', [
        $student->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang'],
    ]);
    piketRecapSession($class, '2026-10-12', [
        $student->id => ['masuk' => 'Izin', 'pulang' => 'Izin'],
    ]);
    piketRecapSession($class, '2026-10-19', []);
    $this->actingAs(piketRecapTeacher()->user);

    $page = piketRecapPage();
    $row = collect($page->viewData('rows'))->firstWhere('student.id', $student->id);

    expect($row['masuk_counts'])->toBe(['H' => 1, 'T' => 0, 'I' => 1, 'S' => 0, 'A' => 0])
        ->and($row['percent'])->toBe(50)
        ->and($page->viewData('columns'))->toHaveCount(3);
});

test('only shows piket records from the running semester', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    piketRecapSession($class, '2026-10-05', [
        $student->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang'],
    ]);
    piketRecapSession($class, '2026-02-10', [
        $student->id => ['masuk' => 'Alpa', 'pulang' => 'Alpa'],
    ]);
    $this->actingAs(piketRecapTeacher()->user);

    $page = piketRecapPage();

    expect($page->viewData('columns'))->toHaveCount(1);
    expect($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026');
});

test('switches the selected class and rejects unknown classes', function () {
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    $studentA = MuridFactory::new()->create(['kelas_id' => $classA->id, 'nama' => 'Siswa A']);
    $studentB = MuridFactory::new()->create(['kelas_id' => $classB->id, 'nama' => 'Siswa B']);
    piketRecapSession($classA, '2026-10-05', [$studentA->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang']]);
    piketRecapSession($classB, '2026-10-12', [$studentB->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang']]);
    $this->actingAs(piketRecapTeacher()->user);

    piketRecapPage()
        ->assertSet('kelasId', $classA->id)
        ->assertSee('Siswa A')->assertDontSee('Siswa B')
        ->call('pilihKelas', $classB->id)
        ->assertSet('kelasId', $classB->id)
        ->assertSee('Siswa B')
        ->assertSee('Senin, 12 Oktober 2026');

    piketRecapPage()->call('pilihKelas', 999999)->assertNotFound();
});

test('shows a notice and link when no piket record exists for the class', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs(piketRecapTeacher()->user);

    piketRecapPage()
        ->assertSee('Belum ada absensi piket tercatat untuk Kelas X')
        ->assertSeeHtml('href="'.e(AbsensiPiket::getUrl()).'"')
        ->assertDontSeeHtml('piket-matrix');
});

test('shows a notice when the class has no students', function () {
    KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $this->actingAs(piketRecapTeacher()->user);

    piketRecapPage()->assertSee('Belum ada siswa di kelas ini.');
});

test('locks the selected class property', function () {
    $class = KelasFactory::new()->create();
    $this->actingAs(piketRecapTeacher()->user);
    $component = piketRecapPage();

    expect(fn () => $component->set('kelasId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('escapes student identity text in the piket recap', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $student = MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    piketRecapSession($class, '2026-10-05', [$student->id => ['masuk' => 'Hadir', 'pulang' => 'Pulang']]);
    $this->actingAs(piketRecapTeacher()->user);

    piketRecapPage()
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
