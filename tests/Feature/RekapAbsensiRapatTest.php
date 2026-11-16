<?php

use App\Filament\Exports\AbsenRapatWorkbook;
use App\Filament\Resources\AbsenRapats\AbsenRapatResource;
use App\Filament\Resources\AbsenRapats\Pages\ListAbsenRapats;
use App\Filament\Resources\AbsenRapats\Tables\AbsenRapatsTable;
use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AbsenRapatFactory;
use Database\Factories\GuruFactory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Storage::fake(AbsenRapat::FOTO_DISK);
});

afterEach(function () {
    $this->travelBack();
});

function rapatRecapTeacher(): Guru
{
    return GuruFactory::new()->create();
}

/** @return array<string, list<array<int, mixed>>> */
function rapatWorkbookSheets(array $download): array
{
    $path = tempnam(sys_get_temp_dir(), 'rapat-export-');
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

/** @param array<string, mixed> $attributes */
function rapatRecapRecord(Guru $teacher, array $attributes = []): AbsenRapat
{
    return AbsenRapatFactory::new()->create([
        'guru_id' => $teacher->id,
        'nama_guru' => $teacher->user->name,
        ...$attributes,
    ]);
}

test('shows the recap menu in the Laporan Kehadiran group as a read only resource', function () {
    expect(AbsenRapatResource::getNavigationGroup())->toBe('Laporan Kehadiran')
        ->and(AbsenRapatResource::getNavigationLabel())->toBe('Rekap Absensi Rapat')
        ->and(AbsenRapatResource::getSlug())->toBe('rekap-absensi-rapat')
        ->and(AbsenRapatResource::canCreate())->toBeFalse();
});

test('redirects guests and forbids accounts without a teacher profile', function () {
    $this->get(AbsenRapatResource::getUrl('index'))
        ->assertRedirectToRoute('filament.admin.auth.login');

    $this->actingAs(User::factory()->create());
    $this->get(AbsenRapatResource::getUrl('index'))->assertForbidden();
    Livewire::test(ListAbsenRapats::class)->assertForbidden();
});

test('lists meeting attendance with date guru agenda and times', function () {
    $teacher = rapatRecapTeacher();
    rapatRecapRecord($teacher, [
        'nama_guru' => 'Ustadz Ahmad',
        'agenda' => 'Rapat Kurikulum',
        'tanggal' => '2026-10-05',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '10:00:00',
    ]);
    $this->actingAs($teacher->user);

    Livewire::test(ListAbsenRapats::class)
        ->assertOk()
        ->assertSee('Rapat Kurikulum')
        ->assertSee('Ustadz Ahmad')
        ->assertSee('05/10/2026')
        ->assertSee('08:00')
        ->assertSee('10:00');
});

test('filters the recap by month', function () {
    $teacher = rapatRecapTeacher();
    rapatRecapRecord($teacher, ['agenda' => 'Rapat Oktober', 'tanggal' => '2026-10-05']);
    rapatRecapRecord($teacher, ['agenda' => 'Rapat November', 'tanggal' => '2026-11-10']);
    $this->actingAs($teacher->user);

    Livewire::test(ListAbsenRapats::class)
        ->filterTable('bulan', '10')
        ->assertSee('Rapat Oktober')
        ->assertDontSee('Rapat November')
        ->filterTable('bulan', null)
        ->assertSee('Rapat November');
});

test('exports the meeting recap following the active month filter', function () {
    $teacher = rapatRecapTeacher();
    $october = rapatRecapRecord($teacher, [
        'agenda' => 'Rapat Oktober',
        'tanggal' => '2026-10-05',
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '10:00:00',
    ]);
    $october->jadwalRapat->update(['notulensi' => "## Keputusan\n\n- Menambah jam tahfiz"]);

    rapatRecapRecord($teacher, ['agenda' => 'Rapat November', 'tanggal' => '2026-11-10']);
    $this->actingAs($teacher->user);

    $component = Livewire::test(ListAbsenRapats::class)
        ->filterTable('bulan', '10')
        ->callAction(TestAction::make('eksporExcel')->table())
        ->assertFileDownloaded('rekap-absensi-rapat-2026-10-19.xlsx');

    $sheets = rapatWorkbookSheets($component->effects['download']);
    $rows = $sheets['Rekap Absensi Rapat'];

    expect($rows[1][0])->toBe('REKAP ABSENSI RAPAT')
        ->and($rows[4])->toBe(AbsenRapatWorkbook::HEADERS)
        ->and($rows[5][1])->toBe('05/10/2026')
        ->and($rows[5][2])->toBe('Rapat Oktober')
        ->and($rows[5][4])->toBe('08:00')
        ->and($rows[5][5])->toBe('10:00');

    expect(collect($rows)->skip(5)->pluck(2)->filter()->all())->toBe(['Rapat Oktober']);

    $notulensi = $sheets['Notulensi Rapat'];

    expect($notulensi[1][0])->toBe('NOTULENSI RAPAT')
        ->and($notulensi[3])->toBe(AbsenRapatWorkbook::NOTULENSI_HEADERS)
        ->and($notulensi[4][2])->toBe('Rapat Oktober')
        ->and($notulensi[4][5])->toBe("## Keputusan\n\n- Menambah jam tahfiz")
        ->and(collect($notulensi)->skip(4)->pluck(2)->filter()->all())->toBe(['Rapat Oktober']);
});

test('provides a photo preview and a view photo action with a temporary url', function () {
    $teacher = rapatRecapTeacher();
    $record = rapatRecapRecord($teacher, [
        'agenda' => 'Rapat Evaluasi',
        'tanggal' => '2026-10-05',
        'foto' => 'absen-rapat/2026-10-05/selfie.jpg',
    ]);
    $url = AbsenRapatsTable::fotoUrl($record->foto);
    $this->actingAs($teacher->user);

    expect($url)->toBeString()->not->toBeEmpty()
        ->and(AbsenRapatsTable::fotoUrl(null))->toBeNull();

    Livewire::test(ListAbsenRapats::class)
        ->assertTableActionExists('lihatFoto')
        ->assertTableActionHasUrl('lihatFoto', $url, $record)
        ->assertTableActionShouldOpenUrlInNewTab('lihatFoto', $record);
});
