<?php

use App\Filament\Pages\AbsensiPeminatan;
use App\Filament\Pages\RekapAbsensiPeminatan;
use App\Models\AbsenPeminatan;
use App\Models\Guru;
use App\Models\JenisPeminatan;
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

function peminatanRecapTeacher(): Guru
{
    return GuruFactory::new()->create();
}

function peminatanRecapPage(): Testable
{
    return Livewire::test(RekapAbsensiPeminatan::class);
}

function peminatanRecapType(string $name): JenisPeminatan
{
    return JenisPeminatan::query()->create([
        'nama_peminatan' => $name,
        'nominal' => 100000,
    ]);
}

/** @param array<int, string> $statuses */
function peminatanRecapSession(JenisPeminatan $peminatan, string $date, array $statuses): AbsenPeminatan
{
    $session = AbsenPeminatan::query()->create([
        'jenis_peminatan_id' => $peminatan->id,
        'guru_id' => null,
        'pencatat_id' => null,
        'revisi' => 1,
        'tanggal' => $date,
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '09:30:00',
        'nama_guru' => 'Guru Peminatan',
        'nama_peminatan' => $peminatan->nama_peminatan,
        'dicatat_pada' => now(),
    ]);

    foreach ($statuses as $studentId => $status) {
        $student = Murid::query()->find($studentId);

        $session->murids()->create([
            'murid_id' => $studentId,
            'nama_murid' => $student?->nama ?? 'Siswa',
            'nis' => $student?->nis,
            'nama_kelas' => $student?->kelas?->nama_kelas,
            'status' => $status,
        ]);
    }

    return $session;
}

function peminatanRecapStudent(string $name, JenisPeminatan $peminatan, int $classId, ?string $nis = null, ?string $gender = 'laki-laki'): Murid
{
    $student = MuridFactory::new()->create([
        'nama' => $name,
        'nis' => $nis,
        'jenis_kelamin' => $gender,
        'kelas_id' => $classId,
    ]);

    $student->jenisPeminatans()->attach($peminatan->id);

    return $student;
}

test('shows the menu in the Absensi group', function () {
    expect(RekapAbsensiPeminatan::getNavigationGroup())->toBe('Absensi')
        ->and(RekapAbsensiPeminatan::getNavigationLabel())->toBe('Rekap Absensi Peminatan')
        ->and(RekapAbsensiPeminatan::getSlug())->toBe('rekap-absensi-peminatan')
        ->and(RekapAbsensiPeminatan::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.rekap-absensi-peminatan'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.rekap-absensi-peminatan'))->assertForbidden();
    Livewire::test(RekapAbsensiPeminatan::class)->assertForbidden();
});

test('shows an empty state when no peminatan type exists', function () {
    $this->actingAs(peminatanRecapTeacher()->user);

    peminatanRecapPage()->assertOk()->assertSee('Belum ada jenis peminatan.');
});

test('selects the first peminatan and renders the attendance grid', function () {
    $peminatanA = peminatanRecapType('Peminatan A');
    peminatanRecapType('Peminatan B');
    $classX = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $classXI = KelasFactory::new()->create(['nama_kelas' => 'Kelas XI']);
    $male = peminatanRecapStudent('Ahmad', $peminatanA, $classX->id, '001', 'laki-laki');
    $female = peminatanRecapStudent('Fatimah', $peminatanA, $classXI->id, '002', 'perempuan');
    peminatanRecapSession($peminatanA, '2026-10-05', [
        $male->id => 'Hadir',
        $female->id => 'Hadir',
    ]);
    peminatanRecapSession($peminatanA, '2026-10-12', [
        $male->id => 'Hadir',
        $female->id => 'Alpa',
    ]);
    $this->actingAs(peminatanRecapTeacher()->user);

    $page = peminatanRecapPage()
        ->assertOk()
        ->assertSet('jenisPeminatanId', $peminatanA->id)
        ->assertSee('Klik Untuk Pilih Peminatan')
        ->assertSee('Peminatan A')->assertSee('Peminatan B')
        ->assertSee('Jumlah Pertemuan:')->assertSeeInOrder(['Jumlah Pertemuan:', '2'])
        ->assertSeeInOrder(['Laki-laki:', '1', 'Perempuan:', '1', 'Jml:', '2'])
        ->assertSee('Tanggal Peminatan')->assertSee('Absensi')->assertSee('% Kehadiran')
        ->assertSee('Senin, 5 Oktober 2026')->assertSee('Senin, 12 Oktober 2026')
        ->assertSee('Ahmad')->assertSee('Fatimah')
        ->assertSee('Kelas X')->assertSee('Kelas XI')
        ->assertSeeHtml('rekap-status-h')->assertSeeHtml('rekap-status-a');

    $rows = collect($page->viewData('rows'));
    $maleRow = $rows->firstWhere('student.id', $male->id);
    $femaleRow = $rows->firstWhere('student.id', $female->id);

    expect($maleRow['counts'])->toBe(['H' => 2, 'I' => 0, 'S' => 0, 'A' => 0])
        ->and($maleRow['percent'])->toBe(100)
        ->and($femaleRow['counts'])->toBe(['H' => 1, 'I' => 0, 'S' => 0, 'A' => 1])
        ->and($femaleRow['percent'])->toBe(50)
        ->and($page->viewData('columns'))->toHaveCount(2);

    expect($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026')
        ->and($page->viewData('columns')[1]['label'])->toBe('Senin, 12 Oktober 2026');
});

test('percentages use only recorded statuses', function () {
    $peminatan = peminatanRecapType('Peminatan A');
    $class = KelasFactory::new()->create();
    $student = peminatanRecapStudent('Ahmad', $peminatan, $class->id);
    peminatanRecapSession($peminatan, '2026-10-05', [$student->id => 'Hadir']);
    peminatanRecapSession($peminatan, '2026-10-12', [$student->id => 'Izin']);
    peminatanRecapSession($peminatan, '2026-10-19', []);
    $this->actingAs(peminatanRecapTeacher()->user);

    $page = peminatanRecapPage();
    $row = collect($page->viewData('rows'))->firstWhere('student.id', $student->id);

    expect($row['counts'])->toBe(['H' => 1, 'I' => 1, 'S' => 0, 'A' => 0])
        ->and($row['percent'])->toBe(50)
        ->and($page->viewData('columns'))->toHaveCount(3);
});

test('only shows peminatan attendance from the running semester', function () {
    $peminatan = peminatanRecapType('Peminatan A');
    $class = KelasFactory::new()->create();
    $student = peminatanRecapStudent('Ahmad', $peminatan, $class->id);
    peminatanRecapSession($peminatan, '2026-10-05', [$student->id => 'Hadir']);
    peminatanRecapSession($peminatan, '2026-02-10', [$student->id => 'Alpa']);
    $this->actingAs(peminatanRecapTeacher()->user);

    $page = peminatanRecapPage();

    expect($page->viewData('columns'))->toHaveCount(1);
    expect($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026');
});

test('filters students and sessions by the selected peminatan type', function () {
    $peminatanA = peminatanRecapType('Peminatan A');
    $peminatanB = peminatanRecapType('Peminatan B');
    $class = KelasFactory::new()->create();
    $studentA = peminatanRecapStudent('Siswa A', $peminatanA, $class->id);
    $studentB = peminatanRecapStudent('Siswa B', $peminatanB, $class->id);
    peminatanRecapSession($peminatanA, '2026-10-05', [$studentA->id => 'Hadir']);
    peminatanRecapSession($peminatanB, '2026-10-12', [$studentB->id => 'Hadir']);
    $this->actingAs(peminatanRecapTeacher()->user);

    peminatanRecapPage()
        ->assertSet('jenisPeminatanId', $peminatanA->id)
        ->assertSee('Siswa A')->assertDontSee('Siswa B')
        ->call('pilihPeminatan', $peminatanB->id)
        ->assertSet('jenisPeminatanId', $peminatanB->id)
        ->assertSee('Siswa B')
        ->assertSee('Senin, 12 Oktober 2026');

    peminatanRecapPage()->call('pilihPeminatan', 999999)->assertNotFound();
});

test('shows a notice and link when the peminatan has no attendance record', function () {
    $peminatan = peminatanRecapType('Peminatan A');
    $class = KelasFactory::new()->create();
    peminatanRecapStudent('Ahmad', $peminatan, $class->id);
    $this->actingAs(peminatanRecapTeacher()->user);

    peminatanRecapPage()
        ->assertSee('Belum ada absensi peminatan tercatat untuk Peminatan A')
        ->assertSeeHtml('href="'.e(AbsensiPeminatan::getUrl()).'"');
});

test('shows a notice when the peminatan has no students', function () {
    peminatanRecapType('Peminatan A');
    $this->actingAs(peminatanRecapTeacher()->user);

    peminatanRecapPage()->assertSee('Belum ada siswa pada peminatan ini.');
});

test('locks the selected peminatan property', function () {
    peminatanRecapType('Peminatan A');
    $this->actingAs(peminatanRecapTeacher()->user);
    $component = peminatanRecapPage();

    expect(fn () => $component->set('jenisPeminatanId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('escapes student identity text in the peminatan recap', function () {
    $peminatan = peminatanRecapType('Peminatan A');
    $class = KelasFactory::new()->create();
    $student = MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $student->jenisPeminatans()->attach($peminatan->id);
    peminatanRecapSession($peminatan, '2026-10-05', [$student->id => 'Hadir']);
    $this->actingAs(peminatanRecapTeacher()->user);

    peminatanRecapPage()
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
