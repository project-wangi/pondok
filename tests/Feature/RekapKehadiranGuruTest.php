<?php

use App\Filament\Pages\RekapKehadiranGuru;
use App\Models\AbsenGuru;
use App\Models\Guru;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenGuruFactory;
use Database\Factories\AbsenPiketFactory;
use Database\Factories\AbsenRapatFactory;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\PresensiMuridFactory;
use Database\Factories\SesiMengajarFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;

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

function guruRekapTeacher(string $name): Guru
{
    return GuruFactory::new()->create([
        'user_id' => User::factory()->create(['name' => $name])->id,
    ]);
}

function guruRekapAttendance(Guru $teacher, string $tanggal): AbsenGuru
{
    return AbsenGuruFactory::new()->create([
        'guru_id' => $teacher->id,
        'user_id' => $teacher->user_id,
        'nama_guru' => $teacher->user->name,
        'tanggal' => $tanggal,
        'masuk_pada' => $tanggal.' 07:05:00',
        'pulang_pada' => $tanggal.' 15:10:00',
    ]);
}

function guruRekapTeaching(Guru $teacher, string $tanggal): void
{
    $class = KelasFactory::new()->create();
    $schedule = JadwalPelajaranFactory::new()->create([
        'guru_id' => $teacher->id,
        'kelas_id' => $class->id,
    ]);
    $session = SesiMengajarFactory::new()->create([
        'jadwal_pelajaran_id' => $schedule->id,
        'guru_id' => $teacher->id,
        'tanggal' => $tanggal,
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:00:00',
    ]);
    PresensiMuridFactory::new()->create([
        'sesi_mengajar_id' => $session->id,
        'status' => 'Hadir',
    ]);
}

function guruRekapPiket(Guru $teacher, string $tanggal): void
{
    AbsenPiketFactory::new()->create([
        'guru_id' => $teacher->id,
        'nama_guru' => $teacher->user->name,
        'tanggal' => $tanggal,
    ]);
}

function guruRekapRapat(Guru $teacher, string $tanggal): void
{
    AbsenRapatFactory::new()->create([
        'guru_id' => $teacher->id,
        'nama_guru' => $teacher->user->name,
        'tanggal' => $tanggal,
    ]);
}

/** @return array<string, list<array<int, mixed>>> */
function guruRekapWorkbookSheets(array $download): array
{
    $path = tempnam(sys_get_temp_dir(), 'guru-rekap-export-');
    file_put_contents($path, base64_decode($download['content']));

    $options = new ReaderOptions;
    $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
    $reader = new Reader($options);
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }
    } finally {
        $reader->close();

        if (is_file($path)) {
            unlink($path);
        }
    }

    return $sheets;
}

test('shows the menu in the attendance report group and restricts access', function () {
    expect(RekapKehadiranGuru::getNavigationGroup())->toBe('Laporan Kehadiran')
        ->and(RekapKehadiranGuru::getNavigationLabel())->toBe('Rekap Kehadiran Guru')
        ->and(RekapKehadiranGuru::getSlug())->toBe('rekap-kehadiran-guru')
        ->and(RekapKehadiranGuru::shouldRegisterNavigation())->toBeTrue();

    $this->get(route('filament.admin.pages.rekap-kehadiran-guru'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);
    $this->get(route('filament.admin.pages.rekap-kehadiran-guru'))->assertForbidden();
    Livewire::test(RekapKehadiranGuru::class)->assertForbidden();

    $this->actingAs(adminPanelUser('View:RekapKehadiranGuru'));
    $this->get(route('filament.admin.pages.rekap-kehadiran-guru'))
        ->assertOk()
        ->assertSee('Rekap Kehadiran Guru');
});

test('shows attendance per date from the same sources as the transport calculation', function () {
    $ahmad = guruRekapTeacher('Ust. Ahmad');
    $bilal = guruRekapTeacher('Ust. Bilal');
    guruRekapAttendance($ahmad, '2026-10-05');
    guruRekapTeaching($ahmad, '2026-10-06');
    guruRekapPiket($ahmad, '2026-10-07');
    guruRekapRapat($ahmad, '2026-10-07');
    guruRekapAttendance($bilal, '2026-10-05');
    guruRekapAttendance($bilal, '2026-09-30');
    $this->actingAs(adminPanelUser('View:RekapKehadiranGuru'));

    $component = Livewire::test(RekapKehadiranGuru::class)
        ->assertOk()
        ->assertSee('Oktober 2026')
        ->assertSee('Ust. Ahmad')
        ->assertSee('Ust. Bilal')
        ->assertSee('Senin, 5 Okt')
        ->assertSee('Selasa, 6 Okt')
        ->assertSee('Rabu, 7 Okt')
        ->assertSeeHtml('guru-rekap-mark');

    $laporan = $component->viewData('laporan');

    expect($laporan['columns'])->toHaveCount(3)
        ->and($laporan['columns'][0])->toMatchArray([
            'tanggal' => '2026-10-05',
            'nomor' => 1,
            'hari' => 'Senin',
            'label' => '5 Okt',
        ])
        ->and($laporan['hariTercatat'])->toBe(3)
        ->and($laporan['guruHadir'])->toBe(2)
        ->and($laporan['totals']['hadir'])->toBe(4)
        ->and($laporan['rows'][0]['nama'])->toBe('Ust. Ahmad')
        ->and($laporan['rows'][0]['hadir'])->toBe(3)
        ->and($laporan['rows'][0]['cells']['2026-10-05'])->toBe(['Absensi'])
        ->and($laporan['rows'][0]['cells']['2026-10-06'])->toBe(['Mengajar'])
        ->and($laporan['rows'][0]['cells']['2026-10-07'])->toBe(['Piket', 'Rapat'])
        ->and($laporan['rows'][1]['hadir'])->toBe(1)
        ->and($laporan['rows'][1]['cells']['2026-10-05'])->toBe(['Absensi']);
});

test('follows the selected month and year', function () {
    $teacher = guruRekapTeacher('Ust. Ahmad');
    guruRekapAttendance($teacher, '2026-10-05');
    guruRekapAttendance($teacher, '2026-09-07');
    $this->actingAs(adminPanelUser('View:RekapKehadiranGuru'));

    $component = Livewire::test(RekapKehadiranGuru::class)
        ->assertSee('Oktober 2026')
        ->call('pilihBulan', 9)
        ->assertSee('September 2026');

    $laporan = $component->viewData('laporan');

    expect($laporan['columns'])->toHaveCount(1)
        ->and($laporan['columns'][0]['tanggal'])->toBe('2026-09-07')
        ->and($laporan['totals']['hadir'])->toBe(1);

    $component->call('pilihTahun', 2027);

    expect($component->viewData('laporan')['columns'])->toBe([])
        ->and($component->viewData('laporan')['rows'])->not->toBe([])
        ->and($component->html())->toContain('Belum ada kehadiran guru tercatat');
});

test('exports the teacher attendance recap to excel', function () {
    $teacher = guruRekapTeacher('Ust. Ahmad');
    guruRekapAttendance($teacher, '2026-10-05');
    guruRekapTeaching($teacher, '2026-10-06');
    guruRekapPiket($teacher, '2026-10-07');
    guruRekapRapat($teacher, '2026-10-07');
    $this->actingAs(adminPanelUser('View:RekapKehadiranGuru'));

    $component = Livewire::test(RekapKehadiranGuru::class)
        ->call('ekspor')
        ->assertFileDownloaded('rekap-kehadiran-guru-oktober-2026.xlsx');

    $sheets = guruRekapWorkbookSheets($component->effects['download']);
    $rekap = $sheets['Rekap Kehadiran'];

    expect(array_keys($sheets))->toBe(['Rekap Kehadiran'])
        ->and($rekap[1][0])->toBe('REKAP KEHADIRAN GURU')
        ->and($rekap[2][0])->toBe('Periode: Oktober 2026')
        ->and(array_slice($rekap[5], 0, 6))->toBe([
            'No', 'Nama Guru', 'Senin, 5 Okt', 'Selasa, 6 Okt', 'Rabu, 7 Okt', 'Hari Hadir',
        ])
        ->and($rekap[6][1])->toBe('Ust. Ahmad')
        ->and($rekap[6][2])->toBe('A')
        ->and($rekap[6][3])->toBe('M')
        ->and($rekap[6][4])->toBe('P/R')
        ->and((int) $rekap[6][5])->toBe(3);
});
