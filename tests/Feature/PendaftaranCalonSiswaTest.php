<?php

use App\Filament\Resources\CalonSiswas\CalonSiswaResource;
use App\Filament\Resources\CalonSiswas\Pages\EditCalonSiswa;
use App\Filament\Resources\CalonSiswas\Pages\ListCalonSiswas;
use App\Filament\Siswa\Pages\DataPendaftaran;
use App\Filament\Siswa\Pages\StatusPendaftaran;
use App\Filament\Siswa\Pages\UbahKataSandi;
use App\Mail\KataSandiBaruCalonSiswa;
use App\Mail\PendaftaranCalonSiswa;
use App\Models\CalonSiswa;
use App\Models\JenisPeminatan;
use App\Models\Murid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CalonSiswaFactory;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));
    Storage::fake('r2');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function calonAdmin(): User
{
    return adminPanelUser('ViewAny:CalonSiswa', 'View:CalonSiswa', 'Update:CalonSiswa');
}

function panelSiswa(): void
{
    Filament::setCurrentPanel('siswa');
    Filament::bootCurrentPanel();
}

/** @return array<string, string> */
function calonPayload(array $overrides = []): array
{
    return array_replace([
        'nama' => 'Ahmad Calon',
        'jenis_kelamin' => 'laki-laki',
        'email' => 'ahmad.calon@example.test',
        'nohp' => '081234567891',
    ], $overrides);
}

/** @return array<string, mixed> */
function calonDataLengkap(): array
{
    return [
        'nik' => '3273010101010001',
        'no_kk' => '3273010101010002',
        'nisn' => '1234567890',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2012-05-14',
        'agama' => 'Islam',
        'alamat' => 'Jl. Pesantren No. 1',
        'nama_ayah' => 'Bapak Calon',
        'status_ayah' => 'Hidup',
        'nama_ibu' => 'Ibu Calon',
        'status_ibu' => 'Hidup',
        'kk' => 'calon-dokumen/kk/kk.pdf',
        'ijazah' => 'calon-dokumen/ijazah/ijazah.pdf',
    ];
}

/** @return array<string, mixed> */
function calonDataForm(): array
{
    return [
        ...calonDataLengkap(),
        'nohp_siswa' => '081234567892',
        'nik_ayah' => '3273010101010003',
        'pekerjaan_ayah' => 'Petani',
        'nohp_ayah' => '081234567893',
        'nik_ibu' => '3273010101010004',
        'pekerjaan_ibu' => 'Ibu rumah tangga',
        'nohp_ibu' => '081234567894',
        'kk' => ['calon-dokumen/kk/kk.pdf'],
        'ijazah' => ['calon-dokumen/ijazah/ijazah.pdf'],
    ];
}

test('shows the public registration form', function () {
    $this->get(route('pendaftaran.create'))
        ->assertOk()
        ->assertSee('Pendaftaran Calon Murid Baru')
        ->assertSee('Nomor WhatsApp Pendaftar')
        ->assertSee('Masukkan Nama Lengkap Sesuai Akta Kelahiran.')
        ->assertSee('oninput="this.value = this.value.replace(/\\D/g, \'\')"', false)
        ->assertDontSee('Nomor Kartu Keluarga')
        ->assertDontSee('name="kk"', false);
});

test('registers a candidate and emails the login credentials', function () {
    Mail::fake();

    $response = $this->post(route('pendaftaran.store'), calonPayload());

    $response->assertRedirect(route('pendaftaran.sukses'))
        ->assertSessionHas('pendaftaran_nomor');

    $user = User::query()->where('email', 'ahmad.calon@example.test')->firstOrFail();
    $calon = CalonSiswa::query()->where('user_id', $user->id)->firstOrFail();

    expect($user->name)->toBe('Ahmad Calon')
        ->and($user->getRoleNames()->isEmpty())->toBeTrue()
        ->and($calon->status)->toBe(CalonSiswa::STATUS_MENUNGGU)
        ->and($calon->nomor_pendaftaran)->toStartWith('PPDB-')
        ->and($calon->nohp)->toBe('081234567891')
        ->and($calon->nik)->toBeNull()
        ->and($calon->nisn)->toBeNull()
        ->and($calon->kk)->toBeNull()
        ->and($calon->dataLengkap())->toBeFalse()
        ->and($calon->dilengkapi_pada)->toBeNull();

    Mail::assertSent(PendaftaranCalonSiswa::class, function (PendaftaranCalonSiswa $mail) use ($user, $calon): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && $mail->calon->is($calon)
            && str_contains($mail->tautanMasuk, '/pendaftaran/akun/')
            && Hash::check($mail->password, $user->fresh()->password);
    });
});

test('rejects invalid registration data', function (array $overrides, string $field) {
    Mail::fake();

    $this->post(route('pendaftaran.store'), calonPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->count())->toBe(0)
        ->and(CalonSiswa::query()->count())->toBe(0);

    Mail::assertNothingSent();
})->with([
    'nomor memakai spasi' => [['nohp' => '0812 3456 7891'], 'nohp'],
    'nomor memakai strip' => [['nohp' => '0812-3456-7891'], 'nohp'],
    'nomor tidak diawali nol' => [['nohp' => '81234567891'], 'nohp'],
    'email tidak valid' => [['email' => 'bukan-email'], 'email'],
    'jenis kelamin tidak valid' => [['jenis_kelamin' => 'lain'], 'jenis_kelamin'],
]);

test('rejects a duplicate registration email', function () {
    User::factory()->create(['email' => 'ahmad.calon@example.test']);
    Mail::fake();

    $this->post(route('pendaftaran.store'), calonPayload())
        ->assertSessionHasErrors('email');

    expect(CalonSiswa::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('logs a candidate in through the emailed one-time link into the student panel', function () {
    Mail::fake();
    $this->post(route('pendaftaran.store'), calonPayload());

    $mail = Mail::sent(PendaftaranCalonSiswa::class)->first();

    $this->get($mail->tautanMasuk)
        ->assertRedirect(StatusPendaftaran::getUrl(panel: 'siswa'));

    $this->assertAuthenticatedAs($mail->user);

    $this->get(StatusPendaftaran::getUrl(panel: 'siswa'))
        ->assertOk()
        ->assertSee('Ahmad Calon')
        ->assertSee('PPDB-')
        ->assertSee('siswa-panel', false)
        ->assertSee('Kelengkapan Data')
        ->assertSee('Dokumen Pendaftaran');
});

test('rejects a login link that has already been used', function () {
    Mail::fake();
    $this->post(route('pendaftaran.store'), calonPayload());

    $mail = Mail::sent(PendaftaranCalonSiswa::class)->first();

    $this->get($mail->tautanMasuk)->assertRedirect(StatusPendaftaran::getUrl(panel: 'siswa'));
    Auth::logout();

    $this->get($mail->tautanMasuk)
        ->assertRedirect(route('filament.siswa.auth.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('rejects an expired login link', function () {
    $calon = CalonSiswaFactory::new()->create();
    $token = $calon->buatTautanMasuk();

    $this->travel(CalonSiswa::TAUTAN_MASUK_HARI + 1)->days();

    $this->get(route('pendaftaran.masuk.tautan', $token))
        ->assertRedirect(route('filament.siswa.auth.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('lets a candidate log in through the student panel login', function () {
    panelSiswa();
    $calon = CalonSiswaFactory::new()->create();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $calon->user->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($calon->user);
});

test('keeps staff accounts out of the student panel login', function () {
    panelSiswa();
    $admin = calonAdmin();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

test('protects the student panel pages', function () {
    $this->get(StatusPendaftaran::getUrl(panel: 'siswa'))
        ->assertRedirect(route('filament.siswa.auth.login'));

    $this->actingAs(User::factory()->create());

    $this->get(StatusPendaftaran::getUrl(panel: 'siswa'))->assertForbidden();
    $this->get(DataPendaftaran::getUrl(panel: 'siswa'))->assertForbidden();
});

test('lets a candidate complete the administration data from the student panel', function () {
    panelSiswa();
    $calon = CalonSiswaFactory::new()->create();
    $this->actingAs($calon->user);

    Livewire::test(DataPendaftaran::class)
        ->assertSeeHtml('oninput')
        ->fillForm(calonDataForm())
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(StatusPendaftaran::getUrl(panel: 'siswa'));

    $calon->refresh();

    expect($calon->nik)->toBe('3273010101010001')
        ->and($calon->no_kk)->toBe('3273010101010002')
        ->and($calon->nisn)->toBe('1234567890')
        ->and($calon->tempat_lahir)->toBe('Bandung')
        ->and($calon->tanggal_lahir->toDateString())->toBe('2012-05-14')
        ->and($calon->agama)->toBe('Islam')
        ->and($calon->alamat)->toBe('Jl. Pesantren No. 1')
        ->and($calon->nohp_siswa)->toBe('081234567892')
        ->and($calon->nama_ayah)->toBe('Bapak Calon')
        ->and($calon->pekerjaan_ayah)->toBe('Petani')
        ->and($calon->nama_ibu)->toBe('Ibu Calon')
        ->and($calon->status_ibu)->toBe('Hidup')
        ->and($calon->kk)->toBe('calon-dokumen/kk/kk.pdf')
        ->and($calon->ijazah)->toBe('calon-dokumen/ijazah/ijazah.pdf')
        ->and($calon->dataLengkap())->toBeTrue()
        ->and($calon->dilengkapi_pada)->not->toBeNull();
});

test('requires the identity documents before the data is complete', function () {
    panelSiswa();
    $calon = CalonSiswaFactory::new()->create();
    $this->actingAs($calon->user);

    $data = calonDataForm();
    unset($data['kk'], $data['ijazah']);

    Livewire::test(DataPendaftaran::class)
        ->fillForm($data)
        ->call('save')
        ->assertHasFormErrors(['kk', 'ijazah']);

    expect($calon->fresh()->dilengkapi_pada)->toBeNull();
});

test('validates the administration data', function (array $overrides, string $field) {
    panelSiswa();
    $calon = CalonSiswaFactory::new()->create();
    $this->actingAs($calon->user);

    Livewire::test(DataPendaftaran::class)
        ->fillForm(array_replace(calonDataForm(), $overrides))
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect($calon->fresh()->dilengkapi_pada)->toBeNull();
})->with([
    'nik kurang dari 16 angka' => [['nik' => '32730101'], 'nik'],
    'nomor kk kurang dari 16 angka' => [['no_kk' => '3273'], 'no_kk'],
    'nisn bukan 10 angka' => [['nisn' => '12345'], 'nisn'],
    'agama tidak dikenal' => [['agama' => 'Lainnya'], 'agama'],
    'tanggal lahir di masa depan' => [['tanggal_lahir' => '2027-01-01'], 'tanggal_lahir'],
    'nama ayah kosong' => [['nama_ayah' => ''], 'nama_ayah'],
    'status ibu tidak dikenal' => [['status_ibu' => 'Pindah'], 'status_ibu'],
]);

test('lets a candidate change the account password from the student panel', function () {
    panelSiswa();
    $calon = CalonSiswaFactory::new()->create();
    $this->actingAs($calon->user);

    Livewire::test(UbahKataSandi::class)
        ->fillForm([
            'password' => 'sandiBaru2026',
            'password_confirmation' => 'sandiBaru2026',
        ])
        ->call('save')
        ->assertNotified('Kata sandi berhasil diperbarui.');

    expect(Hash::check('sandiBaru2026', $calon->user->fresh()->password))->toBeTrue();

    Livewire::test(UbahKataSandi::class)
        ->fillForm([
            'password' => 'lainSekali2026',
            'password_confirmation' => 'beda2026',
        ])
        ->call('save')
        ->assertNotified('Konfirmasi kata sandi belum sama.');

    expect(Hash::check('sandiBaru2026', $calon->user->fresh()->password))->toBeTrue();
});

test('keeps candidates out of the admin panel', function () {
    $calon = CalonSiswaFactory::new()->create();
    $this->actingAs($calon->user);

    $this->get('/admin')->assertForbidden();
});

test('lets admins open the candidate resource and update the test result', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([...calonDataLengkap(), 'status' => CalonSiswa::STATUS_LULUS]);
    $this->actingAs($admin);

    $this->get(CalonSiswaResource::getUrl())->assertOk();

    Livewire::test(ListCalonSiswas::class)
        ->assertCanSeeTableRecords([$calon]);

    Livewire::test(EditCalonSiswa::class, ['record' => $calon->getRouteKey()])
        ->fillForm([
            'nilai_tes' => 85,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $calon->refresh();

    expect($calon->nilai_tes)->toBe(85);

    $this->actingAs($calon->user);

    $this->get(StatusPendaftaran::getUrl(panel: 'siswa'))
        ->assertOk()
        ->assertSee('Lulus')
        ->assertSee('85');
});

test('lets an admin reset a candidate password and emails the new credentials', function () {
    Mail::fake();
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create();
    $user = $calon->user;
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('resetSandi')->table($calon))
        ->assertNotified('Kata sandi baru sudah dikirim');

    $mail = Mail::sent(KataSandiBaruCalonSiswa::class)->first();

    expect($mail->hasTo($user->email))->toBeTrue()
        ->and($mail->calon->is($calon))->toBeTrue()
        ->and(Hash::check($mail->password, $user->fresh()->password))->toBeTrue();

    Auth::logout();
    panelSiswa();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => $mail->password,
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

test('keeps the candidate administration data read only for admins', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([...calonDataLengkap(), 'status' => CalonSiswa::STATUS_LULUS]);
    $this->actingAs($admin);

    Livewire::test(EditCalonSiswa::class, ['record' => $calon->getRouteKey()])
        ->assertFormFieldDisabled('nama', 'form')
        ->assertFormFieldDisabled('nik', 'form')
        ->assertFormFieldDisabled('no_kk', 'form')
        ->assertFormFieldDisabled('nisn', 'form')
        ->assertFormFieldDisabled('alamat', 'form')
        ->assertFormFieldDisabled('nama_ayah', 'form')
        ->assertFormFieldDisabled('nama_ibu', 'form')
        ->assertFormFieldEnabled('nilai_tes', 'form')
        ->assertFormFieldEnabled('status', 'form')
        ->assertFormFieldEnabled('catatan', 'form')
        ->assertFormFieldDisabled('foto', 'form')
        ->assertFormFieldDisabled('kk', 'form')
        ->assertFormFieldDisabled('ijazah', 'form')
        ->assertFormFieldDisabled('kip', 'form');

    Livewire::test(EditCalonSiswa::class, ['record' => $calon->getRouteKey()])
        ->fillForm(['nilai_tes' => 90, 'status' => CalonSiswa::STATUS_TIDAK_LULUS])
        ->call('save')
        ->assertHasNoFormErrors();

    $calon->refresh();

    expect($calon->nilai_tes)->toBe(90)
        ->and($calon->status)->toBe(CalonSiswa::STATUS_TIDAK_LULUS)
        ->and($calon->nik)->toBe('3273010101010001')
        ->and($calon->nisn)->toBe('1234567890')
        ->and($calon->nama_ayah)->toBe('Bapak Calon')
        ->and($calon->kk)->toBe('calon-dokumen/kk/kk.pdf')
        ->and($calon->dataLengkap())->toBeTrue();
});

test('shows candidate documents with open and download actions', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(
        fn (string $path, mixed $expiration): string => 'https://example.test/'.$path,
    );

    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([
        ...calonDataLengkap(),
        'kk' => 'calon-dokumen/kk/kk.pdf',
        'foto' => 'calon-dokumen/foto/foto.jpg',
        'status' => CalonSiswa::STATUS_LULUS,
    ]);
    $this->actingAs($admin);

    $this->get(CalonSiswaResource::getUrl('edit', ['record' => $calon]))
        ->assertOk()
        ->assertSee('Dokumen Pendaftaran')
        ->assertSee('Kartu Keluarga (KK)');

    Livewire::test(EditCalonSiswa::class, ['record' => $calon->getRouteKey()])
        ->assertFormFieldDisabled('kk', 'form')
        ->assertFormFieldDisabled('foto', 'form');
});

test('denies the candidate resource to teachers', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    $this->get(CalonSiswaResource::getUrl())->assertForbidden();
});

test('converts an accepted candidate into a student with NIS, class, and interests', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([
        ...calonDataLengkap(),
        'nama' => 'Ahmad Calon',
        'jenis_kelamin' => 'laki-laki',
        'nohp' => '081234567891',
        'pekerjaan_ayah' => 'Petani',
        'status' => CalonSiswa::STATUS_LULUS,
    ]);
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $peminatan = JenisPeminatan::query()->create(['nama_peminatan' => 'Tahfizh', 'nominal' => 150000]);
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('jadikanSiswa')->table($calon), data: [
            'nis' => '20260001',
            'kelas_id' => $kelas->id,
            'jenis_peminatans' => [$peminatan->id],
        ])
        ->assertHasNoActionErrors();

    $murid = Murid::query()->where('nis', '20260001')->firstOrFail();
    $calon->refresh();

    expect($murid->nama)->toBe('Ahmad Calon')
        ->and($murid->email)->toBe($calon->email)
        ->and($murid->nisn)->toBe('1234567890')
        ->and($murid->nohp_siswa)->toBe('081234567891')
        ->and($murid->kelas_id)->toBe($kelas->id)
        ->and($murid->nik)->toBe('3273010101010001')
        ->and($murid->no_kk)->toBe('3273010101010002')
        ->and($murid->tempat_lahir)->toBe('Bandung')
        ->and($murid->tanggal_lahir)->toStartWith('2012-05-14')
        ->and($murid->agama)->toBe('Islam')
        ->and($murid->alamat)->toBe('Jl. Pesantren No. 1')
        ->and($murid->nama_ayah)->toBe('Bapak Calon')
        ->and($murid->nama_ibu)->toBe('Ibu Calon')
        ->and($murid->kk)->toBe('calon-dokumen/kk/kk.pdf')
        ->and($murid->ijazah)->toBe('calon-dokumen/ijazah/ijazah.pdf')
        ->and($murid->jenisPeminatans->pluck('id')->all())->toBe([$peminatan->id])
        ->and($calon->murid_id)->toBe($murid->id)
        ->and($calon->status)->toBe(CalonSiswa::STATUS_DITERIMA);

    $this->actingAs($calon->user);

    $this->get(StatusPendaftaran::getUrl(panel: 'siswa'))
        ->assertOk()
        ->assertSee('20260001')
        ->assertSee('VII')
        ->assertSee('Tahfizh');
});

test('refuses to convert a candidate whose administration data is incomplete', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create(['status' => CalonSiswa::STATUS_LULUS]);
    $kelas = KelasFactory::new()->create();
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('jadikanSiswa')->table($calon), data: [
            'nis' => '20260001',
            'kelas_id' => $kelas->id,
        ])
        ->assertNotified('Data calon siswa belum lengkap');

    expect($calon->fresh()->murid_id)->toBeNull()
        ->and(Murid::query()->count())->toBe(0);
});

test('validates the school NIS when converting a candidate', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([...calonDataLengkap(), 'status' => CalonSiswa::STATUS_LULUS]);
    $kelas = KelasFactory::new()->create();
    MuridFactory::new()->create(['nis' => '20260001']);
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('jadikanSiswa')->table($calon), data: [
            'nis' => '20260001',
            'kelas_id' => $kelas->id,
        ])
        ->assertHasActionErrors(['nis']);

    expect($calon->fresh()->murid_id)->toBeNull();
});

test('refuses to convert when the candidate email already belongs to a student', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([...calonDataLengkap(), 'status' => CalonSiswa::STATUS_LULUS]);
    $kelas = KelasFactory::new()->create();
    MuridFactory::new()->create(['email' => $calon->email]);
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('jadikanSiswa')->table($calon), data: [
            'nis' => '20260001',
            'kelas_id' => $kelas->id,
        ])
        ->assertNotified('Email sudah dipakai siswa lain');

    expect($calon->fresh()->murid_id)->toBeNull();
});

test('refuses to convert when the NISN already belongs to a student', function () {
    $admin = calonAdmin();
    $calon = CalonSiswaFactory::new()->create([...calonDataLengkap(), 'status' => CalonSiswa::STATUS_LULUS]);
    $kelas = KelasFactory::new()->create();
    MuridFactory::new()->create(['nisn' => '1234567890']);
    $this->actingAs($admin);

    Livewire::test(ListCalonSiswas::class)
        ->callAction(TestAction::make('jadikanSiswa')->table($calon), data: [
            'nis' => '20260001',
            'kelas_id' => $kelas->id,
        ])
        ->assertNotified('NISN sudah dipakai siswa lain');

    expect($calon->fresh()->murid_id)->toBeNull();
});
