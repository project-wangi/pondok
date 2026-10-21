<?php

use App\Filament\Pages\PresensiSiswa;
use App\Models\PresensiMurid;
use App\Models\SesiMengajar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Database\Factories\PresensiMuridFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->originalTimezone = date_default_timezone_get();
    config()->set('app.timezone', 'Asia/Jakarta');
    date_default_timezone_set('Asia/Jakarta');
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
    date_default_timezone_set($this->originalTimezone);
});

test('shows the menu in the Absensi group', function () {
    expect(PresensiSiswa::getNavigationGroup())->toBe('Absensi')
        ->and(PresensiSiswa::getNavigationLabel())->toBe('Presensi Siswa')
        ->and(PresensiSiswa::shouldRegisterNavigation())->toBeTrue();
});

test('preserves panel access for existing administrative roles without granting attendance access', function (string $roleName) {
    Role::create(['name' => $roleName, 'guard_name' => 'web']);
    $user = User::factory()->create()->assignRole($roleName);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    $this->actingAs($user);
    $this->get(route('filament.admin.pages.presensi-siswa'))->assertForbidden();
})->with(['admin', 'super_admin', 'panel_user']);

test('allows existing permission holders into the admin panel but not other panels', function () {
    Permission::findOrCreate('ViewAny:JadwalPelajaran', 'web');
    $user = User::factory()->create()->givePermissionTo('ViewAny:JadwalPelajaran');

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($user->canAccessPanel(Panel::make()->id('other')))->toBeFalse();
});

test('does not grant panel access to an ordinary unassigned account', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

test('redirects guests to the admin login', function () {
    $this->get(route('filament.admin.pages.presensi-siswa'))
        ->assertRedirectToRoute('filament.admin.auth.login');
});

test('forbids authenticated users without a linked teacher', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('filament.admin.pages.presensi-siswa'))->assertForbidden();
    Livewire::test(PresensiSiswa::class)->assertForbidden();
});

test('renders for a linked teacher without roles or permissions', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    $this->get(route('filament.admin.pages.presensi-siswa'))
        ->assertOk()
        ->assertSeeLivewire(PresensiSiswa::class)
        ->assertSee('Tidak ada jadwal mengajar pada periode berjalan.');
});

test('initializes the Livewire page for a linked teacher without roles or permissions', function () {
    $this->actingAs(GuruFactory::new()->create()->user);

    Livewire::test(PresensiSiswa::class)
        ->assertOk()
        ->assertSee('Tidak ada jadwal mengajar pada periode berjalan.')
        ->assertSet('tanggal', '2026-10-19')
        ->assertSet('jadwalId', null)
        ->assertSet('kehadiran', []);
});

test('lists only the teachers Monday schedules and loads only the selected class roster', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $later = JadwalPelajaranFactory::new()->create([
        'guru_id' => $schedule->guru_id, 'jam_mulai' => '11:00:00', 'jam_selesai' => '12:00:00',
    ]);
    $tomorrow = JadwalPelajaranFactory::new()->create(['guru_id' => $schedule->guru_id, 'hari' => 'Selasa']);
    $foreign = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id, 'nama' => 'Siswa Kelas Terpilih', 'nis' => 'NIS-101']);
    $otherStudent = MuridFactory::new()->create([
        'kelas_id' => $foreign->kelas_id, 'nama' => 'Siswa Kelas Rahasia', 'nis' => 'NIS-RAHASIA',
        'email' => 'rahasia@example.test', 'alamat' => 'Alamat Rahasia', 'nik' => '9876543210123456',
    ]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)
        ->assertOk()
        ->assertSeeHtml('wire:click="pilihJadwal('.$schedule->id.')"')
        ->assertSeeHtml('wire:click="pilihJadwal('.$later->id.')"')
        ->assertDontSeeHtml('wire:click="pilihJadwal('.$tomorrow->id.')"')
        ->assertDontSeeHtml('wire:click="pilihJadwal('.$foreign->id.')"')
        ->call('pilihJadwal', $schedule->id)
        ->assertHasNoErrors()
        ->assertSet('jadwalId', $schedule->id)
        ->assertSet('kehadiran', ['m'.$student->id => ['status' => '', 'catatan' => '']])
        ->assertSee($student->nama)
        ->assertSee('NIS-101')
        ->assertDontSee($otherStudent->nama)
        ->assertDontSee($otherStudent->nis)
        ->assertDontSee($otherStudent->email)
        ->assertDontSee($otherStudent->alamat)
        ->assertDontSee($otherStudent->nik);
});

test('saves every supported status and note with teacher and schedule snapshots', function () {
    $teacher = GuruFactory::new()->create(['user_id' => User::factory()->create(['name' => 'Guru Pencatat'])->id]);
    $class = KelasFactory::new()->create(['nama_kelas' => 'VII Presensi']);
    $schedule = JadwalPelajaranFactory::new()->create(['guru_id' => $teacher->id, 'kelas_id' => $class->id]);
    $schedule->mataPelajaran->update(['nama_mapel' => 'Pelajaran Presensi']);
    $students = MuridFactory::new()->count(4)->sequence(
        ['nama' => 'Siswa Hadir', 'nis' => '101'],
        ['nama' => 'Siswa Izin', 'nis' => '102'],
        ['nama' => 'Siswa Sakit', 'nis' => '103'],
        ['nama' => 'Siswa Alpa', 'nis' => '104'],
    )->create(['kelas_id' => $class->id]);
    $entries = [
        ['status' => 'Hadir', 'catatan' => ''],
        ['status' => 'Izin', 'catatan' => str_repeat('a', 500)],
        ['status' => 'Sakit', 'catatan' => 'Istirahat atas anjuran dokter'],
        ['status' => 'Alpa', 'catatan' => null],
    ];
    $payload = $students->mapWithKeys(fn ($student, int $index): array => ['m'.$student->id => $entries[$index]])->all();
    $this->actingAs($teacher->user);

    $component = Livewire::test(PresensiSiswa::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('kehadiran', $payload)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Presensi tersimpan')
        ->assertSee('Catatan sesi ini bukan perhitungan penggajian.');

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 4);
    $session = SesiMengajar::query()->sole();
    $this->assertDatabaseHas('sesi_mengajars', [
        'id' => $session->id, 'jadwal_pelajaran_id' => $schedule->id,
        'guru_id' => $teacher->id, 'kelas_id' => $class->id, 'pencatat_id' => $teacher->user_id,
        'nama_guru' => 'Guru Pencatat', 'nama_kelas' => 'VII Presensi', 'nama_mapel' => 'Pelajaran Presensi',
        'jam_mulai' => '07:00:00', 'jam_selesai' => '09:00:00', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    expect($session->tanggal->toDateString())->toBe('2026-10-19');
    $savedPayload = [];

    foreach ($students as $index => $student) {
        $this->assertDatabaseHas('presensi_murids', [
            'sesi_mengajar_id' => $session->id, 'murid_id' => $student->id,
            'nama_murid' => $student->nama, 'nis' => $student->nis, ...$entries[$index],
        ]);
        $attendance = $session->presensiMurids()->where('murid_id', $student->id)->sole();
        $savedPayload['p'.$attendance->id] = $entries[$index];
    }

    $component->assertSet('kehadiran', $savedPayload);
});

test('updates the existing session without duplicate rows or changing the first recorded time', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)
        ->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')
        ->call('simpan')
        ->assertHasNoErrors();
    $session = SesiMengajar::query()->sole();
    $attendance = PresensiMurid::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    $component->set('kehadiran.p'.$attendance->id, ['status' => 'Izin', 'catatan' => 'Diperbaiki guru'])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Presensi tersimpan');

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 1);
    $this->assertDatabaseHas('sesi_mengajars', ['id' => $session->id, 'dicatat_pada' => '2026-10-19 10:00:00']);
    $this->assertDatabaseHas('presensi_murids', [
        'id' => $attendance->id, 'sesi_mengajar_id' => $session->id, 'murid_id' => $student->id,
        'status' => 'Izin', 'catatan' => 'Diperbaiki guru',
    ]);
    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSet('kehadiran', ['p'.$attendance->id => ['status' => 'Izin', 'catatan' => 'Diperbaiki guru']]);
});

test('marks the whole roster present while preserving notes and waits for an explicit save', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->set('kehadiran.m'.$students[0]->id, ['status' => 'Sakit', 'catatan' => 'Catatan tetap ada']);

    $component->call('tandaiSemuaHadir')->assertHasNoErrors()->assertSet('kehadiran', [
        'm'.$students[0]->id => ['status' => 'Hadir', 'catatan' => 'Catatan tetap ada'],
        'm'.$students[1]->id => ['status' => 'Hadir', 'catatan' => ''],
    ]);

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('rejects invalid attendance without saving any of the roster', function (array $entry, string $field, string $rule, string $message) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')
        ->set('kehadiran.m'.$students[1]->id, $entry)
        ->call('simpan')
        ->assertHasErrors(['kehadiran.m'.$students[1]->id.$field => $rule])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with([
    'empty status' => [['status' => '', 'catatan' => ''], '.status', 'required', 'Pilih status kehadiran untuk setiap siswa.'],
    'unsupported status' => [['status' => 'Terlambat', 'catatan' => ''], '.status', 'in', 'Status kehadiran tidak valid.'],
    'note exceeds 500 characters' => [['status' => 'Hadir', 'catatan' => str_repeat('a', 501)], '.catatan', 'max', 'Catatan maksimal 500 karakter.'],
]);

test('rejects forged nested identities rather than trusting student metadata from the client', function (string $field) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')
        ->set('kehadiran.m'.$student->id.'.'.$field, '999999')
        ->call('simpan')
        ->assertHasErrors(['kehadiran.m'.$student->id => 'array'])
        ->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['murid_id', 'presensi_id', 'nama_murid', 'nis', 'sesi_mengajar_id']);

test('rejects an extra student from another class', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $foreignStudent = MuridFactory::new()->create();
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')
        ->set('kehadiran.m'.$foreignStudent->id, ['status' => 'Hadir', 'catatan' => ''])
        ->call('simpan')
        ->assertHasErrors(['kehadiran'])
        ->assertSee('Daftar siswa atau presensi telah berubah. Pilih ulang jadwal sebelum menyimpan.')
        ->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('rejects a missing student instead of saving a partial roster', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->set('kehadiran', ['m'.$students[0]->id => ['status' => 'Hadir', 'catatan' => '']])
        ->call('simpan')->assertHasErrors(['kehadiran'])->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('blocks saving an empty class', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSee('Belum ada siswa di kelas ini.')
        ->call('simpan')->assertHasErrors(['kehadiran'])
        ->assertSee('Kelas ini belum memiliki siswa. Hubungi admin untuk mengatur data kelas siswa.')
        ->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('requires a schedule before saving', function () {
    $this->actingAs(GuruFactory::new()->create()->user);

    Livewire::test(PresensiSiswa::class)->call('simpan')
        ->assertHasErrors(['jadwalId' => 'required'])->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('rejects a stale unsaved roster when a student joins or transfers', function (string $change) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');

    if ($change === 'joins') {
        MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    } else {
        $students[1]->update(['kelas_id' => KelasFactory::new()->create()->id]);
    }

    $component->call('simpan')->assertHasErrors(['kehadiran'])
        ->assertSee('Daftar siswa atau presensi telah berubah. Pilih ulang jadwal sebelum menyimpan.')
        ->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['joins', 'transfers']);

test('rejects a stale second editor after the first editor creates the session', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $first = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');
    $second = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');
    $first->call('simpan')->assertHasNoErrors();
    $attendance = PresensiMurid::query()->sole();

    $second->call('simpan')->assertHasErrors(['kehadiran']);

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 1);
    $this->assertDatabaseHas('presensi_murids', ['id' => $attendance->id, 'status' => 'Hadir']);
});

test('rejects a stale correction from a second tab after an existing session is revised', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->call('simpan')->assertHasNoErrors();
    $session = SesiMengajar::query()->sole();
    $attendance = PresensiMurid::query()->sole();
    $this->assertDatabaseHas('sesi_mengajars', ['id' => $session->id, 'revisi' => 1]);
    $tabA = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSet('sessionRevision', 1);
    $tabB = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSet('sessionRevision', 1);
    $tabB->set('kehadiran.p'.$attendance->id.'.catatan', 'Catatan tab lama');
    $tabA->set('kehadiran.p'.$attendance->id, ['status' => 'Sakit', 'catatan' => 'Koreksi tab pertama'])
        ->call('simpan')->assertHasNoErrors()->assertSet('sessionRevision', 2);
    $this->assertDatabaseHas('sesi_mengajars', ['id' => $session->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['kehadiran'])
        ->assertSee('Presensi telah diperbarui dari halaman lain. Pilih ulang jadwal sebelum menyimpan.')
        ->assertSet('sessionRevision', 1);

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 1);
    $this->assertDatabaseHas('sesi_mengajars', [
        'id' => $session->id, 'revisi' => 2, 'pencatat_id' => $schedule->guru->user_id,
        'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('presensi_murids', [
        'id' => $attendance->id, 'status' => 'Sakit', 'catatan' => 'Koreksi tab pertama',
    ]);
    $this->assertDatabaseMissing('presensi_murids', ['catatan' => 'Catatan tab lama']);
});

test('rejects invalid updates atomically without changing any saved attendance', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->call('simpan')->assertHasNoErrors();
    $attendance = PresensiMurid::query()->orderBy('id')->get();

    $component->set('kehadiran.p'.$attendance[0]->id.'.status', 'Izin')
        ->set('kehadiran.p'.$attendance[1]->id.'.status', 'Tidak valid')
        ->call('simpan')->assertHasErrors(['kehadiran.p'.$attendance[1]->id.'.status' => 'in']);

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 2);

    foreach ($attendance as $record) {
        $this->assertDatabaseHas('presensi_murids', ['id' => $record->id, 'status' => 'Hadir']);
    }
});

test('returns 404 for another teachers schedule even when they teach the same class', function (bool $sameClass) {
    $own = JadwalPelajaranFactory::new()->create();
    $foreign = JadwalPelajaranFactory::new()->create($sameClass ? ['kelas_id' => $own->kelas_id] : []);
    $this->actingAs($own->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $foreign->id)->assertNotFound();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['another class' => [false], 'same class' => [true]]);

test('returns 404 for an unknown schedule', function () {
    $this->actingAs(GuruFactory::new()->create()->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', 999999)->assertNotFound();
});

test('returns 404 for the teachers schedule stored under a different Indonesian day', function () {
    $schedule = JadwalPelajaranFactory::new()->create(['hari' => 'Selasa']);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->assertNotFound();
});

test('does not allow clients to replace the locked schedule id', function () {
    $own = JadwalPelajaranFactory::new()->create();
    $foreign = JadwalPelajaranFactory::new()->create();
    $this->actingAs($own->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $own->id);

    expect(fn () => $component->set('jadwalId', $foreign->id))->toThrow(CannotUpdateLockedPropertyException::class);

    $this->assertDatabaseCount('sesi_mengajars', 0);
});

test('does not allow clients to replace the locked session revision', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $session = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSet('sessionRevision', 1);

    expect(fn () => $component->set('sessionRevision', 2))->toThrow(CannotUpdateLockedPropertyException::class);

    $this->assertDatabaseHas('sesi_mengajars', ['id' => $session->id, 'revisi' => 1]);
});

test('does not expose schedule and session query helpers as Livewire actions', function (string $method) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class);

    expect(fn () => $component->call($method, $schedule->id))->toThrow(MethodNotFoundException::class);
})->with(['schedulesForDate', 'sessionFor']);

test('clears selection attendance and validation when the date changes', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('simpan')->assertHasErrors();

    $component->set('tanggal', '2026-10-11')
        ->assertHasNoErrors()->assertSet('jadwalId', null)->assertSet('kehadiran', [])
        ->assertSee('Mode riwayat.')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('rejects invalid dates and clears the selected roster', function (string $date, string $rule) {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->set('tanggal', $date)->assertHasErrors(['tanggal' => $rule])
        ->assertSet('jadwalId', null)->assertSet('kehadiran', []);
})->with([
    'future' => ['2026-10-20', 'before_or_equal'],
    'malformed' => ['19/10/2026', 'date_format'],
    'empty' => ['', 'required'],
]);

test('blocks all attendance actions outside the edit window', function (string $date, string $action) {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->set('tanggal', $date)
        ->call($action, $schedule->id)->assertHasErrors(['tanggal'])->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['past' => ['2026-10-11'], 'future' => ['2026-10-20']])
    ->with(['pilihJadwal', 'tandaiSemuaHadir', 'simpan']);

test('checks the date on save even when the date update hook is bypassed', function (string $date) {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');
    $component->instance()->tanggal = $date;

    expect(fn () => $component->instance()->simpan())->toThrow(ValidationException::class);

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['past' => ['2026-10-11'], 'future' => ['2026-10-20']]);

test('shows only the teachers history for the selected date as read only', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $own = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18', 'nama_mapel' => 'Riwayat Milik Sendiri',
    ]);
    PresensiMuridFactory::new()->create(['sesi_mengajar_id' => $own->id, 'nama_murid' => 'Siswa Riwayat Sendiri', 'catatan' => 'Catatan sendiri']);
    $foreignSchedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $foreign = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $foreignSchedule->id, 'tanggal' => '2026-10-18',
        'nama_guru' => $own->nama_guru, 'nama_mapel' => 'Riwayat Guru Lain',
    ]);
    PresensiMuridFactory::new()->create(['sesi_mengajar_id' => $foreign->id, 'nama_murid' => 'Siswa Riwayat Rahasia', 'catatan' => 'Catatan rahasia']);
    SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-17', 'nama_mapel' => 'Riwayat Tanggal Lain']);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-18')
        ->assertHasNoErrors()->assertSee('Riwayat Milik Sendiri')
        ->call('lihatPresensi', $own->id)
        ->assertSee('Siswa Riwayat Sendiri')->assertSee('Catatan sendiri')
        ->assertDontSee('Riwayat Guru Lain')->assertDontSee('Siswa Riwayat Rahasia')->assertDontSee('Catatan rahasia')
        ->assertDontSee('Riwayat Tanggal Lain')->assertDontSeeHtml('wire:submit="simpan"')
        ->assertDontSeeHtml('wire:click="tandaiSemuaHadir"')
        ->call('simpan')->assertHasErrors(['jadwalId']);

    $this->assertDatabaseCount('sesi_mengajars', 3);
    $this->assertDatabaseCount('presensi_murids', 2);
    $this->assertDatabaseHas('presensi_murids', ['sesi_mengajar_id' => $own->id, 'status' => 'Hadir', 'catatan' => 'Catatan sendiri']);
});

test('allows filling attendance for a past date inside the seven day window', function () {
    $schedule = JadwalPelajaranFactory::new()->create(['hari' => 'Minggu']);
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id, 'nama' => 'Siswa Susulan']);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)
        ->set('tanggal', '2026-10-18')
        ->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Presensi tersimpan');

    $session = SesiMengajar::query()->sole();

    expect($session->tanggal->toDateString())->toBe('2026-10-18');
    $this->assertDatabaseHas('presensi_murids', ['sesi_mengajar_id' => $session->id, 'status' => 'Hadir']);
});

test('rejects filling attendance older than seven days', function () {
    $schedule = JadwalPelajaranFactory::new()->create(['hari' => 'Minggu']);
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)
        ->set('tanggal', '2026-10-11')
        ->call('pilihJadwal', $schedule->id)
        ->assertHasErrors(['tanggal'])
        ->assertSee('maksimal 7 hari ke belakang');

    $this->assertDatabaseCount('sesi_mengajars', 0);
});

test('keeps a saved session owned by the original teacher after schedule reassignment', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $session = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id]);
    $attendance = PresensiMuridFactory::new()->create(['sesi_mengajar_id' => $session->id, 'murid_id' => $student->id, 'catatan' => 'Rahasia guru semula']);
    $newTeacher = GuruFactory::new()->create();
    $schedule->update(['guru_id' => $newTeacher->id]);
    $this->actingAs($newTeacher->user);
    $component = Livewire::test(PresensiSiswa::class)->assertDontSee('Rahasia guru semula');

    $component->call('pilihJadwal', $schedule->id)->assertForbidden();

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseHas('presensi_murids', ['id' => $attendance->id, 'status' => 'Hadir', 'catatan' => 'Rahasia guru semula']);
});

test('does not transfer recorded history or editing rights when a teacher profile is relinked', function (string $action) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $teacher = $schedule->guru;
    $originalUser = $teacher->user;
    $newUser = User::factory()->create();
    $this->assertDatabaseMissing('gurus', ['user_id' => $newUser->id]);
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id, 'nama' => 'Siswa Pencatat Semula']);
    $this->actingAs($originalUser);
    $originalTab = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->set('kehadiran.m'.$student->id, ['status' => 'Izin', 'catatan' => 'Catatan pribadi pencatat semula'])
        ->call('simpan')->assertHasNoErrors();
    $session = SesiMengajar::query()->sole();
    $attendance = PresensiMurid::query()->sole();
    $teacher->update(['user_id' => $newUser->id]);
    $this->actingAs($newUser);
    $this->get(route('filament.admin.pages.presensi-siswa'))->assertOk();
    Livewire::test(PresensiSiswa::class)->assertOk()
        ->assertSeeHtml('wire:click="pilihJadwal('.$schedule->id.')"')
        ->assertDontSee('Siswa Pencatat Semula')
        ->assertDontSee('Catatan pribadi pencatat semula');

    $originalTab->call($action, $schedule->id)->assertForbidden();

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 1);
    $this->assertDatabaseHas('sesi_mengajars', [
        'id' => $session->id, 'guru_id' => $teacher->id, 'pencatat_id' => $originalUser->id, 'revisi' => 1,
    ]);
    $this->assertDatabaseHas('presensi_murids', [
        'id' => $attendance->id, 'status' => 'Izin', 'catatan' => 'Catatan pribadi pencatat semula',
    ]);
    $this->actingAs($originalUser);
    $this->get(route('filament.admin.pages.presensi-siswa'))->assertForbidden();
    Livewire::test(PresensiSiswa::class)->assertForbidden();
})->with(['pilihJadwal', 'tandaiSemuaHadir', 'simpan']);

test('rechecks session ownership when a selected schedule is reassigned before submission', function (string $action) {
    $schedule = JadwalPelajaranFactory::new()->create();
    MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');
    $schedule->update(['guru_id' => GuruFactory::new()->create()->id]);

    $component->call($action)->assertNotFound();

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
})->with(['tandaiSemuaHadir', 'simpan']);

test('forbids helpers and saves when a different teacher already owns the selected session', function (string $action) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)->call('tandaiSemuaHadir');
    $originalTeacher = GuruFactory::new()->create();
    $session = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'guru_id' => $originalTeacher->id]);
    $attendance = PresensiMuridFactory::new()->create([
        'sesi_mengajar_id' => $session->id, 'murid_id' => $student->id, 'status' => 'Izin', 'catatan' => 'Catatan pemilik sesi',
    ]);

    $component->call($action)->assertForbidden();

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 1);
    $this->assertDatabaseHas('sesi_mengajars', ['id' => $session->id, 'guru_id' => $originalTeacher->id]);
    $this->assertDatabaseHas('presensi_murids', ['id' => $attendance->id, 'status' => 'Izin', 'catatan' => 'Catatan pemilik sesi']);
})->with(['tandaiSemuaHadir', 'simpan']);

test('retains history for the original teacher rather than the reassigned schedule teacher', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $originalTeacher = $schedule->guru;
    $session = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18']);
    PresensiMuridFactory::new()->create(['sesi_mengajar_id' => $session->id, 'nama_murid' => 'Siswa Guru Semula', 'catatan' => 'Riwayat guru semula']);
    $newTeacher = GuruFactory::new()->create();
    $schedule->update(['guru_id' => $newTeacher->id]);
    $this->actingAs($originalTeacher->user);

    Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-18')
        ->call('lihatPresensi', $session->id)
        ->assertSee('Siswa Guru Semula')->assertSee('Riwayat guru semula');

    $this->actingAs($newTeacher->user);
    Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-18')
        ->assertDontSee('Siswa Guru Semula')->assertDontSee('Riwayat guru semula');
});

test('uses the saved roster after transfers deletions and new class members', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->sequence(
        ['nama' => 'Siswa Dipindah', 'nis' => 'SNAP-101'],
        ['nama' => 'Siswa Dihapus', 'nis' => 'SNAP-102'],
    )->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);
    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->call('simpan')->assertHasNoErrors();
    $attendance = PresensiMurid::query()->get()->keyBy('murid_id');
    $transferredAttendance = $attendance[$students[0]->id];
    $deletedAttendance = $attendance[$students[1]->id];
    $students[0]->update(['kelas_id' => KelasFactory::new()->create()->id, 'nama' => 'Nama Pengganti', 'nis' => 'NIS-BARU']);
    $students[1]->delete();
    $newStudent = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id, 'nama' => 'Siswa Baru']);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSee('Siswa Dipindah')->assertSee('Siswa Dihapus')->assertSee('SNAP-101')->assertSee('SNAP-102')
        ->assertDontSee('Nama Pengganti')->assertDontSee('Siswa Baru')
        ->assertSet('kehadiran', [
            'p'.$transferredAttendance->id => ['status' => 'Hadir', 'catatan' => ''],
            'p'.$deletedAttendance->id => ['status' => 'Hadir', 'catatan' => ''],
        ])
        ->call('tandaiSemuaHadir')
        ->set('kehadiran.p'.$deletedAttendance->id.'.status', 'Sakit')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 2);
    $this->assertDatabaseHas('presensi_murids', ['id' => $transferredAttendance->id, 'nama_murid' => 'Siswa Dipindah', 'nis' => 'SNAP-101']);
    $this->assertDatabaseHas('presensi_murids', ['id' => $deletedAttendance->id, 'murid_id' => null, 'nama_murid' => 'Siswa Dihapus', 'nis' => 'SNAP-102', 'status' => 'Sakit']);
    $this->assertDatabaseMissing('presensi_murids', ['murid_id' => $newStudent->id]);
});

test('keeps historical snapshots visible after renaming and deleting the source schedule', function () {
    $teacher = GuruFactory::new()->create(['user_id' => User::factory()->create(['name' => 'Nama Guru Asli'])->id]);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Nama Kelas Asli']);
    $schedule = JadwalPelajaranFactory::new()->create(['guru_id' => $teacher->id, 'kelas_id' => $class->id]);
    $schedule->mataPelajaran->update(['nama_mapel' => 'Nama Mapel Asli']);
    MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => 'Siswa Snapshot']);
    $this->actingAs($teacher->user);
    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->call('simpan')->assertHasNoErrors();
    $session = SesiMengajar::query()->sole();
    $teacher->user->update(['name' => 'Nama Guru Baru']);
    $class->update(['nama_kelas' => 'Nama Kelas Baru']);
    $schedule->mataPelajaran->update(['nama_mapel' => 'Nama Mapel Baru']);
    $schedule->update(['jam_mulai' => '12:00:00', 'jam_selesai' => '13:00:00']);
    $schedule->delete();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'Asia/Jakarta'));

    Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-19')
        ->assertHasNoErrors()->assertSee('Nama Kelas Asli')->assertSee('Nama Mapel Asli')
        ->call('lihatPresensi', $session->id)
        ->assertSee('Siswa Snapshot')
        ->assertSee('07:00 - 09:00')->assertSee('19/10/2026 10:00')
        ->assertDontSee('Nama Kelas Baru')->assertDontSee('Nama Mapel Baru')
        ->assertDontSeeHtml('wire:submit="simpan"');

    $this->assertDatabaseHas('sesi_mengajars', [
        'id' => $session->id, 'jadwal_pelajaran_id' => null, 'guru_id' => $teacher->id,
        'nama_guru' => 'Nama Guru Asli', 'nama_kelas' => 'Nama Kelas Asli', 'nama_mapel' => 'Nama Mapel Asli',
        'jam_mulai' => '07:00:00', 'jam_selesai' => '09:00:00', 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
});

test('renders the schedule table and opens the student attendance modal', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)
        ->assertSee('Periode berjalan: semester Ganjil tahun ajaran 2026/2027.')
        ->assertSee('Waktu')->assertSee('Mata pelajaran')->assertSee('Hadir')->assertSee('Absen')
        ->call('pilihJadwal', $schedule->id)
        ->assertDispatched('open-modal')
        ->assertSee('Presensi siswa')
        ->assertSee($student->nama)
        ->assertSeeHtml('wire:submit="simpan"')
        ->assertSeeHtml('wire:click="tutupPresensi"');
});

test('closes the modal after saving and shows real attendance counts in the table', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $students = MuridFactory::new()->count(2)->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('tandaiSemuaHadir')->call('simpan')
        ->assertHasNoErrors()->assertDispatched('close-modal')
        ->assertSee('2/2')->assertSee('100,00%')
        ->assertSee('Absen');

    $this->assertDatabaseCount('sesi_mengajars', 1);
    $this->assertDatabaseCount('presensi_murids', 2);
});

test('keeps the modal open when validation fails', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->call('simpan')->assertHasErrors(['kehadiran.m'.$student->id.'.status'])
        ->assertNotDispatched('close-modal')->assertNotNotified();

    $this->assertDatabaseCount('sesi_mengajars', 0);
});

test('closes without saving and clears the selected attendance', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $student = MuridFactory::new()->create(['kelas_id' => $schedule->kelas_id]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->set('kehadiran.m'.$student->id.'.status', 'Hadir')
        ->call('tutupPresensi')
        ->assertDispatched('close-modal')
        ->assertSet('jadwalId', null)->assertSet('kehadiran', []);

    $this->assertDatabaseCount('sesi_mengajars', 0);
    $this->assertDatabaseCount('presensi_murids', 0);
});

test('limits fillable schedules to the active period while keeping older session history', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $schedule->mataPelajaran->update(['nama_mapel' => 'Mapel Periode Aktif']);
    $oldSchedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $schedule->guru_id, 'hari' => 'Senin',
        'semester' => 'Genap', 'tahun_ajaran' => '2025/2026',
    ]);
    $oldSchedule->mataPelajaran->update(['nama_mapel' => 'Mapel Periode Lama']);
    $oldSession = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $oldSchedule->id, 'tanggal' => '2026-10-18', 'nama_mapel' => 'Sesi Periode Lama',
    ]);
    PresensiMuridFactory::new()->create([
        'sesi_mengajar_id' => $oldSession->id, 'nama_murid' => 'Siswa Periode Lama',
    ]);
    $this->actingAs($schedule->guru->user);

    Livewire::test(PresensiSiswa::class)
        ->assertSee('Mapel Periode Aktif')
        ->assertDontSee('Mapel Periode Lama')
        ->assertDontSee('Sesi Periode Lama')
        ->assertDontSee('Siswa Periode Lama')
        ->set('tanggal', '2026-10-18')
        ->call('lihatPresensi', $oldSession->id)
        ->assertSee('Sesi Periode Lama')->assertSee('Siswa Periode Lama');

    $this->assertDatabaseHas('sesi_mengajars', [
        'id' => $oldSession->id, 'semester' => 'Genap', 'tahun_ajaran' => '2025/2026',
    ]);
});

test('does not allow clients to replace the locked history id', function () {
    $schedule = JadwalPelajaranFactory::new()->create();
    $session = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18']);
    $this->actingAs($schedule->guru->user);
    $component = Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-18')->call('lihatPresensi', $session->id);

    expect(fn () => $component->set('riwayatId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('returns 404 for history that is not owned by the teacher on the selected date', function (string $case) {
    $schedule = JadwalPelajaranFactory::new()->create();
    $own = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18']);
    $otherDate = SesiMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-17']);
    $foreign = SesiMengajarFactory::new()->create(['tanggal' => '2026-10-18']);
    $this->actingAs($schedule->guru->user);
    $id = match ($case) {
        'other date' => $otherDate->id,
        'other teacher' => $foreign->id,
        'unknown' => 999999,
    };

    Livewire::test(PresensiSiswa::class)->set('tanggal', '2026-10-18')
        ->call('lihatPresensi', $id)->assertNotFound();

    expect($own->exists)->toBeTrue();
})->with(['other date', 'other teacher', 'unknown']);

test('escapes student class subject names and notes in the editor and saved history', function () {
    $className = '<script>kelas()</script>';
    $subjectName = '<script>mapel()</script>';
    $studentName = '<script>siswa()</script>';
    $note = '<img src=x onerror=alert(1)>';
    $class = KelasFactory::new()->create(['nama_kelas' => $className]);
    $schedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $class->id]);
    $schedule->mataPelajaran->update(['nama_mapel' => $subjectName]);
    $student = MuridFactory::new()->create(['kelas_id' => $class->id, 'nama' => $studentName]);
    $this->actingAs($schedule->guru->user);

    $component = Livewire::test(PresensiSiswa::class)->call('pilihJadwal', $schedule->id)
        ->assertSee($className)->assertSee($subjectName)->assertSee($studentName)
        ->assertDontSeeHtml($className)->assertDontSeeHtml($subjectName)->assertDontSeeHtml($studentName)
        ->set('kehadiran.m'.$student->id, ['status' => 'Izin', 'catatan' => $note])
        ->call('simpan')->assertHasNoErrors();
    $session = SesiMengajar::query()->sole();

    $component->call('lihatPresensi', $session->id)
        ->assertSee($className)->assertSee($subjectName)->assertSee($studentName)->assertSee($note)
        ->assertDontSeeHtml($className)->assertDontSeeHtml($subjectName)
        ->assertDontSeeHtml($studentName)->assertDontSeeHtml($note, false);
    $this->assertDatabaseHas('presensi_murids', ['murid_id' => $student->id, 'nama_murid' => $studentName, 'catatan' => $note]);
});
