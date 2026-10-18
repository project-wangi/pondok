<?php

use App\Filament\Exports\JadwalPelajaranWorkbook;
use App\Filament\Resources\JadwalPelajarans\Pages\ListJadwalPelajarans;
use App\Models\JadwalPelajaran;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Factories\GuruFactory;
use Database\Factories\JadwalPelajaranFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $this->exportPath = tempnam(sys_get_temp_dir(), 'jadwal-test-');
});

afterEach(function () {
    if (is_file($this->exportPath)) {
        unlink($this->exportPath);
    }
});

/** @return list<array{name: string, rows: array<int, array>}> */
function jadwalWorkbookSheets(string $path): array
{
    $options = new Options;
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

            $sheets[] = ['name' => $sheet->getName(), 'rows' => $rows];
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

function jadwalExportUser(): User
{
    Permission::findOrCreate('ViewAny:JadwalPelajaran', 'web');

    return User::factory()->create()->givePermissionTo('ViewAny:JadwalPelajaran');
}

test('exports a printable timetable with configured identity and subject and teacher names', function () {
    SiteSetting::factory()->create(['school_name' => 'Pondok Uji', 'address' => 'Alamat sekolah uji', 'logo' => null]);
    $class = KelasFactory::new()->create(['nama_kelas' => 'VII A']);
    $teacher = GuruFactory::new()->create(['user_id' => User::factory()->create(['name' => 'Hidayatul Maratin'])->id]);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => 'Matematika']);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $class->id, 'guru_id' => $teacher->id, 'mata_pelajaran' => $subject->id]);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $class->id, 'hari' => 'Minggu', 'jam_mulai' => '10:00:00', 'jam_selesai' => '11:00:00']);

    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Genap', '2026/2027');
    $rows = jadwalWorkbookSheets($this->exportPath)[0]['rows'];

    expect($rows[0][1])->toBe('Pondok Uji')
        ->and($rows[2][1])->toBe('Alamat sekolah uji')
        ->and($rows[4][0])->toBe('JADWAL PELAJARAN')
        ->and($rows[5][0])->toBe('SEMESTER GENAP 2026/2027')
        ->and($rows[6][0])->toBe('Kelas: VII A')
        ->and($rows[7])->toBe(['JAM', 'SABTU', 'AHAD', 'SENIN', 'SELASA', 'RABU', 'KAMIS'])
        ->and($rows[8][0])->toBe('07.00 - 09.00')
        ->and($rows[8][3])->toBe("Matematika\nHidayatul Maratin")
        ->and($rows[8][1])->toBe('')
        ->and($rows[9][0])->toBe('10.00 - 11.00');

    $zip = new ZipArchive;
    $zip->open($this->exportPath);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('orientation="landscape"', 'paperSize="9"', 'fitToWidth="1"', 'mergeCell ref="A5:G5"')
        ->and($zip->getFromName('xl/styles.xml'))->toContain('wrapText="1"', 'horizontal="center"', 'vertical="center"', 'style="thin"')
        ->and($zip->getFromName('xl/workbook.xml'))->toContain('Print_Titles', 'Kelas_'.$class->id.'!$1:$8');
    $zip->close();
});

test('keeps Friday and duplicate lessons while using safe sheet names and literal strings', function () {
    $class = KelasFactory::new()->create(['nama_kelas' => 'VII/A:*?[long]class name with invalid chars']);
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => '=1+1']);
    JadwalPelajaranFactory::new()->count(2)->create(['kelas_id' => $class->id, 'mata_pelajaran' => $subject->id, 'hari' => 'Jumat']);
    JadwalPelajaranFactory::new()->create();

    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Ganjil', '2027/2028');
    $sheets = jadwalWorkbookSheets($this->exportPath);

    expect($sheets)->toHaveCount(2)
        ->and(mb_strlen($sheets[0]['name']))->toBeLessThanOrEqual(31)
        ->and($sheets[0]['name'])->not->toMatch('/[\\\\\/:*?\[\]]/')
        ->and($sheets[0]['rows'][7][7])->toBe('JUMAT')
        ->and(substr_count($sheets[0]['rows'][8][7], '=1+1'))->toBe(2);
    $zip = new ZipArchive;
    $zip->open($this->exportPath);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->not->toContain('<f>');
    $zip->close();
});

test('honors the input query and selected class and produces an honest empty timetable', function () {
    $selected = KelasFactory::new()->create(['nama_kelas' => 'VIII B']);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $selected->id]);
    JadwalPelajaranFactory::new()->create();
    $query = JadwalPelajaran::query()->where('hari', 'Sabtu');
    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, $query, 'Genap', '2026/2027', $selected->id);
    $sheets = jadwalWorkbookSheets($this->exportPath);

    expect($sheets)->toHaveCount(1)
        ->and($sheets[0]['rows'][6][0])->toBe('Kelas: VIII B')
        ->and($sheets[0]['rows'][8])->toBe(array_fill(0, 7, ''))
        ->and($sheets[0]['rows'][9][0])->toContain('Tidak ada jadwal');
});

test('handles missing subject references and null times without losing the lesson', function () {
    JadwalPelajaranFactory::new()->create(['mata_pelajaran' => '999999', 'jam_mulai' => null, 'jam_selesai' => null]);
    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Genap', '2026/2027');
    $rows = jadwalWorkbookSheets($this->exportPath)[0]['rows'];
    expect($rows[8][0])->toBe('— - —')
        ->and($rows[8][3])->toContain('Mapel tidak tersedia');
});

test('embeds the configured logo on every class worksheet with valid XML relationships', function () {
    Storage::fake('public');
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    Storage::disk('public')->put('logo.png', $image);
    SiteSetting::factory()->create(['logo' => 'logo.png']);
    JadwalPelajaranFactory::new()->count(2)->create();
    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Genap', '2026/2027');
    $zip = new ZipArchive;
    expect($zip->open($this->exportPath, ZipArchive::CHECKCONS))->toBeTrue();
    expect($zip->getFromName('xl/media/jadwal-logo1.png'))->toBe($image);

    for ($i = 1; $i <= 2; $i++) {
        $sheet = new DOMDocument;
        expect($sheet->loadXML($zip->getFromName('xl/worksheets/sheet'.$i.'.xml')))->toBeTrue()
            ->and($sheet->getElementsByTagName('drawing')->length)->toBe(1);
        $rels = $zip->getFromName('xl/worksheets/_rels/sheet'.$i.'.xml.rels');
        expect($rels)->toContain('../drawings/jadwal-logo'.$i.'.xml')
            ->and($zip->getFromName('xl/drawings/_rels/jadwal-logo'.$i.'.xml.rels'))->toContain('../media/jadwal-logo1.png');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
            expect((new DOMDocument)->loadXML($zip->getFromName($name)))->toBeTrue();
        }
    }

    $zip->close();
    expect(jadwalWorkbookSheets($this->exportPath))->toHaveCount(2);
});

test('downloads the real workbook through the table action with table search applied', function () {
    $this->actingAs(jadwalExportUser());
    $included = KelasFactory::new()->create(['nama_kelas' => 'Kelas Terpilih']);
    JadwalPelajaranFactory::new()->create(['kelas_id' => $included->id]);
    JadwalPelajaranFactory::new()->create();

    $component = Livewire::test(ListJadwalPelajarans::class)
        ->searchTable('Kelas Terpilih')
        ->callAction(TestAction::make('exportExcel')->table(), data: ['semester' => 'Genap', 'tahun_ajaran' => '2026/2027', 'kelas_id' => null])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('jadwal-pelajaran-genap-2026-2027.xlsx');

    $download = $component->effects['download'];
    file_put_contents($this->exportPath, base64_decode($download['content']));
    $sheets = jadwalWorkbookSheets($this->exportPath);
    expect($sheets)->toHaveCount(1)->and($sheets[0]['rows'][6][0])->toBe('Kelas: Kelas Terpilih');
});

test('rejects invalid export headings and nonexistent classes', function (array $data, string $field) {
    $this->actingAs(jadwalExportUser());
    Livewire::test(ListJadwalPelajarans::class)
        ->callAction(TestAction::make('exportExcel')->table(), data: array_replace(['semester' => 'Genap', 'tahun_ajaran' => '2026/2027', 'kelas_id' => null], $data))
        ->assertHasActionErrors([$field])
        ->assertNoFileDownloaded();
})->with([
    [['tahun_ajaran' => '2026/2028'], 'tahun_ajaran'],
    [['tahun_ajaran' => 'invalid'], 'tahun_ajaran'],
    [['semester' => 'Tidak valid'], 'semester'],
    [['kelas_id' => 999999], 'kelas_id'],
]);

test('splits large timetable cells into printable rows without dropping lessons', function () {
    $class = KelasFactory::new()->create();
    $subject = MataPelajaranFactory::new()->create(['nama_mapel' => str_repeat('Matematika ', 8)]);
    JadwalPelajaranFactory::new()->count(12)->create(['kelas_id' => $class->id, 'mata_pelajaran' => $subject->id]);
    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Genap', '2026/2027');
    $rows = jadwalWorkbookSheets($this->exportPath)[0]['rows'];
    $text = collect($rows)->skip(8)->pluck(3)->implode('');

    expect(count($rows))->toBeGreaterThan(9)
        ->and(substr_count(str_replace("\n", '', $text), 'Matematika'))->toBe(96);
    $zip = new ZipArchive;
    $zip->open($this->exportPath);
    $document = new DOMDocument;
    $document->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));

    foreach ($document->getElementsByTagName('row') as $row) {
        expect((float) $row->getAttribute('ht'))->toBeLessThanOrEqual(409);
    }

    $zip->close();
});

test('converts configured WebP logos to PNG when GD supports WebP', function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('WebP conversion requires GD with WebP support.');
    }

    Storage::fake('public');
    $image = imagecreatetruecolor(10, 10);
    ob_start();
    imagewebp($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    Storage::disk('public')->put('logo.webp', $bytes);
    SiteSetting::factory()->create(['logo' => 'logo.webp']);
    app(JadwalPelajaranWorkbook::class)->write($this->exportPath, JadwalPelajaran::query(), 'Genap', '2026/2027');
    $zip = new ZipArchive;
    $zip->open($this->exportPath);
    expect(getimagesizefromstring($zip->getFromName('xl/media/jadwal-logo1.png'))[2])->toBe(IMAGETYPE_PNG);
    $zip->close();
});

test('denies schedule access to users without schedule permissions', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(ListJadwalPelajarans::class)->assertForbidden();
});
