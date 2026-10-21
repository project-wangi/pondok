<?php

use App\Filament\Pages\InputLingkupMateri;
use App\Filament\Pages\JurnalMengajar;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JurnalMengajar as JurnalMengajarModel;
use App\Models\JurnalMengajarBaris;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\JurnalMengajarBarisFactory;
use Database\Factories\JurnalMengajarFactory;
use Database\Factories\KelasFactory;
use Database\Factories\LingkupMateriFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\TujuanPembelajaranFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Exceptions\MethodNotFoundException;
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

function jurnalTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function jurnalSchedule(Guru $teacher, MataPelajaran $subject, array $attributes = []): JadwalPelajaran
{
    return JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $subject->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        ...$attributes,
    ]);
}

/** @param list<string> $goals */
function jurnalMaterial(Guru $teacher, MataPelajaran $subject, string $material, int $order = 1, array $goals = ['siswa memahami materi']): LingkupMateri
{
    $classId = JadwalPelajaran::query()
        ->where('guru_id', $teacher->id)
        ->where('mata_pelajaran', $subject->id)
        ->where('semester', 'Ganjil')
        ->where('tahun_ajaran', '2026/2027')
        ->value('kelas_id');

    $lingkup = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)
        ->state(['kelas_id' => $classId])
        ->create([
            'materi' => $material,
            'urutan' => $order,
        ]);

    foreach ($goals as $index => $goal) {
        TujuanPembelajaranFactory::new()->create([
            'lingkup_materi_id' => $lingkup->id,
            'urutan' => $index + 1,
            'teks' => $goal,
        ]);
    }

    return $lingkup->load('tujuanPembelajarans');
}

/** @return array<string, mixed> */
function jurnalRow(LingkupMateri $material, int $purposeIndex = 0): array
{
    return [
        'lingkup_materi_id' => $material->id,
        'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[$purposeIndex]->id,
        'kegiatan' => 'guru menjelaskan materi dan siswa berlatih',
        'catatan' => 'kelas berjalan tertib',
        'tindak_lanjut' => 'latihan mandiri',
    ];
}

test('shows the menu in the Pembelajaran group', function () {
    expect(JurnalMengajar::getNavigationGroup())->toBe('Pembelajaran')
        ->and(JurnalMengajar::getNavigationLabel())->toBe('Jurnal Mengajar');
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.jurnal-mengajar'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.jurnal-mengajar'))->assertForbidden();
    Livewire::test(JurnalMengajar::class)->assertForbidden();
});

test('shows an honest empty state when there is no schedule or journal today', function () {
    $this->actingAs(jurnalTeacher()->user);

    Livewire::test(JurnalMengajar::class)
        ->assertOk()
        ->assertSee('Belum ada jadwal atau jurnal tersimpan hari ini.')
        ->assertSee('semester Ganjil tahun ajaran 2026/2027');
});

test('lists only the teachers running-period schedules and opens the journal modal', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz Al-Qur\'an']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'Kelas Jurnal']);
    $schedule = jurnalSchedule($teacher, $subject, ['kelas_id' => $class->id]);
    jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $foreignTeacher = jurnalTeacher();
    $foreignClass = KelasFactory::new()->create(['nama_kelas' => 'Kelas Guru Lain']);
    jurnalSchedule($foreignTeacher, MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']), ['kelas_id' => $foreignClass->id]);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    jurnalSchedule($teacher, $oldSubject, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->assertSee('Kelas Jurnal')
        ->assertSee('Tahfidz Al-Qur\'an')
        ->assertDontSee('Kelas Guru Lain')
        ->assertDontSee('Mapel Guru Lain')
        ->assertDontSee('Mapel Semester Lama')
        ->assertSee('Belum diisi')
        ->call('pilihJadwal', $schedule->id)
        ->assertDispatched('open-modal')
        ->assertSet('jadwalId', $schedule->id)
        ->assertSee('Jurnal mengajar')
        ->assertSee('kontrak pembelajaran')
        ->assertSeeHtml('wire:submit="simpan"');
});

test('explains how to create the source material when the subject has none', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->assertSee('Belum ada lingkup materi')
        ->assertSeeHtml('href="'.InputLingkupMateri::getUrl().'"')
        ->assertDontSeeHtml('wire:submit="simpan"');
});

test('loads purposes from the selected material and resets the purpose when material changes', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $first = jurnalMaterial($teacher, $subject, 'materi pertama', 1, ['tujuan pertama', 'tujuan kedua']);
    $second = jurnalMaterial($teacher, $subject, 'materi kedua', 2, ['tujuan materi kedua']);
    $this->actingAs($teacher->user);

    $component = Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->assertSee('materi pertama')->assertSee('materi kedua')
        ->assertDontSee('tujuan pertama')->assertDontSee('tujuan materi kedua')
        ->assertSet('baris.0.tujuan_pembelajaran_id', null)
        ->set('baris.0.lingkup_materi_id', $first->id)
        ->assertSee('tujuan pertama')->assertSee('tujuan kedua')->assertDontSee('tujuan materi kedua')
        ->set('baris.0.tujuan_pembelajaran_id', $first->tujuanPembelajarans[0]->id)
        ->set('baris.0.lingkup_materi_id', $second->id)
        ->assertSet('baris.0.tujuan_pembelajaran_id', null)
        ->assertSee('tujuan materi kedua')->assertDontSee('tujuan pertama');
});

test('saves a journal with snapshots and marks the schedule as filled', function () {
    $teacher = jurnalTeacher(['user_id' => User::factory()->create(['name' => 'Guru Jurnal'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz']);
    $class = KelasFactory::new()->create(['nama_kelas' => 'VII Jurnal']);
    $schedule = jurnalSchedule($teacher, $subject, ['kelas_id' => $class->id, 'jam_mulai' => '08:00:00', 'jam_selesai' => '09:30:00']);
    $material = jurnalMaterial($teacher, $subject, 'makharijul huruf', 1, ['siswa mengenal makhraj huruf']);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('baris', [[
            ...jurnalRow($material),
            'kegiatan' => '  guru menjelaskan makhraj huruf  ',
        ]])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Jurnal tersimpan')
        ->assertDispatched('close-modal')
        ->assertSee('Sudah diisi');

    $journal = JurnalMengajarModel::query()->sole();
    $this->assertDatabaseHas('jurnal_mengajars', [
        'id' => $journal->id,
        'jadwal_pelajaran_id' => $schedule->id,
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
        'mata_pelajaran_id' => $subject->id,
        'pencatat_id' => $teacher->user_id,
        'revisi' => 1,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'nama_guru' => 'Guru Jurnal',
        'nama_kelas' => 'VII Jurnal',
        'nama_mapel' => 'Tahfidz',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '09:30:00',
        'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    expect($journal->tanggal->toDateString())->toBe('2026-10-19');
    $this->assertDatabaseHas('jurnal_mengajar_baris', [
        'jurnal_mengajar_id' => $journal->id,
        'lingkup_materi_id' => $material->id,
        'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[0]->id,
        'lingkup_materi' => 'makharijul huruf',
        'tujuan_pembelajaran' => 'siswa mengenal makhraj huruf',
        'kegiatan' => 'guru menjelaskan makhraj huruf',
        'catatan' => 'kelas berjalan tertib',
        'tindak_lanjut' => 'latihan mandiri',
        'urutan' => 1,
    ]);
});

test('stores a long material snapshot without truncation', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, str_repeat('materi panjang ', 35));
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)
        ->set('baris', [jurnalRow($material)])->call('simpan')->assertHasNoErrors();

    $snapshot = JurnalMengajarBaris::query()->sole()->lingkup_materi;
    expect(mb_strlen($snapshot))->toBeGreaterThan(255)
        ->and($snapshot)->toBe($material->materi);
});

test('updates an existing journal without duplicates or changing the first recorded time', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);
    $component = Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('baris', [jurnalRow($material)])
        ->call('simpan')->assertHasNoErrors();
    $journal = JurnalMengajarModel::query()->sole();
    $baris = JurnalMengajarBaris::query()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'Asia/Jakarta'));

    $component->call('pilihJadwal', $schedule->id)
        ->set('baris.0.kegiatan', 'kegiatan diperbaiki')
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('jurnal_mengajars', 1);
    $this->assertDatabaseCount('jurnal_mengajar_baris', 1);
    $this->assertDatabaseHas('jurnal_mengajars', [
        'id' => $journal->id, 'revisi' => 2, 'dicatat_pada' => '2026-10-19 10:00:00',
    ]);
    $this->assertDatabaseHas('jurnal_mengajar_baris', [
        'jurnal_mengajar_id' => $journal->id, 'kegiatan' => 'kegiatan diperbaiki',
    ]);
});

test('supports multiple journal rows in one meeting and adds or removes them', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $first = jurnalMaterial($teacher, $subject, 'materi satu', 1, ['tujuan satu']);
    $second = jurnalMaterial($teacher, $subject, 'materi dua', 2, ['tujuan dua']);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->call('tambahBaris')
        ->assertCount('baris', 2)
        ->set('baris', [jurnalRow($first), jurnalRow($second)])
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('jurnal_mengajars', 1);
    $this->assertDatabaseCount('jurnal_mengajar_baris', 2);
    $this->assertDatabaseHas('jurnal_mengajar_baris', ['urutan' => 1, 'lingkup_materi' => 'materi satu']);
    $this->assertDatabaseHas('jurnal_mengajar_baris', ['urutan' => 2, 'lingkup_materi' => 'materi dua']);

    Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)
        ->call('hapusBaris', 0)
        ->assertCount('baris', 1)
        ->call('hapusBaris', 0)
        ->assertCount('baris', 1);

    expect(JurnalMengajarModel::query()->sole()->baris()->count())->toBe(2);
});

test('rejects invalid journal entries without saving anything', function (array $row, string $field, string $message) {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('baris', [array_replace(jurnalRow($material), $row)])
        ->call('simpan')
        ->assertHasErrors([$field])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('jurnal_mengajars', 0);
    $this->assertDatabaseCount('jurnal_mengajar_baris', 0);
})->with([
    'missing material' => [['lingkup_materi_id' => null], 'baris.0.lingkup_materi_id', 'Pilih lingkup materi untuk setiap baris.'],
    'missing purpose' => [['tujuan_pembelajaran_id' => null], 'baris.0.tujuan_pembelajaran_id', 'Pilih tujuan pembelajaran untuk setiap baris.'],
    'empty activity' => [['kegiatan' => ''], 'baris.0.kegiatan', 'Kegiatan pembelajaran wajib diisi.'],
    'activity too long' => [['kegiatan' => str_repeat('a', 2001)], 'baris.0.kegiatan', 'Kegiatan pembelajaran maksimal 2000 karakter.'],
    'note too long' => [['catatan' => str_repeat('a', 1001)], 'baris.0.catatan', 'Catatan maksimal 1000 karakter.'],
    'follow up too long' => [['tindak_lanjut' => str_repeat('a', 1001)], 'baris.0.tindak_lanjut', 'Tindak lanjut maksimal 1000 karakter.'],
]);

test('rejects materials and purposes that do not belong to the subject or selected material', function (string $case) {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $first = jurnalMaterial($teacher, $subject, 'materi pertama', 1, ['tujuan pertama']);
    $second = jurnalMaterial($teacher, $subject, 'materi kedua', 2, ['tujuan kedua']);
    $foreignSubject = MataPelajaranFactory::new()->create();
    $foreignMaterial = jurnalMaterial($teacher, $foreignSubject, 'materi mapel lain');
    $this->actingAs($teacher->user);

    $row = match ($case) {
        'purpose from another material' => [
            ...jurnalRow($first),
            'tujuan_pembelajaran_id' => $second->tujuanPembelajarans[0]->id,
        ],
        'material from another subject' => jurnalRow($foreignMaterial),
    };

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('baris', [$row])
        ->call('simpan')
        ->assertHasErrors()
        ->assertSee($case === 'purpose from another material'
            ? 'Tujuan pembelajaran tidak sesuai dengan lingkup materi yang dipilih.'
            : 'Lingkup materi tidak tersedia untuk mata pelajaran dan periode ini.')
        ->assertNotNotified();

    $this->assertDatabaseCount('jurnal_mengajars', 0);
})->with(['purpose from another material', 'material from another subject']);

test('rejects malformed journal rows without a server error', function (mixed $rows) {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->set('baris', $rows)
        ->call('simpan')
        ->assertHasErrors()
        ->assertNotNotified();

    $this->assertDatabaseCount('jurnal_mengajars', 0);
})->with([
    'row string' => [['bukan baris']],
    'row empty' => [[]],
]);

test('returns 404 for another teachers schedule or another teachers journal', function () {
    $teacher = jurnalTeacher();
    $ownSubject = MataPelajaranFactory::new()->create();
    $ownSchedule = jurnalSchedule($teacher, $ownSubject);
    $foreignSchedule = jurnalSchedule(jurnalTeacher(), MataPelajaranFactory::new()->create());
    $foreignJournal = JurnalMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $foreignSchedule->id,
        'tanggal' => '2026-10-18',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $foreignSchedule->id)->assertNotFound();
    Livewire::test(JurnalMengajar::class)->call('pilihJadwal', 999999)->assertNotFound();
    Livewire::test(JurnalMengajar::class)->set('tanggal', '2026-10-18')
        ->call('lihatJurnal', $foreignJournal->id)->assertNotFound();

    expect($ownSchedule->exists)->toBeTrue();
});

test('keeps a journal owned by the original teacher after schedule reassignment', function () {
    $schedule = jurnalSchedule(jurnalTeacher(), MataPelajaranFactory::new()->create());
    $journal = JurnalMengajarFactory::new()->create(['jadwal_pelajaran_id' => $schedule->id]);
    $newTeacher = jurnalTeacher();
    $schedule->update(['guru_id' => $newTeacher->id]);
    $this->actingAs($newTeacher->user);

    Livewire::test(JurnalMengajar::class)
        ->call('pilihJadwal', $schedule->id)
        ->assertForbidden();

    $this->assertDatabaseHas('jurnal_mengajars', ['id' => $journal->id, 'guru_id' => $journal->guru_id]);
});

test('shows only the teachers own journal history as read only', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'materi riwayat');
    $journal = JurnalMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18',
        'nama_mapel' => 'Mapel Riwayat Sendiri', 'nama_kelas' => 'Kelas Riwayat Sendiri',
    ]);
    JurnalMengajarBarisFactory::new()->create([
        'jurnal_mengajar_id' => $journal->id,
        'lingkup_materi_id' => $material->id,
        'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[0]->id,
        'lingkup_materi' => 'materi riwayat sendiri',
        'tujuan_pembelajaran' => 'tujuan riwayat sendiri',
        'kegiatan' => 'kegiatan riwayat sendiri',
        'catatan' => 'catatan riwayat sendiri',
        'tindak_lanjut' => 'tindak lanjut sendiri',
    ]);
    $foreignJournal = JurnalMengajarFactory::new()->create([
        'tanggal' => '2026-10-18', 'nama_mapel' => 'Mapel Guru Lain', 'nama_kelas' => 'Kelas Guru Lain',
    ]);
    JurnalMengajarBarisFactory::new()->create([
        'jurnal_mengajar_id' => $foreignJournal->id, 'kegiatan' => 'kegiatan rahasia guru lain',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)->set('tanggal', '2026-10-18')
        ->assertSee('Mapel Riwayat Sendiri')->assertDontSee('Mapel Guru Lain')
        ->call('lihatJurnal', $journal->id)
        ->assertSee('materi riwayat sendiri')->assertSee('tujuan riwayat sendiri')
        ->assertSee('kegiatan riwayat sendiri')->assertSee('catatan riwayat sendiri')
        ->assertSee('tindak lanjut sendiri')
        ->assertDontSee('kegiatan rahasia guru lain')
        ->assertDontSeeHtml('wire:submit="simpan"')
        ->assertDontSeeHtml('wire:click="tambahBaris"');

    $this->assertDatabaseCount('jurnal_mengajars', 2);
    $this->assertDatabaseCount('jurnal_mengajar_baris', 2);
});

test('keeps snapshot text after the source schedule material and purpose are deleted', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'materi sumber');
    $journal = JurnalMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18',
        'nama_mapel' => 'Mapel Snapshot',
    ]);
    JurnalMengajarBarisFactory::new()->create([
        'jurnal_mengajar_id' => $journal->id,
        'lingkup_materi_id' => $material->id,
        'tujuan_pembelajaran_id' => $material->tujuanPembelajarans[0]->id,
        'lingkup_materi' => 'materi tersimpan',
        'tujuan_pembelajaran' => 'tujuan tersimpan',
        'kegiatan' => 'kegiatan tersimpan',
    ]);
    $schedule->delete();
    $material->delete();
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)->set('tanggal', '2026-10-18')
        ->call('lihatJurnal', $journal->id)
        ->assertSee('materi tersimpan')->assertSee('tujuan tersimpan')->assertSee('kegiatan tersimpan');

    $this->assertDatabaseHas('jurnal_mengajars', ['id' => $journal->id, 'jadwal_pelajaran_id' => null]);
    $this->assertDatabaseHas('jurnal_mengajar_baris', [
        'jurnal_mengajar_id' => $journal->id, 'lingkup_materi_id' => null, 'tujuan_pembelajaran_id' => null,
    ]);
});

test('rejects a stale journal correction from a second tab', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);
    Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)
        ->set('baris', [jurnalRow($material)])->call('simpan')->assertHasNoErrors();
    $journal = JurnalMengajarModel::query()->sole();
    $tabA = Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)->assertSet('revisi', 1);
    $tabB = Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)->assertSet('revisi', 1);
    $tabB->set('baris.0.catatan', 'catatan tab lama');
    $tabA->set('baris.0.catatan', 'koreksi tab pertama')->call('simpan')->assertHasNoErrors();
    $this->assertDatabaseHas('jurnal_mengajars', ['id' => $journal->id, 'revisi' => 2]);

    $tabB->call('simpan')->assertHasErrors(['baris'])
        ->assertSee('Jurnal telah diperbarui dari halaman lain. Pilih ulang jadwal sebelum menyimpan.');

    $this->assertDatabaseCount('jurnal_mengajars', 1);
    $this->assertDatabaseCount('jurnal_mengajar_baris', 1);
    $this->assertDatabaseHas('jurnal_mengajar_baris', ['catatan' => 'koreksi tab pertama']);
    $this->assertDatabaseMissing('jurnal_mengajar_baris', ['catatan' => 'catatan tab lama']);
});

test('blocks journal writes outside today and resets the selected journal when the date changes', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $material = jurnalMaterial($teacher, $subject, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);
    $component = Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id)
        ->set('baris', [jurnalRow($material)]);
    $component->instance()->tanggal = '2026-10-18';

    expect(fn () => $component->instance()->simpan())->toThrow(ValidationException::class);

    $component->set('tanggal', '2026-10-18');
    $component->assertSet('jadwalId', null)->assertSet('baris', [])->assertSee('Mode riwayat.');
    $this->assertDatabaseCount('jurnal_mengajars', 0);

    Livewire::test(JurnalMengajar::class)->set('tanggal', '2026-10-20')
        ->assertHasErrors(['tanggal' => 'before_or_equal']);
});

test('protects locked selection properties and private helpers from client updates', function (string $property) {
    $teacher = jurnalTeacher();
    $schedule = jurnalSchedule($teacher, MataPelajaranFactory::new()->create());
    jurnalMaterial($teacher, $schedule->mataPelajaran, 'kontrak pembelajaran');
    $this->actingAs($teacher->user);
    $component = Livewire::test(JurnalMengajar::class)->call('pilihJadwal', $schedule->id);

    expect(fn () => $component->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['jadwalId', 'jurnalId', 'revisi']);

test('does not expose schedule and journal query helpers as Livewire actions', function (string $method) {
    $teacher = jurnalTeacher();
    $schedule = jurnalSchedule($teacher, MataPelajaranFactory::new()->create());
    $this->actingAs($teacher->user);
    $component = Livewire::test(JurnalMengajar::class);

    expect(fn () => $component->call($method, $schedule->id))->toThrow(MethodNotFoundException::class);
})->with(['todaySchedules', 'journalFor', 'availableMaterials']);

test('escapes journal snapshot text in the read-only history', function () {
    $teacher = jurnalTeacher();
    $subject = MataPelajaranFactory::new()->create();
    $schedule = jurnalSchedule($teacher, $subject);
    $journal = JurnalMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id, 'tanggal' => '2026-10-18',
    ]);
    JurnalMengajarBarisFactory::new()->create([
        'jurnal_mengajar_id' => $journal->id,
        'lingkup_materi' => '<script>lingkup()</script>',
        'tujuan_pembelajaran' => '<script>tujuan()</script>',
        'kegiatan' => '<img src=x onerror=alert(1)>',
        'catatan' => '<script>catatan()</script>',
        'tindak_lanjut' => '<script>tindak()</script>',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(JurnalMengajar::class)->set('tanggal', '2026-10-18')
        ->call('lihatJurnal', $journal->id)
        ->assertSee('<script>lingkup()</script>')
        ->assertSee('<script>tujuan()</script>')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>lingkup()</script>')
        ->assertDontSeeHtml('<script>tujuan()</script>')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>')
        ->assertDontSeeHtml('<script>catatan()</script>')
        ->assertDontSeeHtml('<script>tindak()</script>');
});
