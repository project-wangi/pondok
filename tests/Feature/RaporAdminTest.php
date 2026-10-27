<?php

use App\Filament\Pages\RaporAdmin;
use App\Filament\Resources\Gurus\Pages\EditGuru;
use App\Models\ERapor;
use App\Models\Guru;
use App\Models\Jabatan;
use App\Models\JadwalPelajaran;
use App\Models\JenisPeminatan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Murid;
use App\Models\RaporKepribadian;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ERaporFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Database\Factories\MuridFactory;
use Database\Factories\PresensiMuridFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

function raporAdminUser(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

/**
 * Satu kelas dengan dua mata pelajaran. Ahmad punya nilai di kedua mapel,
 * Bilal hanya di Matematika, Cholid belum punya nilai sama sekali.
 *
 * @return array{kelas: Kelas, guru: Guru, mapel: array<string, MataPelajaran>, jadwal: array<string, JadwalPelajaran>, siswa: array<string, Murid>}
 */
function raporAdminScenario(): array
{
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $guru = GuruFactory::new()->create([
        'user_id' => User::factory()->create(['name' => 'Ust. Wali'])->id,
        'kelas_id' => $kelas->id,
    ]);
    $kepala = GuruFactory::new()->create([
        'user_id' => User::factory()->create(['name' => 'Ust. Kepala'])->id,
    ]);
    $kepala->jabatans()->attach(Jabatan::create(['nama_jabatan' => 'Kepala Tim Manajemen', 'gaji' => 1000000]));

    $matematika = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    $arab = MataPelajaranFactory::new()->create(['nama_mapel' => 'Bahasa Arab']);

    $jadwalMatematika = JadwalPelajaranFactory::new()->create([
        'kelas_id' => $kelas->id,
        'guru_id' => $guru->id,
        'mata_pelajaran' => $matematika->id,
    ]);
    $jadwalArab = JadwalPelajaranFactory::new()->create([
        'kelas_id' => $kelas->id,
        'guru_id' => $guru->id,
        'mata_pelajaran' => $arab->id,
    ]);

    $ahmad = MuridFactory::new()->create([
        'kelas_id' => $kelas->id,
        'nama' => 'Ahmad',
        'nis' => '001',
        'nisn' => '1000000001',
        'nama_ayah' => 'Bapak Ahmad',
        'nama_ibu' => 'Ibu Ahmad',
    ]);
    $bilal = MuridFactory::new()->create([
        'kelas_id' => $kelas->id,
        'nama' => 'Bilal',
        'nis' => '002',
        'nisn' => '1000000002',
    ]);
    $cholid = MuridFactory::new()->create([
        'kelas_id' => $kelas->id,
        'nama' => 'Cholid',
        'nis' => '003',
        'nisn' => '1000000003',
        'nama_ayah' => 'Bapak Cholid',
        'status_ayah' => 'Meninggal',
        'nama_ibu' => 'Ibu Cholid',
        'status_ibu' => 'Hidup',
    ]);

    $raporMatematika = ERaporFactory::new()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $matematika->id,
        'bobot_formatif' => 0,
        'bobot_sumatif' => 0,
        'bobot_asts' => 0,
        'bobot_asas' => 100,
        'bobot_kehadiran' => 0,
        'bobot_sikap' => 0,
        'bobot_keaktifan' => 0,
        'kkm' => 75,
    ]);
    $raporMatematika->nilais()->createMany([
        ['murid_id' => $ahmad->id, 'nama_murid' => 'Ahmad', 'nis' => '001', 'asts' => 80, 'asas' => 90],
        ['murid_id' => $bilal->id, 'nama_murid' => 'Bilal', 'nis' => '002', 'asts' => 60, 'asas' => 60],
    ]);

    $raporArab = ERaporFactory::new()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $arab->id,
        'bobot_formatif' => 0,
        'bobot_sumatif' => 0,
        'bobot_asts' => 100,
        'bobot_asas' => 0,
        'bobot_kehadiran' => 0,
        'bobot_sikap' => 0,
        'bobot_keaktifan' => 0,
        'kkm' => 70,
    ]);
    $raporArab->nilais()->create([
        'murid_id' => $ahmad->id,
        'nama_murid' => 'Ahmad',
        'nis' => '001',
        'asts' => 70,
        'asas' => null,
    ]);

    // Nilai kehadiran tidak memengaruhi rapor contoh karena bobotnya 0,
    // tetapi jumlah sakit/izin/alpa tetap harus tampil di rapor.
    foreach (['Hadir', 'Sakit', 'Izin', 'Alpa'] as $index => $status) {
        $sesi = SesiMengajarFactory::new()->create([
            'jadwal_pelajaran_id' => $jadwalMatematika->id,
            'tanggal' => '2026-10-0'.($index + 1),
        ]);
        PresensiMuridFactory::new()->create([
            'sesi_mengajar_id' => $sesi->id,
            'murid_id' => $ahmad->id,
            'status' => $status,
        ]);
    }

    $peminatan = JenisPeminatan::create(['nama_peminatan' => "Tahfizh Al-Qur'an", 'nominal' => 150000]);
    $ahmad->jenisPeminatans()->attach($peminatan->id);

    return [
        'kelas' => $kelas,
        'guru' => $guru,
        'mapel' => ['matematika' => $matematika, 'arab' => $arab],
        'jadwal' => ['matematika' => $jadwalMatematika, 'arab' => $jadwalArab],
        'siswa' => ['ahmad' => $ahmad, 'bilal' => $bilal, 'cholid' => $cholid],
    ];
}

/** @return list<array<int, mixed>> */
function raporWorkbookRows(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
    } finally {
        $reader->close();
    }

    return $rows;
}

/** @param array<string, mixed> $overrides */
function raporGuruWithPermission(User $admin, array $overrides = []): Guru
{
    foreach (['ViewAny:Guru', 'View:Guru', 'Update:Guru'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $admin->givePermissionTo(['ViewAny:Guru', 'View:Guru', 'Update:Guru']);

    return GuruFactory::new()->create([
        'agama' => 'Islam',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'laki-laki',
        ...$overrides,
    ]);
}

test('the student report page exposes navigation and restricts access', function () {
    expect(RaporAdmin::getNavigationGroup())->toBe('Rapor')
        ->and(RaporAdmin::getNavigationLabel())->toBe('Rapor Siswa')
        ->and(RaporAdmin::getSlug())->toBe('rapor')
        ->and(RaporAdmin::shouldRegisterNavigation())->toBeTrue();

    $this->get(RaporAdmin::getUrl())->assertRedirectToRoute('filament.admin.auth.login');

    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);
    $this->get(RaporAdmin::getUrl())->assertForbidden();
    Livewire::test(RaporAdmin::class)->assertForbidden();

    $this->actingAs(raporAdminUser());
    $this->get(RaporAdmin::getUrl())->assertOk()->assertSee('Rapor Siswa');
});

test('orders classes by grade instead of alphabetically', function () {
    $this->actingAs(raporAdminUser());
    KelasFactory::new()->create(['nama_kelas' => 'XII']);
    KelasFactory::new()->create(['nama_kelas' => 'VII']);
    KelasFactory::new()->create(['nama_kelas' => 'X']);
    KelasFactory::new()->create(['nama_kelas' => 'IX']);

    $names = Livewire::test(RaporAdmin::class)
        ->viewData('kelasList')
        ->pluck('kelas.nama_kelas')
        ->all();

    expect($names)->toBe(['VII', 'IX', 'X', 'XII']);
});

test('ranks students by the average of submitted e-report scores', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $component = Livewire::test(RaporAdmin::class)
        ->assertSee('VII')
        ->assertSee('2 mapel terkirim')
        ->call('pilihKelas', $data['kelas']->id)
        ->assertSee('Peringkat kelas VII')
        ->assertSee('Ahmad')
        ->assertSee('Bilal')
        ->assertSee('Cholid');

    $rows = $component->viewData('daftarMurid');

    expect($rows->pluck('murid.nama')->all())->toBe(['Ahmad', 'Bilal', 'Cholid'])
        ->and($rows->pluck('peringkat')->all())->toBe([1, 2, null])
        ->and($rows->pluck('rata_rata')->all())->toBe([80, 60, null])
        ->and($rows->pluck('jumlah_mapel')->all())->toBe([2, 1, 0]);
});

test('shows every subject while only filling scores that were submitted', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->assertSee('LAPORAN HASIL BELAJAR')
        ->assertSee('UJIAN AKHIR SEMESTER GANJIL 2026/2027')
        ->assertSee('NISM')
        ->assertSee('Ust. Wali')
        ->assertSee('Ust. Kepala')
        ->assertSee('Bapak Ahmad');

    $rapor = $component->viewData('rapor');
    $subjects = collect($rapor['subjects']);

    expect($subjects->pluck('nama')->all())->toBe(['Bahasa Arab', 'Matematika'])
        ->and($subjects->firstWhere('nama', 'Matematika'))->toMatchArray([
            'kkm' => 75,
            'angka' => 90,
            'huruf' => 'A',
        ])
        ->and($subjects->firstWhere('nama', 'Bahasa Arab'))->toMatchArray([
            'kkm' => 70,
            'angka' => 70,
            'huruf' => 'C',
        ])
        ->and($rapor['jumlah'])->toBe(160)
        ->and($rapor['rata_rata'])->toBe(80)
        ->and($rapor['peringkat'])->toBe(1)
        ->and($rapor['jumlah_siswa'])->toBe(3)
        ->and($rapor['student']['no_absen'])->toBe(1)
        ->and($rapor['student']['nis'])->toBe('001')
        ->and($rapor['student']['nisn'])->toBe('1000000001')
        ->and($rapor['class'])->toBe('VII')
        ->and($rapor['peminatan'])->toContain("Tahfizh Al-Qur'an")
        ->and($rapor['ketidakhadiran'])->toBe(['sakit' => 1, 'izin' => 1, 'alpa' => 1])
        ->and($rapor['wali_kelas'])->toBe('Ust. Wali')
        ->and($rapor['kepala_madrasah'])->toBe('Ust. Kepala')
        ->and($rapor['orang_tua'])->toBe('Bapak Ahmad');

    $component->call('pilihMurid', $data['siswa']['cholid']->id);
    $empty = $component->viewData('rapor');

    expect(collect($empty['subjects'])->pluck('angka')->all())->toBe([null, null])
        ->and(collect($empty['subjects'])->pluck('huruf')->all())->toBe([null, null])
        ->and($empty['jumlah'])->toBeNull()
        ->and($empty['rata_rata'])->toBeNull()
        ->and($empty['peringkat'])->toBeNull()
        ->and($empty['orang_tua'])->toBe('Ibu Cholid');
});

test('converts scores into report letters', function () {
    expect(RaporKepribadian::hurufUntuk(100))->toBe('A')
        ->and(RaporKepribadian::hurufUntuk(90))->toBe('A')
        ->and(RaporKepribadian::hurufUntuk(89))->toBe('B')
        ->and(RaporKepribadian::hurufUntuk(80))->toBe('B')
        ->and(RaporKepribadian::hurufUntuk(79))->toBe('C')
        ->and(RaporKepribadian::hurufUntuk(70))->toBe('C')
        ->and(RaporKepribadian::hurufUntuk(69))->toBe('D')
        ->and(RaporKepribadian::hurufUntuk(60))->toBe('D')
        ->and(RaporKepribadian::hurufUntuk(59))->toBe('E')
        ->and(RaporKepribadian::hurufUntuk(null))->toBeNull();
});

test('saves numeric personality scores and generates the letters', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->set('kepribadian.kelakuan', '95')
        ->set('kepribadian.kerajinan', '88')
        ->set('kepribadian.kebersihan', '79')
        ->set('kepribadian.kerapian', '65')
        ->set('kepribadian.kedisiplinan', '50')
        ->call('simpanKepribadian')
        ->assertHasNoErrors();

    $record = RaporKepribadian::query()->firstOrFail();

    expect($record->murid_id)->toBe($data['siswa']['ahmad']->id)
        ->and($record->semester)->toBe('Ganjil')
        ->and($record->tahun_ajaran)->toBe('2026/2027')
        ->and($record->kelakuan)->toBe(95)
        ->and($record->kerajinan)->toBe(88)
        ->and($record->kebersihan)->toBe(79)
        ->and($record->kerapian)->toBe(65)
        ->and($record->kedisiplinan)->toBe(50);

    $kepribadian = collect($component->viewData('rapor')['kepribadian']);

    expect($kepribadian->pluck('huruf')->all())->toBe(['A', 'B', 'C', 'D', 'E']);

    Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->assertSet('kepribadian.kelakuan', '95')
        ->assertSet('kepribadian.kedisiplinan', '50');
});

test('rejects personality scores outside the zero to one hundred range', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->set('kepribadian.kelakuan', '101')
        ->set('kepribadian.kerajinan', '80')
        ->set('kepribadian.kebersihan', '80')
        ->set('kepribadian.kerapian', '80')
        ->set('kepribadian.kedisiplinan', '80')
        ->call('simpanKepribadian')
        ->assertHasErrors(['kepribadian.kelakuan' => 'between']);

    expect(RaporKepribadian::query()->count())->toBe(0);
});

test('downloads an excel report with identity, scores, and personality', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->set('kepribadian.kelakuan', '95')
        ->set('kepribadian.kerajinan', '90')
        ->set('kepribadian.kebersihan', '85')
        ->set('kepribadian.kerapian', '80')
        ->set('kepribadian.kedisiplinan', '75')
        ->call('simpanKepribadian')
        ->call('export')
        ->assertFileDownloaded('rapor-001-ganjil-2026-2027.xlsx');

    $download = $component->effects['download'];
    $path = tempnam(sys_get_temp_dir(), 'rapor-test-');
    file_put_contents($path, base64_decode($download['content']));
    $rows = raporWorkbookRows($path);
    unlink($path);

    $flat = collect($rows)->flatten()->filter(fn (mixed $value): bool => $value !== '' && $value !== null);

    expect($flat)->toContain('LAPORAN HASIL BELAJAR')
        ->toContain('Ahmad')
        ->toContain('VII')
        ->toContain('Matematika')
        ->toContain('Ust. Wali')
        ->toContain('Ust. Kepala')
        ->toContain('Bapak Ahmad');

    $matematika = collect($rows)->first(fn (array $row): bool => ($row[1] ?? null) === 'Matematika');
    $kepribadian = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Kelakuan');

    expect($matematika)->not->toBeNull()
        ->and((int) $matematika[2])->toBe(75)
        ->and((int) $matematika[3])->toBe(90)
        ->and($matematika[4])->toBe('A')
        ->and($kepribadian)->not->toBeNull()
        ->and((int) $kepribadian[2])->toBe(95)
        ->and($kepribadian[3])->toBe('A');
});

test('shows the homeroom class field only when Wali Kelas is selected', function () {
    $admin = User::factory()->create();
    $guru = raporGuruWithPermission($admin);
    $bendahara = Jabatan::create(['nama_jabatan' => 'Bendahara', 'gaji' => 500000]);
    $wali = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 250000]);
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VIII']);
    $this->actingAs($admin);

    Livewire::test(EditGuru::class, ['record' => $guru->getRouteKey()])
        ->fillForm(['jabatans' => [$bendahara->id]])
        ->assertFormFieldHidden('kelas_id')
        ->fillForm(['jabatans' => [$wali->id]])
        ->assertFormFieldVisible('kelas_id')
        ->fillForm(['kelas_id' => $kelas->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($guru->fresh()->kelas_id)->toBe($kelas->id);
});

test('treats every position named Wali Kelas as homeroom', function () {
    $admin = User::factory()->create();
    $guru = raporGuruWithPermission($admin);
    Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 250000]);
    $duplicate = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 250000]);
    $this->actingAs($admin);

    Livewire::test(EditGuru::class, ['record' => $guru->getRouteKey()])
        ->fillForm(['jabatans' => [$duplicate->id]])
        ->assertFormFieldVisible('kelas_id');
});

test('requires a class when Wali Kelas is selected and clears it when removed', function () {
    $admin = User::factory()->create();
    $guru = raporGuruWithPermission($admin);
    $bendahara = Jabatan::create(['nama_jabatan' => 'Bendahara', 'gaji' => 500000]);
    $wali = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 250000]);
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'IX']);
    $guru->update(['kelas_id' => $kelas->id]);
    $this->actingAs($admin);

    Livewire::test(EditGuru::class, ['record' => $guru->getRouteKey()])
        ->fillForm(['jabatans' => [$wali->id], 'kelas_id' => null])
        ->call('save')
        ->assertHasFormErrors(['kelas_id']);

    Livewire::test(EditGuru::class, ['record' => $guru->getRouteKey()])
        ->fillForm(['jabatans' => [$bendahara->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($guru->fresh()->kelas_id)->toBeNull();
});

test('prevents two teachers from becoming homeroom of the same class', function () {
    $admin = User::factory()->create();
    $first = raporGuruWithPermission($admin);
    $second = raporGuruWithPermission($admin);
    $wali = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 250000]);
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'X']);
    $first->update(['kelas_id' => $kelas->id]);
    $this->actingAs($admin);

    Livewire::test(EditGuru::class, ['record' => $second->getRouteKey()])
        ->fillForm(['jabatans' => [$wali->id], 'kelas_id' => $kelas->id])
        ->call('save')
        ->assertHasFormErrors(['kelas_id']);
});

test('filters reports by the selected semester and academic year', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $old = ERaporFactory::new()->create([
        'guru_id' => $data['guru']->id,
        'kelas_id' => $data['kelas']->id,
        'mata_pelajaran_id' => $data['mapel']['matematika']->id,
        'semester' => 'Genap',
        'tahun_ajaran' => '2025/2026',
        'bobot_formatif' => 0,
        'bobot_sumatif' => 0,
        'bobot_asts' => 0,
        'bobot_asas' => 100,
        'bobot_kehadiran' => 0,
        'bobot_sikap' => 0,
        'bobot_keaktifan' => 0,
        'kkm' => 75,
    ]);
    $old->nilais()->create([
        'murid_id' => $data['siswa']['ahmad']->id,
        'nama_murid' => 'Ahmad',
        'nis' => '001',
        'asts' => null,
        'asas' => 50,
    ]);

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->assertSee('UJIAN AKHIR SEMESTER GANJIL 2026/2027');

    expect(collect($component->viewData('rapor')['subjects'])->firstWhere('nama', 'Matematika')['angka'])->toBe(90)
        ->and($component->viewData('tahunAjaranOptions'))->toContain('2025/2026')
        ->and($component->viewData('tahunAjaranOptions'))->toContain('2026/2027');

    $component->set('semester', 'Genap')
        ->set('tahunAjaran', '2025/2026')
        ->assertSee('UJIAN AKHIR SEMESTER GENAP 2025/2026');

    $rapor = $component->viewData('rapor');
    $matematika = collect($rapor['subjects'])->firstWhere('nama', 'Matematika');

    expect($rapor['semester'])->toBe('Genap')
        ->and($rapor['tahun_ajaran'])->toBe('2025/2026')
        ->and($matematika['angka'])->toBe(50)
        ->and($rapor['jumlah'])->toBe(50);
});

test('falls back to the active period when the filter is invalid', function () {
    $this->actingAs(raporAdminUser());

    Livewire::test(RaporAdmin::class)
        ->set('semester', 'Tidak valid')
        ->assertSet('semester', 'Ganjil')
        ->set('tahunAjaran', '1999/2000')
        ->assertSet('tahunAjaran', '2026/2027');
});

test('reloads personality values when the period changes', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id)
        ->set('kepribadian.kelakuan', '95')
        ->set('kepribadian.kerajinan', '90')
        ->set('kepribadian.kebersihan', '85')
        ->set('kepribadian.kerapian', '80')
        ->set('kepribadian.kedisiplinan', '75')
        ->call('simpanKepribadian')
        ->assertSet('kepribadian.kelakuan', '95');

    $component->set('semester', 'Genap')
        ->assertSet('kepribadian.kelakuan', '')
        ->assertSet('kepribadian.kedisiplinan', '')
        ->set('semester', 'Ganjil')
        ->assertSet('kepribadian.kelakuan', '95')
        ->assertSet('kepribadian.kedisiplinan', '75');
});

test('does not leak scores from other semesters', function () {
    $this->actingAs(raporAdminUser());
    $data = raporAdminScenario();

    ERapor::query()->update(['semester' => 'Genap', 'tahun_ajaran' => '2025/2026']);

    $component = Livewire::test(RaporAdmin::class)
        ->call('pilihKelas', $data['kelas']->id)
        ->call('pilihMurid', $data['siswa']['ahmad']->id);

    $rapor = $component->viewData('rapor');

    expect(collect($rapor['subjects'])->pluck('angka')->all())->toBe([null, null])
        ->and($rapor['jumlah'])->toBeNull();
});
