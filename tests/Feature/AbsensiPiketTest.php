<?php

use App\Filament\Pages\AbsensiPiket;
use App\Models\AbsenPiket;
use App\Models\Guru;
use App\Models\JadwalPiket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenPiketFactory;
use Database\Factories\AbsenPiketMuridFactory;
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

function piketTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

function piketSchedule(Guru $teacher, string $day = 'Senin'): JadwalPiket
{
    return JadwalPiket::query()->create([
        'hari' => $day,
        'guru_id' => $teacher->id,
        'ket' => null,
    ]);
}

test('shows the menu in the Pembelajaran group', function () {
    expect(AbsensiPiket::getNavigationGroup())->toBe('Absensi')
        ->and(AbsensiPiket::getNavigationLabel())->toBe('Absensi Piket')
        ->and(AbsensiPiket::getSlug())->toBe('absensi-piket')
        ->and(AbsensiPiket::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.absensi-piket'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.absensi-piket'))->assertForbidden();
    Livewire::test(AbsensiPiket::class)->assertForbidden();
});

test('shows a notice when the teacher has no piket schedule on the date', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher, 'Selasa');
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->assertOk()
        ->assertSee('Anda tidak memiliki jadwal piket pada tanggal ini.')
        ->assertDontSeeHtml('wire:click="pilihKelas('.$class->id.')"')
        ->call('pilihKelas', $class->id)->assertForbidden();
});

test('lists every class with its student count for the piket teacher', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $withStudents = KelasFactory::new()->create(['nama_kelas' => 'Kelas A']);
    $withoutStudents = KelasFactory::new()->create(['nama_kelas' => 'Kelas B']);
    MuridFactory::new()->count(2)->create(['kelas_id' => $withStudents->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->assertSee('Kelas A')->assertSee('Kelas B')
        ->assertSeeInOrder(['Kelas A', '2', '0/2', '0/2', 'Belum diisi'])
        ->assertSeeInOrder(['Kelas B', '0', '-', '-', 'Belum ada siswa'])
        ->assertSeeHtml('wire:click="pilihKelas('.$withStudents->id.')"')
        ->assertDontSeeHtml('wire:click="pilihKelas('.$withoutStudents->id.')"');
});

test('saves masuk and pulang snapshots for every student', function () {
    $teacher = piketTeacher(['user_id' => User::factory()->create(['name' => 'Guru Piket'])->id]);
    piketSchedule($teacher);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VII']);
    $first = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Pertama', 'nis' => '101']);
    $second = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Kedua', 'nis' => '102']);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$first->id, ['masuk' => 'Hadir', 'pulang' => 'Pulang'])
        ->set('kehadiran.m'.$second->id, ['masuk' => 'Terlambat', 'pulang' => ''])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Absensi piket tersimpan');

    $session = AbsenPiket::query()->sole();
    $this->assertDatabaseHas('absen_pikets', [
        'id' => $session->id, 'kelas_id' => $class->id, 'guru_id' => $teacher->id,
        'pencatat_id' => $teacher->user_id, 'revisi' => 1,
        'nama_guru' => 'Guru Piket', 'nama_kelas' => 'Kelas VII', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    expect($session->tanggal->toDateString())->toBe('2026-10-19');
    $this->assertDatabaseCount('absen_piket_murids', 2);
    $this->assertDatabaseHas('absen_piket_murids', [
        'absen_piket_id' => $session->id, 'murid_id' => $first->id, 'nama_murid' => 'Siswa Pertama',
        'nis' => '101', 'masuk' => 'Hadir', 'pulang' => 'Pulang',
    ]);
    $this->assertDatabaseHas('absen_piket_murids', [
        'absen_piket_id' => $session->id, 'murid_id' => $second->id, 'masuk' => 'Terlambat', 'pulang' => null,
    ]);
});

test('keeps separate piket records for two teachers on the same class and date', function () {
    $first = piketTeacher(['user_id' => User::factory()->create(['name' => 'Guru Pertama'])->id]);
    $second = piketTeacher(['user_id' => User::factory()->create(['name' => 'Guru Kedua'])->id]);
    piketSchedule($first);
    piketSchedule($second);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas Bersama']);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);

    $this->actingAs($first->user);
    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$student->id, ['masuk' => 'Hadir', 'pulang' => 'Pulang'])
        ->call('simpan')->assertHasNoErrors();

    $this->actingAs($second->user);
    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$student->id, ['masuk' => 'Terlambat', 'pulang' => 'Pulang'])
        ->call('simpan')->assertHasNoErrors();

    $sessions = AbsenPiket::query()->with('murids')->orderBy('guru_id')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions->pluck('guru_id')->all())->toBe([$first->id, $second->id])
        ->and($sessions[0]->murids->first()->masuk)->toBe('Hadir')
        ->and($sessions[1]->murids->first()->masuk)->toBe('Terlambat')
        ->and($sessions[0]->murids->first()->absen_piket_id)->not->toBe($sessions[1]->murids->first()->absen_piket_id);
});

test('updates the same class record and keeps the first recorded time', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->count(2)->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    $component = Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->call('tandaiSemuaMasuk')
        ->call('simpan')->assertHasNoErrors();
    $session = AbsenPiket::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    $component->call('pilihKelas', $class->id)
        ->call('tandaiSemuaPulang')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('absen_pikets', 1);
    $this->assertDatabaseCount('absen_piket_murids', 2);
    $this->assertDatabaseHas('absen_pikets', [
        'id' => $session->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('absen_piket_murids', ['absen_piket_id' => $session->id, 'pulang' => 'Pulang']);
});

test('marks every student as present and pulang', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->count(3)->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->call('tandaiSemuaMasuk')
        ->call('tandaiSemuaPulang')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('absen_piket_murids', 3);
    $this->assertDatabaseMissing('absen_piket_murids', ['masuk' => null]);
    $this->assertDatabaseMissing('absen_piket_murids', ['pulang' => null]);
});

test('requires masuk before pulang', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$student->id, ['masuk' => '', 'pulang' => 'Pulang'])
        ->call('simpan')
        ->assertHasErrors(['kehadiran.m'.$student->id.'.masuk'])
        ->assertSee('Isi absen masuk sebelum absen pulang.')
        ->assertNotNotified();

    $this->assertDatabaseCount('absen_pikets', 0);
    $this->assertDatabaseCount('absen_piket_murids', 0);
});

test('rejects statuses outside the allowed list', function (string $field, string $value) {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$student->id, ['masuk' => $field === 'masuk' ? $value : 'Hadir', 'pulang' => $field === 'pulang' ? $value : 'Pulang'])
        ->call('simpan')
        ->assertHasErrors(['kehadiran.m'.$student->id.'.'.$field])
        ->assertNotNotified();

    $this->assertDatabaseCount('absen_pikets', 0);
})->with([
    'absen masuk' => ['masuk', 'Bolos'],
    'absen pulang' => ['pulang', 'Setengah hari'],
]);

test('rejects filling a date older than seven days', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->set('tanggal', '2026-10-11')
        ->call('pilihKelas', $class->id)
        ->assertHasErrors(['tanggal'])
        ->assertSee('maksimal 7 hari ke belakang');

    $this->assertDatabaseCount('absen_pikets', 0);
});

test('rejects a date in the future', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->set('tanggal', '2026-10-20')
        ->assertHasErrors(['tanggal' => 'before_or_equal']);

    $this->assertDatabaseCount('absen_pikets', 0);
});

test('shows old records as read only history', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher, 'Minggu');
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas Riwayat']);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Riwayat']);
    $session = AbsenPiketFactory::new()->create([
        'kelas_id' => $class->id, 'guru_id' => $teacher->id, 'tanggal' => '2026-10-04',
        'nama_kelas' => 'Kelas Riwayat', 'nama_guru' => $teacher->nama,
    ]);
    AbsenPiketMuridFactory::new()->create([
        'absen_piket_id' => $session->id, 'murid_id' => $student->id, 'nama_murid' => 'Siswa Riwayat',
        'masuk' => 'Hadir', 'pulang' => 'Pulang',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->set('tanggal', '2026-10-04')
        ->assertSee('Mode riwayat.')
        ->call('lihatAbsen', $session->id)
        ->assertSee('Siswa Riwayat')->assertSee('Hadir')->assertSee('Pulang')
        ->assertDontSeeHtml('wire:submit="simpan"')
        ->assertDontSeeHtml('wire:click="simpan"');
});

test('allows filling a past date inside the seven day window', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher, 'Minggu');
    $class = KelasFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->set('tanggal', '2026-10-18')
        ->call('pilihKelas', $class->id)
        ->set('kehadiran.m'.$student->id, ['masuk' => 'Hadir', 'pulang' => 'Pulang'])
        ->call('simpan')->assertHasNoErrors();

    $session = AbsenPiket::query()->sole();

    expect($session->tanggal->toDateString())->toBe('2026-10-18');
});

test('protects the locked class and record properties', function (string $property) {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $class->id]);
    $this->actingAs($teacher->user);
    $component = Livewire::test(AbsensiPiket::class)->call('pilihKelas', $class->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['kelasId', 'absenId', 'revisi']);

test('escapes student identity text in the attendance table', function () {
    $teacher = piketTeacher();
    piketSchedule($teacher);
    $class = KelasFactory::new()->create();
    MuridFactory::new()->create([
        'kelas_id' => $class->id,
        'nama' => '<script>siswa()</script>',
        'nis' => '<img src=x onerror=alert(1)>',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiPiket::class)
        ->call('pilihKelas', $class->id)
        ->assertSee('<script>siswa()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>siswa()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});
