<?php

use App\Filament\Pages\InputKepribadian;
use App\Models\Guru;
use App\Models\Murid;
use App\Models\RaporKepribadian;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

function kepribadianWaliKelas(int $kelasId): Guru
{
    return GuruFactory::new()->create(['kelas_id' => $kelasId]);
}

function kepribadianStudent(int $kelasId, string $nama): Murid
{
    return MuridFactory::new()->create(['kelas_id' => $kelasId, 'nama' => $nama]);
}

test('restricts the personality page to homeroom teachers', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    Livewire::test(InputKepribadian::class)->assertForbidden();

    $kelas = KelasFactory::new()->create();
    $waliKelas = kepribadianWaliKelas($kelas->id);
    $this->actingAs($waliKelas->user);

    Livewire::test(InputKepribadian::class)->assertOk();

    $this->actingAs(adminPanelUser());
    Livewire::test(InputKepribadian::class)->assertOk();
});

test('assigns the homeroom role when a teacher is given a class', function () {
    $kelas = KelasFactory::new()->create();
    $waliKelas = kepribadianWaliKelas($kelas->id);

    expect($waliKelas->user->fresh()->hasRole('wali_kelas'))->toBeTrue();
});

test('fills personality scores for every student in the homeroom class', function () {
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VII A']);
    $waliKelas = kepribadianWaliKelas($kelas->id);
    $first = kepribadianStudent($kelas->id, 'Ahmad');
    $second = kepribadianStudent($kelas->id, 'Bilal');
    $this->actingAs($waliKelas->user);

    Livewire::test(InputKepribadian::class)
        ->assertSee('VII A')
        ->assertSee('Ahmad')
        ->assertSee('Bilal')
        ->set('kepribadian.'.$first->id.'.kelakuan', '95')
        ->set('kepribadian.'.$first->id.'.kerajinan', '88')
        ->set('kepribadian.'.$first->id.'.kebersihan', '79')
        ->set('kepribadian.'.$first->id.'.kerapian', '65')
        ->set('kepribadian.'.$first->id.'.kedisiplinan', '50')
        ->set('kepribadian.'.$second->id.'.kelakuan', '80')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Nilai kepribadian tersimpan');

    $this->assertDatabaseHas('rapor_kepribadians', [
        'murid_id' => $first->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'kelakuan' => 95,
        'kerajinan' => 88,
        'kebersihan' => 79,
        'kerapian' => 65,
        'kedisiplinan' => 50,
    ]);

    $secondRecord = RaporKepribadian::query()->where('murid_id', $second->id)->sole();

    expect($secondRecord->kelakuan)->toBe(80)
        ->and($secondRecord->kerajinan)->toBeNull();
});

test('loads saved personality scores when the page opens again', function () {
    $kelas = KelasFactory::new()->create();
    $waliKelas = kepribadianWaliKelas($kelas->id);
    $student = kepribadianStudent($kelas->id, 'Ahmad');
    RaporKepribadian::query()->create([
        'murid_id' => $student->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'kelakuan' => 95,
        'kerajinan' => 88,
        'kebersihan' => 79,
        'kerapian' => 65,
        'kedisiplinan' => 50,
    ]);
    $this->actingAs($waliKelas->user);

    Livewire::test(InputKepribadian::class)
        ->assertSet('kepribadian.'.$student->id.'.kelakuan', '95')
        ->assertSet('kepribadian.'.$student->id.'.kedisiplinan', '50')
        ->assertSee('A')
        ->assertSee('E');
});

test('rejects personality scores outside the allowed range', function () {
    $kelas = KelasFactory::new()->create();
    $waliKelas = kepribadianWaliKelas($kelas->id);
    $student = kepribadianStudent($kelas->id, 'Ahmad');
    $this->actingAs($waliKelas->user);

    Livewire::test(InputKepribadian::class)
        ->set('kepribadian.'.$student->id.'.kelakuan', '101')
        ->call('simpan')
        ->assertHasErrors(['kepribadian.'.$student->id.'.kelakuan'])
        ->assertSee('Nilai Kelakuan Ahmad harus berada antara 0 dan 100.');

    expect(RaporKepribadian::query()->count())->toBe(0);
});

test('shows a notice when the account has no homeroom class', function () {
    $this->actingAs(adminPanelUser());

    Livewire::test(InputKepribadian::class)
        ->assertSee('Akun ini belum ditetapkan sebagai wali kelas.');
});
