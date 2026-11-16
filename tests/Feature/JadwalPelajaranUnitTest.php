<?php

use App\Filament\Resources\JadwalPelajarans\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\JadwalPelajarans\Schemas\JadwalPelajaranForm;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @return array<string, mixed> */
function jadwalUnitFormData(Kelas $kelas, MataPelajaran $mataPelajaran): array
{
    return [
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'hari' => 'Senin',
        'kelas_id' => $kelas->id,
        'mata_pelajaran' => $mataPelajaran->id,
        'jam_mulai' => '07:00',
        'jam_selesai' => '08:00',
        'guru_id' => GuruFactory::new()->create()->id,
    ];
}

test('filters subjects by the class unit and keeps subjects that apply to all units', function () {
    $mtsSubject = MataPelajaranFactory::new()->create(['unit' => Kelas::UNIT_MTS]);
    $maSubject = MataPelajaranFactory::new()->create(['unit' => Kelas::UNIT_MA]);
    $universal = MataPelajaranFactory::new()->create(['unit' => null]);

    $ids = MataPelajaran::query()->forUnit(Kelas::UNIT_MTS)->pluck('id')->all();

    expect($ids)->toContain($mtsSubject->id)
        ->and($ids)->toContain($universal->id)
        ->and($ids)->not->toContain($maSubject->id)
        ->and(MataPelajaran::query()->forUnit(null)->count())->toBe(3);
});

test('rejects a subject from another unit when saving a schedule', function () {
    $this->actingAs(adminPanelUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));

    $kelas = KelasFactory::new()->create(['unit' => Kelas::UNIT_MTS]);
    $subject = MataPelajaranFactory::new()->create(['unit' => Kelas::UNIT_MA]);

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(jadwalUnitFormData($kelas, $subject))
        ->call('create')
        ->assertHasFormErrors(['mata_pelajaran']);

    $this->assertDatabaseCount('jadwal_pelajarans', 0);
});

test('accepts a subject from the same unit', function () {
    $this->actingAs(adminPanelUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));

    $kelas = KelasFactory::new()->create(['unit' => Kelas::UNIT_MTS]);
    $subject = MataPelajaranFactory::new()->create(['unit' => Kelas::UNIT_MTS]);

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(jadwalUnitFormData($kelas, $subject))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('jadwal_pelajarans', [
        'kelas_id' => $kelas->id,
        'mata_pelajaran' => $subject->id,
    ]);
});

test('shows the unit as a badge in the select labels', function () {
    expect(JadwalPelajaranForm::optionLabel('Fiqih', Kelas::UNIT_MTS))
        ->toBe('Fiqih <span class="jadwal-unit-badge">MTs</span>')
        ->and(JadwalPelajaranForm::optionLabel('Bahasa Arab', null))
        ->toBe('Bahasa Arab <span class="jadwal-unit-badge is-all">Semua unit</span>')
        ->and(JadwalPelajaranForm::optionLabel('<b>Nama</b>', Kelas::UNIT_MA))
        ->toBe('&lt;b&gt;Nama&lt;/b&gt; <span class="jadwal-unit-badge">MA</span>');
});

test('clears the chosen subject when the class changes', function () {
    $this->actingAs(adminPanelUser('ViewAny:JadwalPelajaran', 'Create:JadwalPelajaran'));

    $mtsKelas = KelasFactory::new()->create(['unit' => Kelas::UNIT_MTS]);
    $maKelas = KelasFactory::new()->create(['unit' => Kelas::UNIT_MA]);
    $subject = MataPelajaranFactory::new()->create(['unit' => Kelas::UNIT_MTS]);

    Livewire::test(CreateJadwalPelajaran::class)
        ->fillForm(jadwalUnitFormData($mtsKelas, $subject))
        ->assertFormSet(['mata_pelajaran' => $subject->id])
        ->set('data.kelas_id', $maKelas->id)
        ->assertFormSet(['mata_pelajaran' => null]);
});
