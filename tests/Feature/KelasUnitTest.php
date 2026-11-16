<?php

use App\Filament\Resources\Kelas\Pages\CreateKelas;
use App\Filament\Resources\Kelas\Pages\ListKelas;
use App\Models\Kelas;
use Database\Factories\KelasFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

test('shows the unit in the class table', function () {
    KelasFactory::new()->create(['nama_kelas' => 'VII A', 'unit' => Kelas::UNIT_MTS]);
    KelasFactory::new()->create(['nama_kelas' => 'X IPA', 'unit' => Kelas::UNIT_MA]);
    $this->actingAs(adminPanelUser('ViewAny:Kelas'));

    Livewire::test(ListKelas::class)
        ->assertSee('VII A')
        ->assertSee('X IPA')
        ->assertSee('MTs')
        ->assertSee('MA');
});

test('filters classes by unit', function () {
    KelasFactory::new()->create(['nama_kelas' => 'VIII B', 'unit' => Kelas::UNIT_MTS]);
    KelasFactory::new()->create(['nama_kelas' => 'XI IPS', 'unit' => Kelas::UNIT_MA]);
    $this->actingAs(adminPanelUser('ViewAny:Kelas'));

    Livewire::test(ListKelas::class)
        ->filterTable('unit', Kelas::UNIT_MA)
        ->assertSee('XI IPS')
        ->assertDontSee('VIII B')
        ->filterTable('unit', Kelas::UNIT_MTS)
        ->assertSee('VIII B')
        ->assertDontSee('XI IPS');
});

test('stores the selected unit when creating a class', function () {
    $this->actingAs(adminPanelUser('ViewAny:Kelas', 'Create:Kelas'));

    Livewire::test(CreateKelas::class)
        ->fillForm([
            'nama_kelas' => 'X IPA',
            'unit' => Kelas::UNIT_MA,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('kelas', [
        'nama_kelas' => 'X IPA',
        'unit' => Kelas::UNIT_MA,
    ]);
});

test('requires a unit when creating a class', function () {
    $this->actingAs(adminPanelUser('ViewAny:Kelas', 'Create:Kelas'));

    Livewire::test(CreateKelas::class)
        ->fillForm(['nama_kelas' => 'Kelas Tanpa Unit'])
        ->call('create')
        ->assertHasFormErrors(['unit']);

    $this->assertDatabaseMissing('kelas', ['nama_kelas' => 'Kelas Tanpa Unit']);
});
