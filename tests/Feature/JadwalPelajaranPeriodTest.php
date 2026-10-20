<?php

use App\Filament\Resources\JadwalPelajarans\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\JadwalPelajarans\Pages\ListJadwalPelajarans;
use App\Models\JadwalPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function jadwalPeriodUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/** @return array<string, mixed> */
function jadwalPeriodFormData(): array
{
    return [
        'kelas_id' => KelasFactory::new()->create()->id,
        'guru_id' => GuruFactory::new()->create()->id,
        'mata_pelajaran' => MataPelajaranFactory::new()->create()->id,
        'hari' => 'Senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '09:00',
        'semester' => 'Genap',
        'tahun_ajaran' => '2025/2026',
    ];
}

test('resolves the running academic period across the semester boundary', function (string $date, string $semester, string $year) {
    $period = JadwalPelajaran::activePeriod(CarbonImmutable::parse($date, 'Asia/Jakarta'));

    expect($period)->toBe(['semester' => $semester, 'tahun_ajaran' => $year]);
})->with([
    'first day of odd semester' => ['2026-07-01', 'Ganjil', '2026/2027'],
    'last day of even semester' => ['2026-06-30', 'Genap', '2025/2026'],
    'start of calendar year' => ['2026-01-01', 'Genap', '2025/2026'],
]);

test('stores the selected academic period with a new schedule', function () {
    $user = jadwalPeriodUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran');
    $this->actingAs($user);
    expect($user->can('create', JadwalPelajaran::class))->toBeTrue();

    Livewire::test(CreateJadwalPelajaran::class)
        ->assertOk()
        ->fillForm(jadwalPeriodFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $schedule = JadwalPelajaran::query()->sole();
    expect($schedule->semester)->toBe('Genap')
        ->and($schedule->tahun_ajaran)->toBe('2025/2026');
});

test('rejects an invalid academic year', function (array $data) {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(array_replace(jadwalPeriodFormData(), $data))
        ->call('create')
        ->assertHasFormErrors(['tahun_ajaran']);

    $this->assertDatabaseCount('jadwal_pelajarans', 0);
})->with([
    'not consecutive' => [['tahun_ajaran' => '2025/2027']],
    'wrong format' => [['tahun_ajaran' => '2025-2026']],
]);

test('filters the schedule table by semester and academic year', function () {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran'));
    $oldClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas Genap Lama']);
    $activeClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas Ganjil Baru']);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $oldClass->id, 'semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $activeClass->id, 'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027']);

    Livewire::test(ListJadwalPelajarans::class)
        ->assertSee('Kelas Genap Lama')->assertSee('Kelas Ganjil Baru')
        ->filterTable('semester', 'Ganjil')
        ->assertSee('Kelas Ganjil Baru')->assertDontSee('Kelas Genap Lama')
        ->filterTable('semester', null)
        ->filterTable('tahun_ajaran', '2025/2026')
        ->assertSee('Kelas Genap Lama')->assertDontSee('Kelas Ganjil Baru');
});
