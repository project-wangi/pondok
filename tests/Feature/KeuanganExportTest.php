<?php

use App\Filament\Exports\DonasiWorkbook;
use App\Filament\Exports\GajiGuruWorkbook;
use App\Filament\Pages\GajiGuru;
use App\Filament\Pages\RekapMengajarGuru;
use App\Filament\Resources\Donasis\Pages\ListDonasis;
use App\Models\Jabatan;
use App\Models\Kelas;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenPiketFactory;
use Database\Factories\AbsenRapatFactory;
use Database\Factories\DonasiFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Database\Factories\PresensiMuridFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function keuanganAdmin(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

/** @return list<array<int, mixed>> */
function keuanganWorkbookRows(array $download): array
{
    $path = tempnam(sys_get_temp_dir(), 'keuangan-test-');
    file_put_contents($path, base64_decode($download['content']));

    $options = new Options;
    $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
    $reader = new Reader($options);
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

        if (is_file($path)) {
            unlink($path);
        }
    }

    return $rows;
}

test('exports the salary recap for the selected month', function () {
    $this->actingAs(keuanganAdmin());
    SiteSetting::create(['school_name' => 'Pondok Uji']);

    $guru = GuruFactory::new()->create(['jarak_rumah' => 10]);
    $guru->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $position = Jabatan::create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 500000]);
    $guru->jabatans()->attach($position->id);

    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);

    $student = MuridFactory::new()->create();

    foreach (['2026-10-05', '2026-10-06'] as $date) {
        $session = SesiMengajarFactory::new()->create([
            'jadwal_pelajaran_id' => $schedule->id,
            'tanggal' => $date,
        ]);

        PresensiMuridFactory::new()->create([
            'sesi_mengajar_id' => $session->id,
            'murid_id' => $student->id,
        ]);
    }

    AbsenRapatFactory::new()->create(['guru_id' => $guru->id, 'tanggal' => '2026-10-08']);
    AbsenPiketFactory::new()->create([
        'guru_id' => $guru->id,
        'kelas_id' => KelasFactory::new()->create()->id,
        'tanggal' => '2026-10-09',
    ]);

    $component = Livewire::test(GajiGuru::class)
        ->set('bulan', 10)
        ->set('tahun', 2026)
        ->call('ekspor')
        ->assertFileDownloaded('gaji-guru-oktober-2026.xlsx');

    $rows = keuanganWorkbookRows($component->effects['download']);

    expect($rows[1][0])->toBe('REKAP GAJI GURU')
        ->and($rows[2][0])->toBe('Periode: Oktober 2026')
        ->and($rows[4])->toBe(GajiGuruWorkbook::HEADERS);

    $data = $rows[5];

    // 2 sesi × 90 menit = 4,5 JP → Rp45.000; transport 10 km × 2 hari = Rp20.000.
    expect($data[1])->toBe('Ust. Ahmad Fauzi')
        ->and((float) $data[2])->toBe(4.5)
        ->and((int) $data[3])->toBe(2)
        ->and((int) $data[4])->toBe(45000)
        ->and((int) $data[5])->toBe(20000)
        ->and((int) $data[6])->toBe(500000)
        ->and((int) $data[7])->toBe(20000)
        ->and((int) $data[8])->toBe(0)
        ->and((int) $data[9])->toBe(10000)
        ->and((int) $data[10])->toBe(595000);

    $total = $rows[6];

    expect($total[0])->toBe('Total')
        ->and((float) $total[2])->toBe(4.5)
        ->and((int) $total[4])->toBe(45000)
        ->and((int) $total[10])->toBe(595000);
});

test('exports the teaching recap as a JP matrix per class', function () {
    $this->actingAs(keuanganAdmin());

    $classSeven = Kelas::create(['nama_kelas' => 'VII']);
    $classEight = Kelas::create(['nama_kelas' => 'VIII']);

    $guru = GuruFactory::new()->create();
    $guru->user->update(['name' => 'Ust. Ahmad Fauzi']);

    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $classSeven->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
    ]);

    JadwalPelajaranFactory::new()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $classEight->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
    ]);

    $student = MuridFactory::new()->create(['kelas_id' => $classSeven->id]);
    $session = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'tanggal' => '2026-10-05',
    ]);

    PresensiMuridFactory::new()->create([
        'sesi_mengajar_id' => $session->id,
        'murid_id' => $student->id,
    ]);

    $component = Livewire::test(RekapMengajarGuru::class)
        ->set('bulan', 10)
        ->set('tahun', 2026)
        ->call('ekspor')
        ->assertFileDownloaded('rekap-mengajar-oktober-2026.xlsx');

    $rows = keuanganWorkbookRows($component->effects['download']);

    expect($rows[1][0])->toBe('REKAP MENGAJAR GURU')
        ->and($rows[4])->toBe(['No', 'Nama Guru', 'VII', 'VIII', 'Total'])
        ->and($rows[5][1])->toBe('Ust. Ahmad Fauzi')
        ->and((float) $rows[5][2])->toBe(2.25)
        ->and((float) $rows[5][3])->toBe(0.0)
        ->and((float) $rows[5][4])->toBe(2.25)
        ->and($rows[6][0])->toBe('Total')
        ->and((float) $rows[6][2])->toBe(2.25)
        ->and((float) $rows[6][4])->toBe(2.25);
});

test('exports donations following the active month filter', function () {
    $this->actingAs(keuanganAdmin());
    SiteSetting::create(['school_name' => 'Pondok Uji']);

    $october = DonasiFactory::new()->create(['jumlah_anak' => 2, 'tanggal' => '2026-10-22']);
    $october->guru->user->update(['name' => 'Ust. Ahmad Fauzi']);
    DonasiFactory::new()->create(['jumlah_anak' => 1, 'tanggal' => '2026-09-22']);

    $component = Livewire::test(ListDonasis::class)
        ->assertSee('Ekspor Excel')
        ->callAction(TestAction::make('eksporExcel')->table())
        ->assertFileDownloaded('data-donasi-2026-10-22.xlsx');

    $rows = keuanganWorkbookRows($component->effects['download']);

    expect($rows[1][0])->toBe('DATA DONASI GURU')
        ->and($rows[4])->toBe(DonasiWorkbook::HEADERS)
        ->and($rows[5][1])->toBe('22/10/2026')
        ->and($rows[5][2])->toBe('Ust. Ahmad Fauzi')
        ->and((int) $rows[5][3])->toBe(2)
        ->and((int) $rows[5][4])->toBe(100000)
        ->and($rows[6][1])->toBe('22/09/2026')
        ->and((int) $rows[6][4])->toBe(50000)
        ->and($rows[7][0])->toBe('Total')
        ->and((int) $rows[7][3])->toBe(3)
        ->and((int) $rows[7][4])->toBe(150000);

    $component = Livewire::test(ListDonasis::class)
        ->filterTable('bulan', '10')
        ->callAction(TestAction::make('eksporExcel')->table())
        ->assertFileDownloaded('data-donasi-2026-10-22.xlsx');

    $rows = keuanganWorkbookRows($component->effects['download']);

    expect($rows[5][1])->toBe('22/10/2026')
        ->and($rows[6][0])->toBe('Total')
        ->and((int) $rows[6][4])->toBe(100000);
});

test('lets a teacher export only their own donations', function () {
    $teacher = GuruFactory::new()->create();
    $teacher->user->update(['name' => 'Ust. Ahmad Fauzi']);
    $this->actingAs($teacher->user);

    DonasiFactory::new()->create(['guru_id' => $teacher->id, 'jumlah_anak' => 2, 'tanggal' => '2026-10-22']);
    DonasiFactory::new()->create(['jumlah_anak' => 1, 'tanggal' => '2026-10-22']);

    $component = Livewire::test(ListDonasis::class)
        ->assertSee('Ekspor Excel')
        ->callAction(TestAction::make('eksporExcel')->table())
        ->assertFileDownloaded('data-donasi-2026-10-22.xlsx');

    $rows = keuanganWorkbookRows($component->effects['download']);

    expect($rows[5][2])->toBe('Ust. Ahmad Fauzi')
        ->and((int) $rows[5][4])->toBe(100000)
        ->and($rows[6][0])->toBe('Total')
        ->and((int) $rows[6][4])->toBe(100000);
});
