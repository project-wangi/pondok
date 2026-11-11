<?php

use App\Filament\Exports\MuridWorkbook;
use App\Filament\Imports\MuridImporter;
use App\Filament\Resources\Murids\Pages\ListMurids;
use App\Models\Murid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

/** @return list<list<mixed>> */
function muridSheetRows(string $path): array
{
    $sheets = muridWorkbookSheets($path);

    return array_values($sheets)[0] ?? [];
}

/** @return array<string, list<list<mixed>>> */
function muridWorkbookSheets(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(fn ($cell): mixed => $cell->getValue(), $row->getCells());
        }

        $sheets[$sheet->getName()] = $rows;
    }

    $reader->close();

    return $sheets;
}

/** @param list<list<string>> $rows */
function muridCsvFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'murid-import-').'.csv';
    $handle = fopen($path, 'w');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

/** @return list<string> */
function muridCsvHeader(): array
{
    return MuridWorkbook::HEADERS;
}

test('exports student data with the same columns as the import format', function () {
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $murid = MuridFactory::new()->create([
        'kelas_id' => $kelas->id,
        'nama' => 'Ahmad',
        'nis' => '20260001',
        'nisn' => '1234567890',
        'nik' => '3273010101010001',
        'status' => Murid::STATUS_LULUS,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'murid-export-').'.xlsx';
    app(MuridWorkbook::class)->write($path, new Collection([$murid]));

    $rows = muridSheetRows($path);
    unlink($path);

    expect($rows[0])->toBe(muridCsvHeader())
        ->and($rows[1][0])->toBe('20260001')
        ->and($rows[1][2])->toBe('Ahmad')
        ->and($rows[1][6])->toBe('VII')
        ->and($rows[1][10])->toBe(Murid::STATUS_LULUS)
        ->and($rows[1][11])->toBe('2026-11-01');
});

test('imports new students from a csv file', function () {
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $path = muridCsvFile([
        muridCsvHeader(),
        ['20260001', '1234567890', 'Ahmad', 'L', 'ahmad@example.test', '081234567891', 'VII', '', '', '', '', '', ''],
        ['20260002', '', 'Bilal', 'perempuan', 'bilal@example.test', '', '', '', '', '', '', '', ''],
    ]);

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    expect($hasil['dibuat'])->toBe(2)
        ->and($hasil['diperbarui'])->toBe(0)
        ->and($hasil['gagal'])->toBe([]);

    $ahmad = Murid::query()->where('nis', '20260001')->firstOrFail();

    expect($ahmad->nama)->toBe('Ahmad')
        ->and($ahmad->jenis_kelamin)->toBe('laki-laki')
        ->and($ahmad->kelas_id)->toBe($kelas->id)
        ->and($ahmad->nohp_siswa)->toBe('081234567891')
        ->and($ahmad->status)->toBe(Murid::STATUS_AKTIF)
        ->and(Murid::query()->where('email', 'bilal@example.test')->value('jenis_kelamin'))->toBe('perempuan');
});

test('updates existing students matched by nis', function () {
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'VIII']);
    MuridFactory::new()->create([
        'nama' => 'Nama Lama',
        'nis' => '20260001',
        'email' => 'lama@example.test',
        'status' => Murid::STATUS_AKTIF,
    ]);

    $path = muridCsvFile([
        muridCsvHeader(),
        ['20260001', '', 'Nama Baru', 'Laki-laki', 'baru@example.test', '', 'VIII', '', '', '', 'Lulus', '', ''],
    ]);

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    $murid = Murid::query()->where('nis', '20260001')->firstOrFail();

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['diperbarui'])->toBe(1)
        ->and($murid->nama)->toBe('Nama Baru')
        ->and($murid->email)->toBe('baru@example.test')
        ->and($murid->kelas_id)->toBe($kelas->id)
        ->and($murid->status)->toBe(Murid::STATUS_LULUS)
        ->and($murid->tanggal_lulus?->toDateString())->toBe('2026-11-01');
});

test('skips invalid rows and reports why', function () {
    KelasFactory::new()->create(['nama_kelas' => 'VII']);
    MuridFactory::new()->create(['email' => 'dipakai@example.test', 'nis' => '90000001']);
    MuridFactory::new()->create(['email' => 'milik@example.test', 'nis' => '90000002']);

    $path = muridCsvFile([
        muridCsvHeader(),
        ['', '', 'Tanpa Kelas', 'L', 'a@example.test', '', 'Kelas Tidak Ada', '', '', '', '', '', ''],
        ['', '', 'Email Rusak', 'L', 'bukan-email', '', 'VII', '', '', '', '', '', ''],
        ['90000002', '', 'Email Bentrok', 'L', 'dipakai@example.test', '', 'VII', '', '', '', '', '', ''],
        ['', '', 'Jenis Kelamin Salah', 'X', 'c@example.test', '', 'VII', '', '', '', '', '', ''],
        ['', '', 'Status Salah', 'L', 'd@example.test', '', 'VII', '', '', '', 'Pindah', '', ''],
        ['', '', 'Valid', 'P', 'valid@example.test', '', 'VII', '', '', '', '', '', ''],
    ]);

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    expect($hasil['dibuat'])->toBe(1)
        ->and($hasil['jumlah_gagal'])->toBe(5)
        ->and($hasil['gagal'])->toHaveCount(5)
        ->and($hasil['gagal'][0])->toContain('kelas')
        ->and($hasil['gagal'][1])->toContain('email tidak valid')
        ->and($hasil['gagal'][2])->toContain('sudah dipakai')
        ->and($hasil['gagal'][3])->toContain('jenis kelamin')
        ->and($hasil['gagal'][4])->toContain('status')
        ->and($hasil['gagal'][0])->toContain('Tanpa Kelas')
        ->and(Murid::query()->where('email', 'valid@example.test')->exists())->toBeTrue()
        ->and(Murid::query()->count())->toBe(3);
});

test('matches students by email when the nis is empty so re-uploading does not duplicate', function () {
    $murid = MuridFactory::new()->create([
        'nama' => 'Nama Lama',
        'email' => 'lama@example.test',
        'nis' => null,
    ]);

    $path = muridCsvFile([
        muridCsvHeader(),
        ['', '', 'Nama Baru', 'P', 'lama@example.test', '', '', '', '', '', '', '', ''],
    ]);

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['diperbarui'])->toBe(1)
        ->and($hasil['jumlah_gagal'])->toBe(0)
        ->and(Murid::query()->count())->toBe(1)
        ->and($murid->fresh()->nama)->toBe('Nama Baru');
});

test('round trips an exported workbook back through the importer', function () {
    $murid = MuridFactory::new()->create([
        'nama' => 'Ahmad',
        'nis' => '20260001',
        'nisn' => '1234567890',
        'email' => 'ahmad@example.test',
        'jenis_kelamin' => 'laki-laki',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'murid-roundtrip-').'.xlsx';
    app(MuridWorkbook::class)->write($path, new Collection([$murid]));

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['diperbarui'])->toBe(1)
        ->and($hasil['gagal'])->toBe([])
        ->and($murid->fresh()->nama)->toBe('Ahmad');
});

test('hides the upload action without create permission', function () {
    Permission::findOrCreate('ViewAny:Murid', 'web');
    $user = User::factory()->create()->givePermissionTo('ViewAny:Murid');
    $this->actingAs($user);

    Livewire::test(ListMurids::class)
        ->assertTableActionHidden('unggahDataSiswa')
        ->assertTableActionVisible('unduhDataSiswa');
});

test('offers a template with guided headers and a reference sheet', function () {
    Permission::findOrCreate('ViewAny:Murid', 'web');
    $user = User::factory()->create()->givePermissionTo('ViewAny:Murid');
    KelasFactory::new()->create(['nama_kelas' => 'VII']);
    KelasFactory::new()->create(['nama_kelas' => 'VIII']);
    $this->actingAs($user);

    $component = Livewire::test(ListMurids::class)
        ->callTableAction('unduhTemplateSiswa')
        ->assertFileDownloaded('template-data-siswa.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'murid-template-').'.xlsx';
    file_put_contents($path, base64_decode($component->effects['download']['content']));

    $sheets = muridWorkbookSheets($path);
    unlink($path);

    $data = $sheets['Data Siswa'];
    $petunjuk = collect($sheets['Petunjuk'])->flatten()->filter()->all();

    expect($data)->toHaveCount(1)
        ->and($data[0])->toBe(MuridWorkbook::TEMPLATE_HEADERS)
        ->and($petunjuk)->toContain('PETUNJUK PENGISIAN')
        ->and($petunjuk)->toContain('PILIHAN STATUS')
        ->and($petunjuk)->toContain('VII')
        ->and($petunjuk)->toContain('VIII');
});

test('imports a file that uses the guided template headers', function () {
    KelasFactory::new()->create(['nama_kelas' => 'VII']);

    $path = muridCsvFile([
        MuridWorkbook::TEMPLATE_HEADERS,
        ['20260001', '', 'Ahmad', 'L', 'ahmad@example.test', '', 'VII', '', '', '', 'Lulus', '', ''],
    ]);

    $hasil = app(MuridImporter::class)->import($path);
    unlink($path);

    expect($hasil['dibuat'])->toBe(1)
        ->and($hasil['gagal'])->toBe([])
        ->and(Murid::query()->where('nis', '20260001')->value('status'))->toBe(Murid::STATUS_LULUS);
});

test('imports students through the upload action', function () {
    foreach (['ViewAny:Murid', 'Create:Murid'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create()->givePermissionTo(['ViewAny:Murid', 'Create:Murid']);
    KelasFactory::new()->create(['nama_kelas' => 'VII']);

    $path = muridCsvFile([
        muridCsvHeader(),
        ['20260001', '', 'Ahmad', 'L', 'ahmad@example.test', '', 'VII', '', '', '', '', '', ''],
    ]);

    Storage::disk('local')->put('import-siswa/uji.csv', file_get_contents($path));
    unlink($path);

    $this->actingAs($user);

    Livewire::test(ListMurids::class)
        ->callTableAction('unggahDataSiswa', data: ['berkas' => ['import-siswa/uji.csv']]);

    expect(Murid::query()->where('nis', '20260001')->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists('import-siswa/uji.csv'))->toBeFalse();
});

test('downloads the student list from the table action', function () {
    Permission::findOrCreate('ViewAny:Murid', 'web');
    $user = User::factory()->create()->givePermissionTo('ViewAny:Murid');
    MuridFactory::new()->create(['nama' => 'Ahmad']);
    $this->actingAs($user);

    Livewire::test(ListMurids::class)
        ->callTableAction('unduhDataSiswa')
        ->assertFileDownloaded();
});
