<?php

use App\Filament\Resources\JadwalPelajarans\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\JadwalPelajarans\Pages\EditJadwalPelajaran;
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

test('uses the 24 hour jam picker for schedule times', function () {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));

    Livewire::test(CreateJadwalPelajaran::class)
        ->assertSeeHtml('jam-picker')
        ->assertSeeHtml('aria-label="Jam"')
        ->assertSeeHtml('aria-label="Menit"')
        ->assertDontSeeHtml('type="time"');
});

test('stores schedule times with seconds and shows them back as HH:MM', function () {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran', 'Update:JadwalPelajaran'));

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(jadwalPeriodFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $schedule = JadwalPelajaran::query()->sole();
    expect($schedule->jam_mulai)->toBe('07:00:00')
        ->and($schedule->jam_selesai)->toBe('09:00:00');

    Livewire::test(EditJadwalPelajaran::class, ['record' => $schedule->getKey()])
        ->assertFormSet([
            'jam_mulai' => '07:00',
            'jam_selesai' => '09:00',
        ]);
});

test('filters the schedule table by class', function () {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran'));
    $mtsClass = KelasFactory::new()->create(['nama_kelas' => 'VII A', 'unit' => 'MTs']);
    $maClass = KelasFactory::new()->create(['nama_kelas' => 'X IPA', 'unit' => 'MA']);
    $mtsSchedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $mtsClass->id]);
    $maSchedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $maClass->id]);

    Livewire::test(ListJadwalPelajarans::class)
        ->assertSee('VII A (MTs)')
        ->assertSee('X IPA (MA)')
        ->filterTable('kelas_id', $mtsClass->id)
        ->assertCanSeeTableRecords([$mtsSchedule])
        ->assertCanNotSeeTableRecords([$maSchedule])
        ->filterTable('kelas_id', null)
        ->assertCanSeeTableRecords([$mtsSchedule, $maSchedule]);
});

test('filters the schedule table by semester and academic year', function () {
    $this->actingAs(jadwalPeriodUser('ViewAny:JadwalPelajaran'));
    $oldClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas Genap Lama']);
    $activeClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas Ganjil Baru']);
    $oldSchedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $oldClass->id, 'semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $activeSchedule = JadwalPelajaranFactory::new()->create(['kelas_id' => $activeClass->id, 'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027']);

    Livewire::test(ListJadwalPelajarans::class)
        ->assertSeeHtml('jadwal-filter-bar')
        ->assertDontSee('Terapkan filter')
        ->assertCanSeeTableRecords([$oldSchedule, $activeSchedule])
        ->filterTable('semester', 'Ganjil')
        ->assertCanSeeTableRecords([$activeSchedule])
        ->assertCanNotSeeTableRecords([$oldSchedule])
        ->filterTable('semester', null)
        ->filterTable('tahun_ajaran', '2025/2026')
        ->assertCanSeeTableRecords([$oldSchedule])
        ->assertCanNotSeeTableRecords([$activeSchedule]);
});
