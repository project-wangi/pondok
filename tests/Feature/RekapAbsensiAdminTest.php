<?php

use App\Filament\Pages\RekapAbsensi;
use App\Filament\Pages\RekapAbsensiPeminatan;
use App\Filament\Pages\RekapAbsensiPiket;
use App\Filament\Resources\AbsenRapats\AbsenRapatResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
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

function rekapAdminUser(): User
{
    return adminPanelUser(
        'View:RekapAbsensi',
        'View:RekapAbsensiPeminatan',
        'View:RekapAbsensiPiket',
        'ViewAny:AbsenRapat',
    );
}

test('lets admins open every attendance recap menu', function () {
    $this->actingAs(rekapAdminUser());

    $this->get(route('filament.admin.pages.rekap-absensi'))->assertOk();
    $this->get(route('filament.admin.pages.rekap-absensi-peminatan'))->assertOk();
    $this->get(route('filament.admin.pages.rekap-absensi-piket'))->assertOk();
    $this->get(AbsenRapatResource::getUrl('index'))->assertOk();

    expect(RekapAbsensi::canAccess())->toBeTrue()
        ->and(RekapAbsensiPeminatan::canAccess())->toBeTrue()
        ->and(RekapAbsensiPiket::canAccess())->toBeTrue()
        ->and(AbsenRapatResource::canAccess())->toBeTrue();
});

test('shows schedules from every teacher to admins without a teacher profile', function () {
    $this->actingAs(rekapAdminUser());

    $kelasA = KelasFactory::new()->create(['nama_kelas' => 'Tujuh A']);
    $kelasB = KelasFactory::new()->create(['nama_kelas' => 'Delapan B']);
    $mapel = MataPelajaranFactory::new()->create(['nama_mapel' => 'Fiqih']);

    foreach ([$kelasA, $kelasB] as $kelas) {
        JadwalPelajaranFactory::new()->create([
            'guru_id' => GuruFactory::new()->create()->id,
            'mata_pelajaran' => $mapel->id,
            'kelas_id' => $kelas->id,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
        ]);
    }

    Livewire::test(RekapAbsensi::class)
        ->assertSee('Tujuh A')
        ->assertSee('Delapan B');
});

test('keeps accounts without a teacher profile out of the recaps', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('filament.admin.pages.rekap-absensi'))->assertForbidden();

    expect(RekapAbsensi::canAccess())->toBeFalse()
        ->and(RekapAbsensiPeminatan::canAccess())->toBeFalse()
        ->and(RekapAbsensiPiket::canAccess())->toBeFalse()
        ->and(AbsenRapatResource::canAccess())->toBeFalse();
});
