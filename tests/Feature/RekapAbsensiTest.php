<?php

use App\Filament\Pages\PresensiSiswa;
use App\Filament\Pages\RekapAbsensi;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\SesiMengajar;
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

function rekapTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function rekapSchedule(Guru $teacher, MataPelajaran $subject, int $classId, array $attributes = []): JadwalPelajaran
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

function rekapPage(): Testable
{
    return Livewire::test(RekapAbsensi::class);
}

/** @param array<int, string> $statuses */
function rekapSession(JadwalPelajaran $schedule, string $date, array $statuses): SesiMengajar
{
    $session = SesiMengajar::query()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'guru_id' => $schedule->guru_id,
        'kelas_id' => $schedule->kelas_id,
        'pencatat_id' => $schedule->guru?->user_id,
        'revisi' => 1,
        'tanggal' => $date,
        'semester' => $schedule->semester,
        'tahun_ajaran' => $schedule->tahun_ajaran,
        'nama_guru' => $schedule->guru?->user?->name ?? 'Guru',
        'nama_kelas' => $schedule->kelas?->nama_kelas ?? 'Kelas',
        'nama_mapel' => $schedule->mataPelajaran?->nama_mapel ?? 'Mapel',
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '09:00:00',
        'dicatat_pada' => now(),
    ]);

    foreach ($statuses as $studentId => $status) {
        $student = Murid::query()->find($studentId);

        $session->presensiMurids()->create([
            'murid_id' => $studentId,
            'nama_murid' => $student?->nama ?? 'Siswa',
            'nis' => $student?->nis,
            'status' => $status,
        ]);
    }

    return $session;
}

test('shows the menu in the Laporan Kehadiran group', function () {
    expect(RekapAbsensi::getNavigationGroup())->toBe('Laporan Kehadiran')
        ->and(RekapAbsensi::getNavigationLabel())->toBe('Rekap Absensi')
        ->and(RekapAbsensi::getSlug())->toBe('rekap-absensi')
        ->and(RekapAbsensi::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.rekap-absensi'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.rekap-absensi'))->assertForbidden();
    Livewire::test(RekapAbsensi::class)->assertForbidden();
});

test('shows an empty state when there is no teaching schedule', function () {
    $this->actingAs(rekapTeacher()->user);

    Livewire::test(RekapAbsensi::class)
        ->assertOk()
        ->assertSee('Tidak ada kelas atau mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.');
});

test('selects the first class and renders the attendance grid', function () {
    $teacher = rekapTeacher(['user_id' => User::factory()->create(['name' => 'Guru Rekap'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    $schedule = rekapSchedule($teacher, $subject, $class->id);
    $male = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Ahmad', 'jenis_kelamin' => 'laki-laki', 'nis' => '001']);
    $female = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Fatimah', 'jenis_kelamin' => 'perempuan', 'nis' => '002']);
    rekapSession($schedule, '2026-10-05', [$male->id => 'Hadir', $female->id => 'Hadir']);
    rekapSession($schedule, '2026-10-12', [$male->id => 'Sakit', $female->id => 'Alpa']);
    $this->actingAs($teacher->user);

    $page = rekapPage()
        ->assertOk()
        ->assertSet('kelasId', $class->id)
        ->assertSet('mataPelajaranId', $subject->id)
        ->assertSee('Klik Untuk Pilih Kelas')
        ->assertSee('Kelas X')->assertSee('Tahfidz')->assertSee('Guru Rekap')
        ->assertSee('Laki-laki:')->assertSee('Perempuan:')->assertSee('Jml:')
        ->assertSeeInOrder(['Laki-laki:', '1', 'Perempuan:', '1', 'Jml:', '2'])
        ->assertSee('Tanggal-Pertemuan ke-')->assertSee('Absensi')->assertSee('% Kehadiran')
        ->assertSee('Senin, 5 Oktober 2026')->assertSee('Senin, 12 Oktober 2026')
        ->assertSee('Ahmad')->assertSee('Fatimah')
        ->assertSeeHtml('rekap-status-h')->assertSeeHtml('rekap-status-s')->assertSeeHtml('rekap-status-a');

    $rows = collect($page->viewData('rows'));
    $maleRow = $rows->firstWhere('student.id', $male->id);
    $femaleRow = $rows->firstWhere('student.id', $female->id);

    expect($maleRow['counts'])->toBe(['S' => 1, 'I' => 0, 'A' => 0, 'H' => 1])
        ->and($maleRow['percent'])->toBe(50)
        ->and($femaleRow['counts'])->toBe(['S' => 0, 'I' => 0, 'A' => 1, 'H' => 1])
        ->and($femaleRow['percent'])->toBe(50)
        ->and($page->viewData('columns'))->toHaveCount(2);

    expect($page->viewData('columns')[0]['nomor'])->toBe(1)
        ->and($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026')
        ->and($page->viewData('columns')[1]['nomor'])->toBe(2)
        ->and($page->viewData('columns')[1]['label'])->toBe('Senin, 12 Oktober 2026');
});

test('switches the selected class and subject', function () {
    $teacher = rekapTeacher();
    $firstSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Aljabar']);
    $secondSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $otherSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Fiqih']);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    rekapSchedule($teacher, $secondSubject, $classA->id);
    rekapSchedule($teacher, $firstSubject, $classA->id);
    rekapSchedule($teacher, $otherSubject, $classB->id);
    $this->actingAs($teacher->user);

    rekapPage()
        ->assertSet('kelasId', $classA->id)
        ->assertSet('mataPelajaranId', $firstSubject->id)
        ->call('pilihMapel', $secondSubject->id)
        ->assertSet('mataPelajaranId', $secondSubject->id)
        ->call('pilihKelas', $classB->id)
        ->assertSet('kelasId', $classB->id)
        ->assertSet('mataPelajaranId', $otherSubject->id)
        ->assertSee('Kelas B')->assertSee('Fiqih');
});

test('rejects a class or subject the teacher does not teach', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $otherSubject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $otherClass = KelasFactory::new()->create();
    rekapSchedule($teacher, $subject, $class->id);
    rekapSchedule(rekapTeacher(), $subject, $otherClass->id);
    $this->actingAs($teacher->user);

    rekapPage()->call('pilihKelas', $otherClass->id)->assertNotFound();
    rekapPage()->call('pilihMapel', $otherSubject->id)->assertNotFound();
});

test('only shows sessions from the running period and the selected class and subject', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $otherSubject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $otherClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    $schedule = rekapSchedule($teacher, $subject, $class->id);
    $otherSchedule = rekapSchedule($teacher, $otherSubject, $class->id);
    $oldSchedule = rekapSchedule($teacher, $subject, $class->id, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $otherClassSchedule = rekapSchedule($teacher, $subject, $otherClass->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    rekapSession($schedule, '2026-10-05', [$student->id => 'Hadir']);
    rekapSession($otherSchedule, '2026-10-06', [$student->id => 'Alpa']);
    rekapSession($oldSchedule, '2026-02-10', [$student->id => 'Hadir']);
    rekapSession($otherClassSchedule, '2026-10-07', []);
    $this->actingAs($teacher->user);

    $page = rekapPage()->call('pilihMapel', $subject->id);

    expect($page->viewData('columns'))->toHaveCount(1);
    expect($page->viewData('columns')[0]['label'])->toBe('Senin, 5 Oktober 2026');
});

test('percentages use only recorded statuses so empty sessions do not lower the score', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $schedule = rekapSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    rekapSession($schedule, '2026-10-05', [$student->id => 'Hadir']);
    rekapSession($schedule, '2026-10-12', [$student->id => 'Sakit']);
    rekapSession($schedule, '2026-10-19', []);
    $this->actingAs($teacher->user);

    $page = rekapPage();
    $row = collect($page->viewData('rows'))->firstWhere('student.id', $student->id);

    expect($row['counts'])->toBe(['S' => 1, 'I' => 0, 'A' => 0, 'H' => 1])
        ->and($row['percent'])->toBe(50)
        ->and($row['statuses'])->toHaveCount(3);

    expect($page->viewData('columns'))->toHaveCount(3);
});

test('shows a notice and presensi link when no attendance is recorded', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas X']);
    rekapSchedule($teacher, $subject, $class->id);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(RekapAbsensi::class)
        ->assertSee('Belum ada presensi tercatat untuk Tahfidz di Kelas X')
        ->assertSeeHtml('href="'.e(PresensiSiswa::getUrl()).'"')
        ->assertDontSeeHtml('rekap-matrix');
});

test('shows a notice when the class has no students', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    rekapSchedule($teacher, $subject, $class->id);
    $this->actingAs($teacher->user);

    Livewire::test(RekapAbsensi::class)
        ->assertSee('Belum ada siswa di kelas ini.');
});

test('locks the selected class and subject properties', function (string $property) {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    rekapSchedule($teacher, $subject, $class->id);
    $this->actingAs($teacher->user);
    $component = rekapPage();

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'mataPelajaranId']);

test('escapes student identity text in the recap', function () {
    $teacher = rekapTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $class = KelasFactory::new()->create();
    $schedule = rekapSchedule($teacher, $subject, $class->id);
    $student = MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    rekapSession($schedule, '2026-10-05', [$student->id => 'Hadir']);
    $this->actingAs($teacher->user);

    Livewire::test(RekapAbsensi::class)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
