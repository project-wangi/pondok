<?php

use App\Filament\Resources\JadwalPelajarans\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\JadwalPelajarans\Pages\EditJadwalPelajaran;
use App\Filament\Resources\JadwalPelajarans\Schemas\JadwalPelajaranForm;
use App\Models\Guru;
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
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function conflictUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/** @param array<string, mixed> $attributes */
function busySchedule(Guru $guru, array $attributes = []): JadwalPelajaran
{
    return JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'hari' => 'Senin',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '10:00:00',
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        ...$attributes,
    ]);
}

/** @return array<string, mixed> */
function conflictFormData(Guru $guru, string $start = '09:00', string $end = '11:00'): array
{
    return [
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'hari' => 'Senin',
        'mata_pelajaran' => MataPelajaranFactory::new()->create()->id,
        'jam_mulai' => $start,
        'jam_selesai' => $end,
        'guru_id' => $guru->id,
        'kelas_id' => KelasFactory::new()->create()->id,
    ];
}

test('hides a teacher who already teaches at an overlapping time', function () {
    $busy = GuruFactory::new()->create();
    $free = GuruFactory::new()->create();
    busySchedule($busy);

    $options = JadwalPelajaranForm::teacherOptions('Ganjil', '2026/2027', 'Senin', '09:00', '11:00');

    expect($options)->toHaveKey($free->id)
        ->and($options)->not->toHaveKey($busy->id);
});

test('keeps teachers whose schedule only touches the new time', function (string $start, string $end) {
    $busy = GuruFactory::new()->create();
    busySchedule($busy);

    $options = JadwalPelajaranForm::teacherOptions('Ganjil', '2026/2027', 'Senin', $start, $end);

    expect($options)->toHaveKey($busy->id);
})->with([
    'starts when the other ends' => ['10:00', '12:00'],
    'ends when the other starts' => ['06:00', '08:00'],
]);

test('ignores schedules on other days or academic periods', function (array $attributes) {
    $teacher = GuruFactory::new()->create();
    busySchedule($teacher, $attributes);

    $options = JadwalPelajaranForm::teacherOptions('Ganjil', '2026/2027', 'Senin', '09:00', '11:00');

    expect($options)->toHaveKey($teacher->id);
})->with([
    'other day' => [['hari' => 'Selasa']],
    'other semester' => [['semester' => 'Genap']],
    'other year' => [['tahun_ajaran' => '2025/2026']],
]);

test('keeps the teacher when editing the same schedule', function () {
    $teacher = GuruFactory::new()->create();
    $schedule = busySchedule($teacher);

    $options = JadwalPelajaranForm::teacherOptions('Ganjil', '2026/2027', 'Senin', '09:00', '11:00', $schedule->id);

    expect($options)->toHaveKey($teacher->id);
});

test('rejects a conflicting teacher from the create form', function () {
    $this->actingAs(conflictUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));
    $busy = GuruFactory::new()->create();
    busySchedule($busy);

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(conflictFormData($busy))
        ->call('create')
        ->assertHasFormErrors(['guru_id']);

    $this->assertDatabaseCount('jadwal_pelajarans', 1);
});

test('allows a teacher without a conflicting schedule', function () {
    $this->actingAs(conflictUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));
    $free = GuruFactory::new()->create();

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(conflictFormData($free))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseCount('jadwal_pelajarans', 1);
});

test('allows saving the edited schedule itself', function () {
    $this->actingAs(conflictUser(
        'ViewAny:JadwalPelajaran',
        'View:JadwalPelajaran',
        'Update:JadwalPelajaran',
    ));
    $teacher = GuruFactory::new()->create();
    $schedule = busySchedule($teacher);

    Livewire::test(EditJadwalPelajaran::class, ['record' => $schedule->getKey()])
        ->fillForm(['guru_id' => $teacher->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseCount('jadwal_pelajarans', 1);
});

test('rejects editing a schedule into another conflicting time', function () {
    $this->actingAs(conflictUser(
        'ViewAny:JadwalPelajaran',
        'View:JadwalPelajaran',
        'Update:JadwalPelajaran',
    ));
    $teacher = GuruFactory::new()->create();
    busySchedule($teacher, ['jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00']);
    $editable = busySchedule($teacher, ['jam_mulai' => '10:00:00', 'jam_selesai' => '12:00:00']);

    Livewire::test(EditJadwalPelajaran::class, ['record' => $editable->getKey()])
        ->fillForm([
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
        ])
        ->call('save')
        ->assertHasFormErrors(['guru_id']);

    $this->assertDatabaseHas('jadwal_pelajarans', [
        'id' => $editable->id, 'jam_mulai' => '10:00:00', 'jam_selesai' => '12:00:00',
    ]);
});
