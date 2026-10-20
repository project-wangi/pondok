<?php

use App\Filament\Pages\InputLingkupMateri;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\LingkupMateriFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\TujuanPembelajaranFactory;
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

/** @param array<string, mixed> $attributes */
function inputMateriTeacher(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

/** @param array<string, mixed> $attributes */
function inputMateriSchedule(Guru $teacher, MataPelajaran $subject, array $attributes = []): JadwalPelajaran
{
    return JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'mata_pelajaran' => $subject->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        ...$attributes,
    ]);
}

function inputMateriRow(string $material, array $purposes = ['siswa memahami materi']): array
{
    return [
        'materi' => $material,
        'tujuan' => array_pad($purposes, 4, ''),
    ];
}

test('shows the requested menu label under the Pembelajaran group', function () {
    expect(InputLingkupMateri::getNavigationGroup())->toBe('Pembelajaran')
        ->and(InputLingkupMateri::getNavigationLabel())->toBe('Input Lingkup Materi dan Tujuan Pembelajaran');
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(route('filament.admin.pages.input-lingkup-materi'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(route('filament.admin.pages.input-lingkup-materi'))->assertForbidden();
    Livewire::test(InputLingkupMateri::class)->assertForbidden();
});

test('shows an honest empty state when the running period has no teaching schedule', function () {
    $teacher = inputMateriTeacher();
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertOk()
        ->assertSee('Tidak ada mata pelajaran yang diajar pada semester Ganjil tahun ajaran 2026/2027.')
        ->assertSee('Lingkup Materi dan Tujuan Pembelajaran');
});

test('lists only subjects the teacher teaches in the running period and removes duplicates', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Tahfidz Al-Qur\'an']);
    $duplicateClassSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);
    $oldSubject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Semester Lama']);
    inputMateriSchedule($teacher, $subject);
    inputMateriSchedule($teacher, $subject);
    inputMateriSchedule($teacher, $duplicateClassSubject);
    inputMateriSchedule($teacher, $oldSubject, ['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);
    inputMateriSchedule(inputMateriTeacher(), MataPelajaranFactory::new()->create(['nama_mapel' => 'Mapel Guru Lain']));
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSee('Tahfidz Al-Qur\'an')
        ->assertSee('Bahasa Arab')
        ->assertDontSee('Mapel Semester Lama')
        ->assertDontSee('Mapel Guru Lain')
        ->assertSeeHtml('value="'.$subject->id.'"')
        ->assertSeeHtml('value="'.$duplicateClassSubject->id.'"')
        ->assertDontSeeHtml('value="'.$oldSubject->id.'"');
});

test('selects the only subject automatically and starts with one empty row', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSet('mataPelajaranId', $subject->id)
        ->assertSet('lingkup', [['materi' => '', 'tujuan' => ['', '', '', '']]])
        ->assertSee('Tujuan 4')
        ->assertDontSee('Tujuan 5');
});

test('loads saved materials and pads purposes to four fields in order', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $first = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->create([
        'materi' => 'kontrak pembelajaran', 'urutan' => 2,
    ]);
    TujuanPembelajaranFactory::new()->create(['lingkup_materi_id' => $first->id, 'urutan' => 1, 'teks' => 'siswa mampu menaati peraturan']);
    TujuanPembelajaranFactory::new()->create(['lingkup_materi_id' => $first->id, 'urutan' => 2, 'teks' => 'siswa mampu mengikuti pembelajaran']);
    $second = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->create([
        'materi' => 'pengantar makharijul huruf', 'urutan' => 1,
    ]);
    TujuanPembelajaranFactory::new()->create(['lingkup_materi_id' => $second->id, 'urutan' => 1, 'teks' => 'siswa mengenal makhraj huruf']);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSet('mataPelajaranId', $subject->id)
        ->assertSet('lingkup', [
            ['materi' => 'pengantar makharijul huruf', 'tujuan' => ['siswa mengenal makhraj huruf']],
            ['materi' => 'kontrak pembelajaran', 'tujuan' => [
                'siswa mampu menaati peraturan',
                'siswa mampu mengikuti pembelajaran',
            ]],
        ]);
});

test('saves ordered materials and purposes for the running period', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->set('lingkup', [
            inputMateriRow('kontrak pembelajaran', ['siswa memahami kesepakatan kelas', '', 'siswa mengikuti aturan']),
            inputMateriRow('makharijul huruf', ['siswa mengenal makhraj huruf']),
        ])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Lingkup materi tersimpan');

    $this->assertDatabaseCount('lingkup_materis', 2);
    $this->assertDatabaseCount('tujuan_pembelajarans', 3);
    $this->assertDatabaseHas('lingkup_materis', [
        'guru_id' => $teacher->id, 'mata_pelajaran_id' => $subject->id,
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'urutan' => 1, 'materi' => 'kontrak pembelajaran',
    ]);
    $material = LingkupMateri::query()->where('urutan', 1)->sole();
    expect($material->tujuanPembelajarans->pluck('teks')->all())->toBe([
        'siswa memahami kesepakatan kelas',
        'siswa mengikuti aturan',
    ]);
});

test('replaces saved materials instead of duplicating them', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);
    $component = Livewire::test(InputLingkupMateri::class)
        ->set('lingkup', [inputMateriRow('materi awal')])
        ->call('simpan')->assertHasNoErrors();

    $component->set('lingkup', [inputMateriRow('materi pengganti')])->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseCount('lingkup_materis', 1);
    $this->assertDatabaseCount('tujuan_pembelajarans', 1);
    $this->assertDatabaseHas('lingkup_materis', ['materi' => 'materi pengganti']);
    $this->assertDatabaseMissing('lingkup_materis', ['materi' => 'materi awal']);
});

test('rejects invalid purposes without saving any material', function (array $row, string $field, string $message) {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->set('lingkup', [$row])
        ->call('simpan')
        ->assertHasErrors([$field])
        ->assertSee($message)
        ->assertNotNotified();

    $this->assertDatabaseCount('lingkup_materis', 0);
    $this->assertDatabaseCount('tujuan_pembelajarans', 0);
})->with([
    'uppercase first letter' => [
        ['materi' => 'kontrak', 'tujuan' => ['Siswa memahami aturan', '', '', '']],
        'lingkup.0.tujuan.0',
        'Tujuan pembelajaran harus diawali dengan huruf kecil.',
    ],
    'trailing period' => [
        ['materi' => 'kontrak', 'tujuan' => ['siswa memahami aturan.', '', '', '']],
        'lingkup.0.tujuan.0',
        'Tujuan pembelajaran tidak boleh diakhiri tanda titik.',
    ],
    'more than 100 characters' => [
        ['materi' => 'kontrak', 'tujuan' => [str_repeat('a', 101), '', '', '']],
        'lingkup.0.tujuan.0',
        'Tujuan pembelajaran maksimal 100 karakter.',
    ],
    'empty purpose set' => [
        ['materi' => 'kontrak', 'tujuan' => ['', '', '', '']],
        'lingkup.0.tujuan',
        'Isi minimal satu tujuan pembelajaran untuk setiap lingkup materi.',
    ],
    'empty material' => [
        ['materi' => '', 'tujuan' => ['siswa memahami aturan', '', '', '']],
        'lingkup.0.materi',
        'Lingkup materi wajib diisi.',
    ],
]);

test('rejects a subject that the teacher does not teach in the running period', function () {
    $teacher = inputMateriTeacher();
    $own = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $own);
    $foreign = MataPelajaranFactory::new()->create();
    inputMateriSchedule(inputMateriTeacher(), $foreign);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->set('mataPelajaranId', $foreign->id)
        ->assertNotFound();
});

test('adds and removes purposes beyond the initial four and saves them in order', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    $component = Livewire::test(InputLingkupMateri::class)
        ->assertCount('lingkup.0.tujuan', 4)
        ->call('tambahTujuan', 0)
        ->assertCount('lingkup.0.tujuan', 5)
        ->assertSee('Tujuan 5')
        ->set('lingkup.0.materi', 'kontrak pembelajaran')
        ->set('lingkup.0.tujuan.0', 'siswa memahami aturan kelas')
        ->set('lingkup.0.tujuan.4', 'siswa menerapkan kesepakatan belajar')
        ->call('simpan')->assertHasNoErrors();

    $material = LingkupMateri::query()->sole();
    expect($material->tujuanPembelajarans->pluck('teks')->all())->toBe([
        'siswa memahami aturan kelas',
        'siswa menerapkan kesepakatan belajar',
    ])->and($material->tujuanPembelajarans->pluck('urutan')->all())->toBe([1, 2]);

    $component->call('hapusTujuan', 0, 1)
        ->assertCount('lingkup.0.tujuan', 1)
        ->set('lingkup.0.tujuan', ['satu-satunya tujuan'])
        ->call('hapusTujuan', 0, 0)
        ->assertSet('lingkup.0.tujuan', ['']);
});

test('keeps saved material isolated by teacher and academic period', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->create([
        'semester' => 'Genap', 'tahun_ajaran' => '2025/2026', 'materi' => 'materi semester lama',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSet('lingkup', [['materi' => '', 'tujuan' => ['', '', '', '']]])
        ->assertDontSee('materi semester lama')
        ->set('lingkup', [inputMateriRow('materi semester berjalan')])
        ->call('simpan')->assertHasNoErrors();

    $this->assertDatabaseHas('lingkup_materis', [
        'semester' => 'Genap', 'tahun_ajaran' => '2025/2026', 'materi' => 'materi semester lama',
    ]);
    $this->assertDatabaseHas('lingkup_materis', [
        'semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027', 'materi' => 'materi semester berjalan',
    ]);
});

test('adds and removes material rows but keeps one editable row', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    $component = Livewire::test(InputLingkupMateri::class)
        ->call('tambahLingkup')
        ->assertCount('lingkup', 2)
        ->call('hapusLingkup', 0)
        ->assertCount('lingkup', 1)
        ->call('hapusLingkup', 0)
        ->assertCount('lingkup', 1);

    expect($component->get('lingkup')[0])->toBe(['materi' => '', 'tujuan' => ['', '', '', '']]);
});

test('rejects malformed rows and nested purposes without a server error', function (array $rows) {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->set('lingkup', $rows)
        ->call('simpan')
        ->assertHasErrors()
        ->assertNotNotified();

    $this->assertDatabaseCount('lingkup_materis', 0);
    $this->assertDatabaseCount('tujuan_pembelajarans', 0);
})->with([
    'row is not an array' => [['bukan baris']],
    'purpose is nested' => [[['materi' => 'kontrak', 'tujuan' => [['nested'], '', '', '']]]],
]);

test('escapes material and purpose text rendered from stored records', function () {
    $teacher = inputMateriTeacher();
    $subject = MataPelajaranFactory::new()->create();
    inputMateriSchedule($teacher, $subject);
    $material = LingkupMateriFactory::new()->forTeacher($teacher)->forSubject($subject)->create([
        'materi' => '<script>materi()</script>',
    ]);
    TujuanPembelajaranFactory::new()->create([
        'lingkup_materi_id' => $material->id, 'teks' => '<script>tujuan()</script>',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(InputLingkupMateri::class)
        ->assertSet('lingkup', [
            ['materi' => '<script>materi()</script>', 'tujuan' => ['<script>tujuan()</script>']],
        ])
        ->assertDontSeeHtml('<script>materi()</script>')
        ->assertDontSeeHtml('<script>tujuan()</script>');
});
