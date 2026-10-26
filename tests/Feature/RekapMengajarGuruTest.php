<?php

use App\Filament\Pages\RekapMengajarGuru;
use App\Models\Kelas;
use App\Models\User;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
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

function rekapMengajarAdmin(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

test('the teaching recap page exposes the expected navigation metadata', function () {
    expect(RekapMengajarGuru::getNavigationGroup())->toBe('Keuangan')
        ->and(RekapMengajarGuru::getNavigationLabel())->toBe('Rekap Mengajar Guru')
        ->and(RekapMengajarGuru::getSlug())->toBe('rekap-mengajar-guru')
        ->and(RekapMengajarGuru::shouldRegisterNavigation())->toBeTrue();
});

test('guests are redirected from the teaching recap page', function () {
    $this->get(route('filament.admin.pages.rekap-mengajar-guru'))
        ->assertRedirectToRoute('filament.admin.auth.login');
});

test('teachers cannot open the teaching recap page', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    $this->get(route('filament.admin.pages.rekap-mengajar-guru'))->assertForbidden();
    Livewire::test(RekapMengajarGuru::class)->assertForbidden();
});

test('an admin can open the teaching recap page', function () {
    $this->actingAs(rekapMengajarAdmin());

    $this->get(route('filament.admin.pages.rekap-mengajar-guru'))->assertOk();
});

test('the teaching recap counts hours per teacher and class for the selected month', function () {
    $this->actingAs(rekapMengajarAdmin());

    // Dibuat tidak berurutan untuk memastikan kolom tetap VII lalu VIII.
    $classEight = Kelas::create(['nama_kelas' => 'VIII']);
    $classSeven = Kelas::create(['nama_kelas' => 'VII']);

    $teacher = GuruFactory::new()->create();
    $teacher->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $sevenSchedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classSeven->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);

    JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $classEight->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
    ]);

    $student = MuridFactory::new()->create(['kelas_id' => $classSeven->id]);

    foreach (['2026-10-05', '2026-10-06', '2026-09-30'] as $date) {
        $session = SesiMengajarFactory::new()->create([
            'jadwal_pelajaran_id' => $sevenSchedule->id,
            'tanggal' => $date,
        ]);

        PresensiMuridFactory::new()->create([
            'sesi_mengajar_id' => $session->id,
            'murid_id' => $student->id,
        ]);
    }

    // Sesi tanpa presensi siswa tidak dihitung sebagai JP.
    SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $sevenSchedule->id,
        'tanggal' => '2026-10-07',
    ]);

    $otherTeacher = GuruFactory::new()->create();
    $otherTeacher->user->update(['name' => 'Ustzh. Siti Aminah']);

    $component = Livewire::test(RekapMengajarGuru::class)->set('bulan', 10)->set('tahun', 2026);
    $rekap = $component->instance()->rekap();

    expect($rekap['periode'])->toBe('Oktober 2026')
        ->and(collect($rekap['columns'])->pluck('nama')->all())->toBe(['VII', 'VIII']);

    $rows = collect($rekap['rows'])->keyBy('guru_id');

    expect($rows[$teacher->id]['cells'][$classSeven->id])->toBe(['scheduled' => true, 'hours' => 3.0])
        ->and($rows[$teacher->id]['cells'][$classEight->id])->toBe(['scheduled' => true, 'hours' => 0.0])
        ->and($rows[$teacher->id]['total'])->toBe(3.0)
        ->and($rows[$otherTeacher->id]['cells'][$classSeven->id])->toBe(['scheduled' => false, 'hours' => 0.0])
        ->and($rows[$otherTeacher->id]['cells'][$classEight->id])->toBe(['scheduled' => false, 'hours' => 0.0])
        ->and($rows[$otherTeacher->id]['total'])->toBe(0.0)
        ->and($rekap['totals']['kelas'][$classSeven->id])->toBe(3.0)
        ->and($rekap['totals']['kelas'][$classEight->id])->toBe(0.0)
        ->and($rekap['totals']['total'])->toBe(3.0);

    $component->assertSee('Ust. Ahmad Fauzi')
        ->assertSee('Jumlah Kehadiran (JP)')
        ->assertSee('Rekap Mengajar Guru');
});

test('the teaching recap only uses schedules from the selected academic period', function () {
    $this->actingAs(rekapMengajarAdmin());

    $class = Kelas::create(['nama_kelas' => 'IX']);
    $teacher = GuruFactory::new()->create();
    $teacher->user->update(['name' => 'Ust. Ahmad Fauzi']);

    JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
        'semester' => 'Genap',
        'tahun_ajaran' => '2025/2026',
    ]);

    $component = Livewire::test(RekapMengajarGuru::class)->set('bulan', 10)->set('tahun', 2026);
    $rekap = $component->instance()->rekap();
    $row = collect($rekap['rows'])->firstWhere('guru_id', $teacher->id);

    expect($row['cells'][$class->id])->toBe(['scheduled' => false, 'hours' => 0.0]);
});
