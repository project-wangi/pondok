<?php

use App\Filament\Pages\PresensiSiswa;
use App\Models\Guru;
use App\Models\JadwalPiket;
use App\Models\JadwalRapat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DonasiFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;

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
function dashboardTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

test('shows the teacher dashboard with real daily data', function () {
    $teacher = dashboardTeacher(['user_id' => User::factory()->create(['name' => 'Ustadz Ahmad'])->id]);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas VII']);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
        'mata_pelajaran' => $subject->id,
        'hari' => 'Senin',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '09:30:00',
    ]);
    SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'guru_id' => $teacher->id,
        'pencatat_id' => $teacher->user_id,
        'tanggal' => '2026-10-19',
    ]);
    JadwalPiket::query()->create([
        'hari' => 'Senin',
        'guru_id' => $teacher->id,
        'ket' => 'Piket gerbang',
    ]);
    $rapat = JadwalRapat::query()->create([
        'tanggal' => '2026-10-19',
        'hari' => 'Senin',
        'jam_mulai' => '13:00:00',
        'jam_selesai' => '14:00:00',
        'agenda' => 'Rapat kurikulum',
    ]);
    $rapat->gurus()->attach($teacher->id);
    DonasiFactory::new()->create([
        'guru_id' => $teacher->id,
        'tanggal' => '2026-10-19',
        'jumlah_anak' => 2,
    ]);
    $this->actingAs($teacher->user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Selamat pagi')
        ->assertSee('Ustadz Ahmad')
        ->assertSee('Matematika')
        ->assertSee('Kelas VII')
        ->assertSee('Presensi tercatat')
        ->assertSee('Piket gerbang')
        ->assertSee('Rapat kurikulum')
        ->assertSee('Rp 100.000')
        ->assertSeeHtml('href="'.e(PresensiSiswa::getUrl()).'"');
});

test('shows an honest empty state when the teacher has no schedule today', function () {
    $teacher = dashboardTeacher();
    $this->actingAs($teacher->user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Tidak ada jadwal mengajar hari ini.')
        ->assertSee('Data donasi belum diisi.');
});

test('shows only the teachers own schedule for today', function () {
    $teacher = dashboardTeacher();
    $ownSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $otherSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']);
    $tomorrowSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Hari Lain']);

    JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $ownSubject->id,
        'hari' => 'Senin',
    ]);
    JadwalPelajaranFactory::new()->create([
        'mata_pelajaran' => $otherSubject->id,
        'hari' => 'Senin',
    ]);
    JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $tomorrowSubject->id,
        'hari' => 'Selasa',
    ]);
    $this->actingAs($teacher->user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Tahfidz')
        ->assertDontSee('Mapel Guru Lain')
        ->assertDontSee('Mapel Hari Lain');
});

test('keeps the default dashboard for staff accounts', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('Jadwal mengajar hari ini');
});
