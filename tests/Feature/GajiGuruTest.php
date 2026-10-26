<?php

use App\Filament\Pages\GajiGuru;
use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\Jabatan;
use App\Models\JadwalPeminatan;
use App\Models\JadwalRapat;
use App\Models\JenisPeminatan;
use App\Models\User;
use Database\Factories\AbsenPiketFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Database\Factories\PresensiMuridFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function gajiGuruAdmin(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

/**
 * Data contoh: 3 sesi Oktober, 1 sesi September, 2 rapat Oktober, 1 rapat September,
 * 2 hari piket Oktober (salah satunya dua kelas), 1 piket September, 2 jenis peminatan,
 * dan dua jabatan.
 */
function gajiGuruScenario(): Guru
{
    $guru = GuruFactory::new()->create(['jarak_rumah' => 10]);
    $guru->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);

    $student = MuridFactory::new()->create();

    foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-09-30'] as $date) {
        $session = SesiMengajarFactory::new()->create([
            'jadwal_pelajaran_id' => $schedule->id,
            'tanggal' => $date,
        ]);

        PresensiMuridFactory::new()->create([
            'sesi_mengajar_id' => $session->id,
            'murid_id' => $student->id,
        ]);
    }

    // Sesi tanpa presensi menambah jam mengajar, tetapi tidak dihitung sebagai hari hadir.
    SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'tanggal' => '2026-10-08',
    ]);

    $positions = [
        Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 500000]),
        Jabatan::create(['nama_jabatan' => 'Bendahara', 'gaji' => 250000]),
    ];
    $guru->jabatans()->attach(array_map(fn (Jabatan $jabatan): int => $jabatan->id, $positions));

    foreach (['2026-10-08', '2026-10-15', '2026-09-10'] as $date) {
        $meeting = JadwalRapat::create([
            'tanggal' => $date,
            'hari' => 'Kamis',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:00:00',
            'agenda' => 'Rapat bulanan',
        ]);

        AbsenRapat::create([
            'jadwal_rapat_id' => $meeting->id,
            'guru_id' => $guru->id,
            'user_id' => $guru->user_id,
            'nama_guru' => 'Ust. Ahmad Fauzi',
            'agenda' => 'Rapat bulanan',
            'tanggal' => $date,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:00:00',
            'foto' => 'absen-rapat/contoh.jpg',
            'dicatat_pada' => now(),
        ]);
    }

    $classA = KelasFactory::new()->create();
    $classB = KelasFactory::new()->create();

    AbsenPiketFactory::new()->create(['guru_id' => $guru->id, 'kelas_id' => $classA->id, 'tanggal' => '2026-10-09']);
    AbsenPiketFactory::new()->create(['guru_id' => $guru->id, 'kelas_id' => $classB->id, 'tanggal' => '2026-10-09']);
    AbsenPiketFactory::new()->create(['guru_id' => $guru->id, 'kelas_id' => $classA->id, 'tanggal' => '2026-10-16']);
    AbsenPiketFactory::new()->create(['guru_id' => $guru->id, 'kelas_id' => $classB->id, 'tanggal' => '2026-09-25']);

    $tahfizh = JenisPeminatan::create(['nama_peminatan' => "Tahfizh Al-Qur'an", 'nominal' => 150000]);
    $arab = JenisPeminatan::create(['nama_peminatan' => 'Bahasa Arab', 'nominal' => 100000]);

    JadwalPeminatan::create([
        'hari' => 'Kamis',
        'guru_id' => $guru->id,
        'jenis_peminatan_id' => $tahfizh->id,
        'jam_mulai' => '15:00:00',
        'jam_selesai' => '16:30:00',
    ]);

    JadwalPeminatan::create([
        'hari' => 'Kamis',
        'guru_id' => $guru->id,
        'jenis_peminatan_id' => $arab->id,
        'jam_mulai' => '16:30:00',
        'jam_selesai' => '18:00:00',
    ]);

    return $guru;
}

test('the salary page exposes the expected navigation metadata', function () {
    expect(GajiGuru::getNavigationGroup())->toBe('Keuangan')
        ->and(GajiGuru::getNavigationLabel())->toBe('Gaji Guru')
        ->and(GajiGuru::getSlug())->toBe('gaji-guru')
        ->and(GajiGuru::shouldRegisterNavigation())->toBeTrue();
});

test('guests are redirected from the salary page', function () {
    $this->get(route('filament.admin.pages.gaji-guru'))
        ->assertRedirectToRoute('filament.admin.auth.login');
});

test('teachers cannot open the salary page', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    $this->get(route('filament.admin.pages.gaji-guru'))->assertForbidden();
    Livewire::test(GajiGuru::class)->assertForbidden();
});

test('an admin can open the salary page', function () {
    $this->actingAs(gajiGuruAdmin());

    $this->get(route('filament.admin.pages.gaji-guru'))->assertOk();
});

test('the salary page computes each component for the selected month', function () {
    $this->actingAs(gajiGuruAdmin());
    $guru = gajiGuruScenario();

    $component = Livewire::test(GajiGuru::class)->set('bulan', 10)->set('tahun', 2026);
    $rekap = $component->instance()->rekap();
    $row = collect($rekap['rows'])->firstWhere('guru_id', $guru->id);

    expect($rekap['periode'])->toBe('Oktober 2026')
        ->and($row['jam'])->toBe(6.0)
        ->and($row['hari_hadir'])->toBe(3)
        ->and($row['mengajar'])->toBe(60000)
        ->and($row['transport'])->toBe(30000)
        ->and($row['jabatan'])->toBe(750000)
        ->and($row['rapat'])->toBe(40000)
        ->and($row['peminatan'])->toBe(250000)
        ->and($row['piket'])->toBe(20000)
        ->and($row['total'])->toBe(1150000)
        ->and($rekap['totals']['total'])->toBe(1150000);

    $component->assertSee('Ust. Ahmad Fauzi')
        ->assertSee('Rp 1.150.000')
        ->assertSee('Jabatan')
        ->assertSee('Transport');
});

test('a day counts once for transport when at least one session has attendance', function () {
    $this->actingAs(gajiGuruAdmin());

    $guru = GuruFactory::new()->create(['jarak_rumah' => 5]);
    $guru->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $firstSchedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);
    $secondSchedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'jam_mulai' => '08:30:00',
        'jam_selesai' => '10:00:00',
    ]);

    $student = MuridFactory::new()->create();

    $filledSession = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $firstSchedule->id,
        'tanggal' => '2026-10-05',
    ]);

    PresensiMuridFactory::new()->create([
        'sesi_mengajar_id' => $filledSession->id,
        'murid_id' => $student->id,
    ]);

    // Sesi kedua pada tanggal yang sama belum diisi presensi.
    SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $secondSchedule->id,
        'tanggal' => '2026-10-05',
    ]);

    $component = Livewire::test(GajiGuru::class)->set('bulan', 10)->set('tahun', 2026);
    $row = collect($component->instance()->rekap()['rows'])->firstWhere('guru_id', $guru->id);

    expect($row['jam'])->toBe(3.0)
        ->and($row['hari_hadir'])->toBe(1)
        ->and($row['mengajar'])->toBe(30000)
        ->and($row['transport'])->toBe(5000);
});

test('the salary page excludes records from other months', function () {
    $this->actingAs(gajiGuruAdmin());
    $guru = gajiGuruScenario();

    $component = Livewire::test(GajiGuru::class)->set('bulan', 9)->set('tahun', 2026);
    $rekap = $component->instance()->rekap();
    $row = collect($rekap['rows'])->firstWhere('guru_id', $guru->id);

    expect($rekap['periode'])->toBe('September 2026')
        ->and($row['jam'])->toBe(1.5)
        ->and($row['hari_hadir'])->toBe(1)
        ->and($row['mengajar'])->toBe(15000)
        ->and($row['transport'])->toBe(10000)
        ->and($row['rapat'])->toBe(20000)
        ->and($row['piket'])->toBe(10000)
        ->and($row['total'])->toBe(1055000);
});

test('the salary page clamps an out of range month and year', function () {
    $this->actingAs(gajiGuruAdmin());

    $component = Livewire::test(GajiGuru::class)->set('bulan', 15)->set('tahun', 1990);

    expect($component->instance()->bulan)->toBe(12)
        ->and($component->instance()->tahun)->toBe(2000);
});
