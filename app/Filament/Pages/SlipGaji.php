<?php

namespace App\Filament\Pages;

use App\Models\Guru;
use App\Models\SiteSetting;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SlipGaji extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Slip Gaji';

    protected static ?string $title = 'Slip Gaji';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'slip-gaji';

    protected string $view = 'filament.pages.slip-gaji';

    public int $bulan = 1;

    public int $tahun = 2026;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $now = CarbonImmutable::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
    }

    public function updatedBulan(mixed $value): void
    {
        $this->bulan = min(12, max(1, (int) $value));
    }

    public function updatedTahun(mixed $value): void
    {
        $this->tahun = min(2100, max(2000, (int) $value));
    }

    /**
     * Slip gaji guru yang sedang masuk untuk bulan terpilih.
     *
     * @return array<string, mixed>
     */
    public function rincian(): array
    {
        abort_unless(static::canAccess(), 403);

        $guru = Filament::auth()->user()?->guru()->with('user')->first();
        abort_if($guru === null, 403);

        return GajiGuru::rincianGuru($guru, $this->bulan, $this->tahun);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rincian = $this->rincian();
        $tampilan = self::komponenRincian($rincian);
        $currentYear = (int) CarbonImmutable::now()->year;
        $years = range($currentYear - 3, $currentYear + 1);

        if (! in_array($this->tahun, $years, true)) {
            $years[] = $this->tahun;
            sort($years);
        }

        return [
            'rincian' => $rincian,
            'sekolah' => SiteSetting::current()->school_name,
            'bulanOptions' => GajiGuru::BULAN,
            'tahunOptions' => $years,
            'komponen' => $tampilan['komponen'],
            'angka' => $tampilan['angka'],
            'rupiah' => $tampilan['rupiah'],
        ];
    }

    /**
     * Data komponen slip yang dipakai halaman admin dan halaman cetak.
     *
     * @param  array<string, mixed>  $rincian
     * @return array{komponen: list<array{label: string, rincian: string, jumlah: string}>, angka: Closure, rupiah: Closure}
     */
    public static function komponenRincian(array $rincian): array
    {
        $rupiah = fn (int $value): string => 'Rp '.number_format($value, 0, ',', '.');
        $angka = function (float $value): string {
            $formatted = rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');

            return $formatted === '' ? '0' : $formatted;
        };

        $jabatanRincian = $rincian['jabatan_items'] === []
            ? 'Tidak ada tunjangan jabatan'
            : implode(', ', array_map(fn (array $item): string => $item['nama'], $rincian['jabatan_items']));

        $peminatanRincian = $rincian['peminatan_items'] === []
            ? 'Tidak ada absensi peminatan'
            : implode(', ', array_map(
                fn (array $item): string => $item['nama'].' × '.$item['jumlah'].' ('.$rupiah($item['total']).')',
                $rincian['peminatan_items'],
            ));

        $komponen = [
            [
                'label' => 'Mengajar',
                'rincian' => $angka($rincian['jp']).' JP × Rp10.000',
                'jumlah' => $rupiah($rincian['mengajar']),
            ],
            [
                'label' => 'Transport',
                'rincian' => $angka($rincian['jarak_rumah']).' km × Rp1.000 × '.$rincian['hari_hadir'].' hari hadir',
                'jumlah' => $rupiah($rincian['transport']),
            ],
            [
                'label' => 'Jabatan',
                'rincian' => $jabatanRincian,
                'jumlah' => $rupiah($rincian['jabatan']),
            ],
            [
                'label' => 'Rapat',
                'rincian' => $rincian['rapat_count'].' rapat × Rp20.000',
                'jumlah' => $rupiah($rincian['rapat']),
            ],
            [
                'label' => 'Peminatan',
                'rincian' => $peminatanRincian,
                'jumlah' => $rupiah($rincian['peminatan']),
            ],
            [
                'label' => 'Piket',
                'rincian' => $rincian['piket_count'].' hari × Rp10.000',
                'jumlah' => $rupiah($rincian['piket']),
            ],
        ];

        return compact('komponen', 'angka', 'rupiah');
    }
}
