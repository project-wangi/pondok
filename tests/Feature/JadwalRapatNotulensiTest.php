<?php

use App\Filament\Resources\JadwalRapats\Pages\CreateJadwalRapat;
use App\Filament\Resources\JadwalRapats\Pages\EditJadwalRapat;
use App\Filament\Resources\JadwalRapats\Pages\ListJadwalRapats;
use App\Models\JadwalRapat;
use Database\Factories\GuruFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @param array<string, mixed> $attributes */
function notulensiMeeting(array $attributes = []): JadwalRapat
{
    return JadwalRapat::query()->create([
        'tanggal' => '2026-11-20',
        'hari' => 'Jumat',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '10:00:00',
        'agenda' => 'Rapat kurikulum',
        ...$attributes,
    ]);
}

test('an admin writes meeting minutes with the markdown editor', function () {
    $this->actingAs(adminPanelUser(
        'ViewAny:JadwalRapat',
        'Create:JadwalRapat',
        'Update:JadwalRapat',
    ));

    $notulensi = "## Keputusan\n\n- Menambah jam tahfiz\n- Menyusun jadwal piket baru";

    Livewire::test(CreateJadwalRapat::class)
        ->assertSeeHtml('fi-fo-markdown-editor')
        ->fillForm([
            'tanggal' => '2026-11-20',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'agenda' => 'Rapat kurikulum',
            'notulensi' => $notulensi,
            'gurus' => [GuruFactory::new()->create()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rapat = JadwalRapat::query()->sole();
    expect($rapat->notulensi)->toBe($notulensi);

    Livewire::test(EditJadwalRapat::class, ['record' => $rapat->getKey()])
        ->assertFormSet(['notulensi' => $notulensi]);
});

test('the meeting table marks which meetings already have minutes', function () {
    $this->actingAs(adminPanelUser('ViewAny:JadwalRapat'));

    notulensiMeeting(['agenda' => 'Rapat tanpa catatan']);
    $withMinutes = notulensiMeeting(['agenda' => 'Rapat dengan catatan', 'notulensi' => '## Hasil rapat']);
    $withoutMinutes = JadwalRapat::query()->where('agenda', 'Rapat tanpa catatan')->sole();

    Livewire::test(ListJadwalRapats::class)
        ->assertTableColumnFormattedStateSet('notulensi', 'Ada', $withMinutes)
        ->assertTableColumnFormattedStateSet('notulensi', 'Belum ada', $withoutMinutes);
});
