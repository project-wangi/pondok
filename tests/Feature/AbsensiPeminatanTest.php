<?php

use App\Filament\Pages\AbsensiPeminatan;
use App\Models\AbsenPeminatan;
use App\Models\Guru;
use App\Models\JadwalPeminatan;
use App\Models\JenisPeminatan;
use App\Models\Murid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenPeminatanFactory;
use Database\Factories\AbsenPeminatanMuridFactory;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
function peminatanTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

function peminatanType(string $name): JenisPeminatan
{
    return JenisPeminatan::query()->create([
        'nama_peminatan' => $name,
        'nominal' => 100000,
    ]);
}

function peminatanSchedule(Guru $teacher, JenisPeminatan $peminatan, string $day = 'Senin', string $start = '08:00:00', string $end = '09:30:00'): JadwalPeminatan
{
    return JadwalPeminatan::query()->create([
        'hari' => $day,
        'jam_mulai' => $start,
        'jam_selesai' => $end,
        'guru_id' => $teacher->id,
        'jenis_peminatan_id' => $peminatan->id,
    ]);
}

function peminatanStudent(string $name, ?string $nis = null, ?int $classId = null): Murid
{
    return MuridFactory::new()->create([
        'nama' => $name,
        'nis' => $nis,
        'kelas_id' => $classId ?? KelasFactory::new()->create()->id,
    ]);
}

test('shows the menu in the Pembelajaran group', function () {
    expect(AbsensiPeminatan::getNavigationGroup())->toBe('Pembelajaran')
        ->and(AbsensiPeminatan::getNavigationLabel())->toBe('Absensi Peminatan')
        ->and(AbsensiPeminatan::getSlug())->toBe('absensi-peminatan')
        ->and(AbsensiPeminatan::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.absensi-peminatan'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.absensi-peminatan'))->assertForbidden();
    Livewire::test(AbsensiPeminatan::class)->assertForbidden();
});

test('shows a notice when the teacher has no peminatan schedule on the date', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan, 'Selasa');
    $student = peminatanStudent('Siswa Peminatan');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->assertOk()
        ->assertSee('Anda tidak memiliki jadwal peminatan pada tanggal ini.')
        ->assertDontSeeHtml('wire:click="pilihPeminatan('.$peminatan->id.')"')
        ->call('pilihPeminatan', $peminatan->id)->assertForbidden();
});

test('lists scheduled peminatan with student counts and only its own students', function () {
    $teacher = peminatanTeacher();
    $tahfidz = peminatanType('Tahfidz');
    $bahasaArab = peminatanType('Bahasa Arab');
    peminatanSchedule($teacher, $tahfidz);
    peminatanSchedule($teacher, $bahasaArab);
    $classA = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $classB = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    $first = peminatanStudent('Siswa Tahfidz Satu', '001', $classA->id);
    $second = peminatanStudent('Siswa Tahfidz Dua', '002', $classB->id);
    $third = peminatanStudent('Siswa Bahasa Arab', '003', $classA->id);
    $without = peminatanStudent('Siswa Tanpa Peminatan', '004', $classA->id);
    $first->jenisPeminatans()->attach($tahfidz->id);
    $second->jenisPeminatans()->attach($tahfidz->id);
    $third->jenisPeminatans()->attach($bahasaArab->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->assertSee('Tahfidz')->assertSee('Bahasa Arab')
        ->assertSeeInOrder(['Bahasa Arab', '1', '0/1', 'Belum diisi'])
        ->assertSeeInOrder(['Tahfidz', '2', '0/2', 'Belum diisi'])
        ->assertSee('08:00 - 09:30')
        ->assertSeeHtml('wire:click="pilihPeminatan('.$tahfidz->id.')"')
        ->call('pilihPeminatan', $tahfidz->id)
        ->assertSee('Siswa Tahfidz Satu')->assertSee('Siswa Tahfidz Dua')->assertSee('Kelas A')->assertSee('Kelas B')
        ->assertDontSee('Siswa Bahasa Arab')->assertDontSee('Siswa Tanpa Peminatan');
});

test('saves one attendance row per peminatan student', function () {
    $teacher = peminatanTeacher(['user_id' => User::factory()->create(['name' => 'Guru Peminatan'])->id]);
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VII']);
    $first = peminatanStudent('Siswa Pertama', '101', $class->id);
    $second = peminatanStudent('Siswa Kedua', '102', $class->id);
    $first->jenisPeminatans()->attach($peminatan->id);
    $second->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->set('kehadiran.m'.$first->id, ['status' => 'Hadir'])
        ->set('kehadiran.m'.$second->id, ['status' => 'Izin'])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Absensi peminatan tersimpan');

    $session = AbsenPeminatan::query()->sole();
    $this->assertDatabaseHas('absen_peminatans', [
        'id' => $session->id, 'jenis_peminatan_id' => $peminatan->id, 'guru_id' => $teacher->id,
        'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'jam_mulai' => '08:00:00', 'jam_selesai' => '09:30:00',
        'nama_guru' => 'Guru Peminatan', 'nama_peminatan' => 'Tahfidz', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    expect($session->tanggal->toDateString())->toBe('2026-10-19');
    $this->assertDatabaseCount('absen_peminatan_murids', 2);
    $this->assertDatabaseHas('absen_peminatan_murids', [
        'absen_peminatan_id' => $session->id, 'murid_id' => $first->id, 'nama_murid' => 'Siswa Pertama',
        'nis' => '101', 'nama_kelas' => 'Kelas VII', 'status' => 'Hadir',
    ]);
    $this->assertDatabaseHas('absen_peminatan_murids', [
        'absen_peminatan_id' => $session->id, 'murid_id' => $second->id, 'status' => 'Izin',
    ]);
});

test('updates the same peminatan record and keeps the first recorded time', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $student = peminatanStudent('Siswa Satu');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    $component = Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->set('kehadiran.m'.$student->id, ['status' => 'Alpa'])
        ->call('simpan')->assertHasNoErrors();
    $session = AbsenPeminatan::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    $component->call('pilihPeminatan', $peminatan->id)
        ->call('tandaiSemuaHadir')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('absen_peminatans', 1);
    $this->assertDatabaseCount('absen_peminatan_murids', 1);
    $this->assertDatabaseHas('absen_peminatans', [
        'id' => $session->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('absen_peminatan_murids', [
        'absen_peminatan_id' => $session->id, 'murid_id' => $student->id, 'status' => 'Hadir',
    ]);
});

test('marks every student as present', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    foreach (range(1, 3) as $index) {
        $student = peminatanStudent('Siswa '.$index);
        $student->jenisPeminatans()->attach($peminatan->id);
    }
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->call('tandaiSemuaHadir')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('absen_peminatan_murids', 3);
    $this->assertDatabaseMissing('absen_peminatan_murids', ['status' => 'Alpa']);
    $this->assertDatabaseHas('absen_peminatan_murids', ['status' => 'Hadir']);
});

test('rejects a missing or invalid status', function (string $value, string $message) {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $student = peminatanStudent('Siswa Satu');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->set('kehadiran.m'.$student->id, ['status' => $value])
        ->call('simpan')
        ->assertHasErrors(['kehadiran.m'.$student->id.'.status'])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('absen_peminatans', 0);
    $this->assertDatabaseCount('absen_peminatan_murids', 0);
})->with([
    'missing' => ['', 'Pilih status kehadiran untuk setiap siswa.'],
    'invalid' => ['Bolos', 'Status kehadiran tidak valid.'],
]);

test('rejects a peminatan without students', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->call('simpan')
        ->assertHasErrors(['kehadiran'])
        ->assertSee('Belum ada siswa yang terdaftar pada peminatan ini.');

    $this->assertDatabaseCount('absen_peminatans', 0);
});

test('rejects filling a date older than seven days', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan, 'Minggu');
    $student = peminatanStudent('Siswa Satu');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->set('tanggal', '2026-10-11')
        ->call('pilihPeminatan', $peminatan->id)
        ->assertHasErrors(['tanggal'])
        ->assertSee('maksimal 7 hari ke belakang');

    $this->assertDatabaseCount('absen_peminatans', 0);
});

test('rejects a date in the future', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->set('tanggal', '2026-10-20')
        ->assertHasErrors(['tanggal' => 'before_or_equal']);
});

test('allows filling a past date inside the seven day window', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan, 'Minggu');
    $student = peminatanStudent('Siswa Satu');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->set('tanggal', '2026-10-18')
        ->call('pilihPeminatan', $peminatan->id)
        ->set('kehadiran.m'.$student->id, ['status' => 'Hadir'])
        ->call('simpan')->assertHasNoErrors();

    expect(AbsenPeminatan::query()->sole()->tanggal->toDateString())->toBe('2026-10-18');
});

test('shows old records as read only history', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan, 'Minggu');
    $student = peminatanStudent('Siswa Riwayat');
    $student->jenisPeminatans()->attach($peminatan->id);
    $session = AbsenPeminatanFactory::new()->create([
        'jenis_peminatan_id' => $peminatan->id, 'guru_id' => $teacher->id, 'tanggal' => '2026-10-04',
        'nama_guru' => $teacher->nama, 'nama_peminatan' => 'Tahfidz',
    ]);
    AbsenPeminatanMuridFactory::new()->create([
        'absen_peminatan_id' => $session->id, 'murid_id' => $student->id,
        'nama_murid' => 'Siswa Riwayat', 'status' => 'Hadir',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->set('tanggal', '2026-10-04')
        ->assertSee('Mode riwayat.')
        ->call('lihatAbsen', $session->id)
        ->assertSee('Siswa Riwayat')->assertSee('Hadir')
        ->assertSee('07:00 - 09:00')
        ->assertDontSeeHtml('wire:submit="simpan"')
        ->assertDontSeeHtml('wire:click="simpan"');
});

test('protects the locked peminatan and record properties', function (string $property) {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $student = peminatanStudent('Siswa Satu');
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);
    $component = Livewire::test(AbsensiPeminatan::class)->call('pilihPeminatan', $peminatan->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['peminatanId', 'absenId', 'revisi']);

test('escapes student identity text in the attendance table', function () {
    $teacher = peminatanTeacher();
    $peminatan = peminatanType('Tahfidz');
    peminatanSchedule($teacher, $peminatan);
    $student = MuridFactory::new()->create([
        'kelas_id' => KelasFactory::new()->create()->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $student->jenisPeminatans()->attach($peminatan->id);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPeminatan::class)
        ->call('pilihPeminatan', $peminatan->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
