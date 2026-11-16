<?php

use App\Filament\Resources\MataPelajarans\Pages\CreateMataPelajaran;
use App\Filament\Resources\MataPelajarans\Pages\ListMataPelajarans;
use Database\Factories\ERaporFactory;
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

test('stores the unit when creating a subject', function () {
    $this->actingAs(adminPanelUser('ViewAny:MataPelajaran', 'Create:MataPelajaran'));

    Livewire::test(CreateMataPelajaran::class)
        ->fillForm([
            'kode_mapel' => 'MTK',
            'nama_mapel' => 'Matematika',
            'unit' => 'MTs',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('mata_pelajarans', [
        'kode_mapel' => 'MTK',
        'nama_mapel' => 'Matematika',
        'unit' => 'MTs',
    ]);
});

test('shows the unit and the kkm used on the e-report in the subject table', function () {
    $subject = MataPelajaranFactory::new()->create([
        'nama_mapel' => 'Fiqih',
        'unit' => 'MA',
    ]);
    ERaporFactory::new()->create([
        'mata_pelajaran_id' => $subject->id,
        'kkm' => 78,
    ]);
    $this->actingAs(adminPanelUser('ViewAny:MataPelajaran'));

    Livewire::test(ListMataPelajarans::class)
        ->assertSee('Fiqih')
        ->assertSee('MA')
        ->assertSee('78');
});

test('shows the default kkm before the teacher submits an e-report', function () {
    MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab', 'unit' => 'MTs']);
    $this->actingAs(adminPanelUser('ViewAny:MataPelajaran'));

    Livewire::test(ListMataPelajarans::class)
        ->assertSee('Bahasa Arab')
        ->assertSee('75 (default)');
});
