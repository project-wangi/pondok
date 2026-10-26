<?php

use App\Filament\Pages\SlipGaji;
use App\Models\AbsenPeminatan;
use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\Jabatan;
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

function slipGajiAdmin(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

/**
 * Guru dengan jarak 10 km, dua sesi berpresensi di Oktober, satu sesi September,
 * satu sesi Oktober tanpa presensi, dua rapat, dua hari piket, satu jabatan,
 * dan satu peminatan.
 */
function slipGajiScenario(): Guru
{
    $guru = GuruFactory::new()->create(['jarak_rumah' => 10]);
    $guru->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);

    $student = MuridFactory::new()->create();

    foreach (['2026-10-05', '2026-10-06', '2026-09-30'] as $date) {
        $session = SesiMengajarFactory::new()->create([
            'jadwal_pelajaran_id' => $schedule->id,
            'tanggal' => $date,
        ]);

        PresensiMuridFactory::new()->create([
            'sesi_mengajar_id' => $session->id,
            'murid_id' => $student->id,
        ]);
    }

    // Sesi tanpa presensi tidak dihitung pada slip.
    SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'tanggal' => '2026-10-07',
    ]);

    $position = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 500000]);
    $guru->jabatans()->attach($position->id);

    foreach (['2026-10-08', '2026-10-15'] as $date) {
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

    $specialization = JenisPeminatan::create(['nama_peminatan' => "Tahfizh Al-Qur'an", 'nominal' => 150000]);

    foreach (['2026-10-01', '2026-10-08', '2026-09-24'] as $tanggal) {
        AbsenPeminatan::create([
            'jenis_peminatan_id' => $specialization->id,
            'guru_id' => $guru->id,
            'pencatat_id' => $guru->user_id,
            'revisi' => 1,
            'tanggal' => $tanggal,
            'jam_mulai' => '15:30:00',
            'jam_selesai' => '17:00:00',
            'nama_guru' => 'Ust. Ahmad Fauzi',
            'nama_peminatan' => $specialization->nama_peminatan,
            'dicatat_pada' => now(),
        ]);
    }

    return $guru;
}

test('the salary slip page exposes the expected navigation metadata', function () {
    expect(SlipGaji::getNavigationGroup())->toBe('Keuangan')
        ->and(SlipGaji::getNavigationLabel())->toBe('Slip Gaji')
        ->and(SlipGaji::getSlug())->toBe('slip-gaji')
        ->and(SlipGaji::shouldRegisterNavigation())->toBeTrue();
});

test('guests are redirected from the salary slip page', function () {
    $this->get(route('filament.admin.pages.slip-gaji'))
        ->assertRedirectToRoute('filament.admin.auth.login');
});

test('users without a teacher profile cannot open the salary slip page', function () {
    $this->actingAs(slipGajiAdmin());

    $this->get(route('filament.admin.pages.slip-gaji'))->assertForbidden();
    Livewire::test(SlipGaji::class)->assertForbidden();
});

test('a teacher sees their own salary slip for the selected month', function () {
    $guru = slipGajiScenario();
    $other = GuruFactory::new()->create();
    $other->user->update(['name' => 'Ustzh. Siti Aminah']);

    $this->actingAs($guru->user);

    $component = Livewire::test(SlipGaji::class)->set('bulan', 10)->set('tahun', 2026);
    $rincian = $component->instance()->rincian();

    expect($rincian['periode'])->toBe('Oktober 2026')
        ->and($rincian['jam'])->toBe(3.0)
        ->and($rincian['hari_hadir'])->toBe(2)
        ->and($rincian['mengajar'])->toBe(30000)
        ->and($rincian['transport'])->toBe(20000)
        ->and($rincian['jabatan'])->toBe(500000)
        ->and($rincian['rapat'])->toBe(40000)
        ->and($rincian['peminatan'])->toBe(300000)
        ->and($rincian['piket'])->toBe(20000)
        ->and($rincian['total'])->toBe(910000);

    $component->assertSee('Ust. Ahmad Fauzi')
        ->assertDontSee('Ustzh. Siti Aminah')
        ->assertSee('Rp 910.000')
        ->assertSee('3 jam × Rp10.000')
        ->assertSee('2 hari hadir')
        ->assertSee('Tahfizh Al-Qur\'an');
});

test('the salary slip follows the selected month', function () {
    $guru = slipGajiScenario();
    $this->actingAs($guru->user);

    $component = Livewire::test(SlipGaji::class)->set('bulan', 9)->set('tahun', 2026);
    $rincian = $component->instance()->rincian();

    expect($rincian['periode'])->toBe('September 2026')
        ->and($rincian['jam'])->toBe(1.5)
        ->and($rincian['hari_hadir'])->toBe(1)
        ->and($rincian['mengajar'])->toBe(15000)
        ->and($rincian['transport'])->toBe(10000)
        ->and($rincian['rapat'])->toBe(0)
        ->and($rincian['piket'])->toBe(0)
        ->and($rincian['jabatan'])->toBe(500000)
        ->and($rincian['peminatan'])->toBe(150000)
        ->and($rincian['total'])->toBe(675000);

    $component->assertSee('Rp 675.000');
});
