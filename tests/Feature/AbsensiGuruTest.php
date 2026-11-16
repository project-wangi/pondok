<?php

use App\Filament\Pages\AbsensiGuru;
use App\Models\AbsenGuru;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenGuruFactory;
use Database\Factories\GuruFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-16 07:12:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

test('shows the menu in the Absensi group', function () {
    expect(AbsensiGuru::getNavigationGroup())->toBe('Absensi')
        ->and(AbsensiGuru::getNavigationLabel())->toBe('Absensi Guru')
        ->and(AbsensiGuru::getSlug())->toBe('absensi-guru')
        ->and(AbsensiGuru::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.absensi-guru'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.absensi-guru'))->assertForbidden();
    Livewire::test(AbsensiGuru::class)->assertForbidden();
});

test('records the clock in time once per day', function () {
    $guru = GuruFactory::new()->create([
        'user_id' => User::factory()->create(['name' => 'Ust. Ahmad Fauzi'])->id,
    ]);
    $this->actingAs($guru->user);

    Livewire::test(AbsensiGuru::class)
        ->assertOk()
        ->assertSee('Belum absen')
        ->call('masuk')
        ->assertHasNoErrors()
        ->assertNotified('Absen masuk tersimpan')
        ->assertSee('Pukul 07:12');

    $absen = AbsenGuru::query()->sole();
    expect($absen->guru_id)->toBe($guru->id)
        ->and($absen->user_id)->toBe($guru->user_id)
        ->and($absen->nama_guru)->toBe('Ust. Ahmad Fauzi')
        ->and($absen->tanggal->toDateString())->toBe('2026-11-16')
        ->and($absen->masuk_pada->format('H:i'))->toBe('07:12')
        ->and($absen->pulang_pada)->toBeNull();

    Livewire::test(AbsensiGuru::class)
        ->call('masuk')
        ->assertHasErrors(['masuk'])
        ->assertSee('Anda sudah absen masuk hari ini pukul 07:12.');

    $this->assertDatabaseCount('absen_gurus', 1);
});

test('rejects clock out before clock in', function () {
    $guru = GuruFactory::new()->create();
    $this->actingAs($guru->user);

    Livewire::test(AbsensiGuru::class)
        ->call('pulang')
        ->assertHasErrors(['pulang'])
        ->assertSee('Absen masuk terlebih dahulu sebelum absen pulang.');

    $this->assertDatabaseCount('absen_gurus', 0);
});

test('records the clock out time once after clock in', function () {
    $guru = GuruFactory::new()->create();
    $this->actingAs($guru->user);

    Livewire::test(AbsensiGuru::class)->call('masuk')->assertHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-11-16 14:03:00', 'Asia/Jakarta'));

    Livewire::test(AbsensiGuru::class)
        ->call('pulang')
        ->assertHasNoErrors()
        ->assertNotified('Absen pulang tersimpan')
        ->assertSee('Pukul 14:03');

    expect(AbsenGuru::query()->sole()->pulang_pada->format('H:i'))->toBe('14:03');

    Livewire::test(AbsensiGuru::class)
        ->call('pulang')
        ->assertHasErrors(['pulang'])
        ->assertSee('Anda sudah absen pulang hari ini pukul 14:03.');
});

test('lists the last seven days of attendance newest first', function () {
    $guru = GuruFactory::new()->create();
    $this->actingAs($guru->user);

    AbsenGuruFactory::new()->create([
        'guru_id' => $guru->id,
        'tanggal' => '2026-11-16',
        'masuk_pada' => CarbonImmutable::parse('2026-11-16 07:05:00'),
        'pulang_pada' => null,
    ]);
    AbsenGuruFactory::new()->create([
        'guru_id' => $guru->id,
        'tanggal' => '2026-11-12',
        'masuk_pada' => CarbonImmutable::parse('2026-11-12 06:58:00'),
        'pulang_pada' => CarbonImmutable::parse('2026-11-12 14:10:00'),
    ]);
    // Di luar jendela tujuh hari terakhir.
    AbsenGuruFactory::new()->create([
        'guru_id' => $guru->id,
        'tanggal' => '2026-11-08',
    ]);

    Livewire::test(AbsensiGuru::class)
        ->assertSee('16 Nov 2026')
        ->assertSee('12 Nov 2026')
        ->assertSee('Belum pulang')
        ->assertSee('Lengkap')
        ->assertDontSee('08 Nov 2026');
});
