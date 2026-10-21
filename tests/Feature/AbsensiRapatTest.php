<?php

use App\Filament\Pages\AbsensiRapat;
use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\JadwalRapat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

/** @param array<string, mixed> $attributes */
function rapatTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param list<Guru> $members */
function rapatMeeting(string $agenda, array $members, string $tanggal = '2026-10-19', string $mulai = '08:00:00', string $selesai = '10:00:00'): JadwalRapat
{
    $meeting = JadwalRapat::query()->create([
        'tanggal' => $tanggal,
        'hari' => CarbonImmutable::parse($tanggal)->locale('id')->translatedFormat('l'),
        'jam_mulai' => $mulai,
        'jam_selesai' => $selesai,
        'agenda' => $agenda,
    ]);
    $meeting->gurus()->attach(collect($members)->pluck('id')->all());

    return $meeting;
}

test('shows the menu in the Pembelajaran group', function () {
    expect(AbsensiRapat::getNavigationGroup())->toBe('Pembelajaran')
        ->and(AbsensiRapat::getNavigationLabel())->toBe('Absensi Rapat')
        ->and(AbsensiRapat::getSlug())->toBe('absensi-rapat')
        ->and(AbsensiRapat::shouldRegisterNavigation())->toBeTrue();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.absensi-rapat'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.absensi-rapat'))->assertForbidden();
    Livewire::test(AbsensiRapat::class)->assertForbidden();
});

test('shows an empty state when there is no meeting today', function () {
    $this->actingAs(rapatTeacher()->user);

    Livewire::test(AbsensiRapat::class)
        ->assertOk()
        ->assertSee('Tidak ada rapat untuk Anda pada hari ini.');
});

test('lists only today meetings that include the teacher', function () {
    $teacher = rapatTeacher();
    $other = rapatTeacher();
    $own = rapatMeeting('Rapat koordinasi guru', [$teacher]);
    rapatMeeting('Rapat panitia', [$other]);
    rapatMeeting('Rapat besok', [$teacher], '2026-10-20');
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)
        ->assertSee('Rapat koordinasi guru')
        ->assertSee('1 rapat hari ini')
        ->assertSee('08:00 - 10:00')
        ->assertSee('Belum absen')
        ->assertDontSee('Rapat panitia')
        ->assertDontSee('Rapat besok')
        ->assertSeeHtml('wire:click="pilihRapat('.$own->id.')"');
});

test('stores a selfie on the r2 disk exactly once', function () {
    Storage::fake('r2');
    $teacher = rapatTeacher(['user_id' => User::factory()->create(['name' => 'Guru Rapat'])->id]);
    $meeting = rapatMeeting('Rapat evaluasi belajar', [$teacher]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)
        ->call('pilihRapat', $meeting->id)
        ->assertDontSeeHtml('type="file"')
        ->assertSeeHtml('x-ref="video"')
        ->assertSeeHtml('rapat-camera-stage')
        ->assertSeeHtml('rapat-camera-actions')
        ->set('foto', UploadedFile::fake()->image('selfie.jpg', 640, 800))
        ->call('absen')
        ->assertHasNoErrors()
        ->assertNotified('Absensi rapat tersimpan')
        ->assertSee('Sudah absen');

    $absen = AbsenRapat::query()->sole();
    $this->assertDatabaseHas('absen_rapats', [
        'id' => $absen->id, 'jadwal_rapat_id' => $meeting->id, 'guru_id' => $teacher->id,
        'user_id' => $teacher->user_id, 'nama_guru' => 'Guru Rapat',
        'agenda' => 'Rapat evaluasi belajar', 'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00',
    ]);
    expect($absen->foto)->toStartWith('absen-rapat/2026-10-19/');
    Storage::disk('r2')->assertExists($absen->foto);
    Storage::disk('local')->assertMissing($absen->foto);
});

test('rejects a second attendance for the same meeting', function () {
    Storage::fake('r2');
    $teacher = rapatTeacher();
    $meeting = rapatMeeting('Rapat bulanan', [$teacher]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)
        ->call('pilihRapat', $meeting->id)
        ->set('foto', UploadedFile::fake()->image('selfie.jpg'))
        ->call('absen')->assertHasNoErrors();

    Livewire::test(AbsensiRapat::class)
        ->call('pilihRapat', $meeting->id)
        ->set('foto', UploadedFile::fake()->image('selfie-lagi.jpg'))
        ->call('absen')
        ->assertHasErrors(['rapatId'])
        ->assertSee('Absensi rapat hanya dapat dilakukan satu kali.');

    $this->assertDatabaseCount('absen_rapats', 1);
});

test('rejects meetings that are not today or do not include the teacher', function () {
    $teacher = rapatTeacher();
    $other = rapatTeacher();
    $tomorrow = rapatMeeting('Rapat besok', [$teacher], '2026-10-20');
    $foreign = rapatMeeting('Rapat guru lain', [$other]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)->call('pilihRapat', $tomorrow->id)->assertNotFound();
    Livewire::test(AbsensiRapat::class)->call('pilihRapat', $foreign->id)->assertNotFound();
});

test('requires an image selfie and limits its size', function (string $kind, string $message) {
    Storage::fake('r2');
    $teacher = rapatTeacher();
    $meeting = rapatMeeting('Rapat kedisiplinan', [$teacher]);
    $this->actingAs($teacher->user);
    $file = $kind === 'document'
        ? UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')
        : UploadedFile::fake()->image('besar.jpg')->size(6000);

    Livewire::test(AbsensiRapat::class)
        ->call('pilihRapat', $meeting->id)
        ->set('foto', $file)
        ->call('absen')
        ->assertHasErrors(['foto'])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('absen_rapats', 0);
})->with([
    'document' => ['document', 'Foto harus berupa gambar.'],
    'too large' => ['large', 'Foto maksimal 5 MB.'],
]);

test('requires a selfie before submitting', function () {
    Storage::fake('r2');
    $teacher = rapatTeacher();
    $meeting = rapatMeeting('Rapat wajib', [$teacher]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)
        ->call('pilihRapat', $meeting->id)
        ->call('absen')
        ->assertHasErrors(['foto'])
        ->assertSee('Ambil selfie terlebih dahulu.');

    $this->assertDatabaseCount('absen_rapats', 0);
});

test('prevents clients from replacing the locked meeting id', function () {
    $teacher = rapatTeacher();
    $meeting = rapatMeeting('Rapat koordinasi', [$teacher]);
    $this->actingAs($teacher->user);
    $component = Livewire::test(AbsensiRapat::class)->call('pilihRapat', $meeting->id);

    expect(fn () => $component->set('rapatId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('escapes meeting agenda text', function () {
    $teacher = rapatTeacher();
    rapatMeeting('<script>rapat()</script>', [$teacher]);
    $this->actingAs($teacher->user);

    Livewire::test(AbsensiRapat::class)
        ->assertSee('<script>rapat()</script>')
        ->assertDontSeeHtml('<script>rapat()</script>');
});
