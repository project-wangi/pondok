<?php

namespace App\Http\Controllers;

use App\Filament\Pages\GajiGuru;
use App\Filament\Pages\SlipGaji;
use App\Models\Guru;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lembar cetak berisi slip gaji seluruh guru untuk satu periode.
 */
class SlipGajiPrintAllController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->can('View:GajiGuru') ?? false, 403);

        $now = CarbonImmutable::now();
        $bulan = min(12, max(1, (int) $request->query('bulan', $now->month)));
        $tahun = min(2100, max(2000, (int) $request->query('tahun', $now->year)));

        $penandatangan = SlipGaji::penandatangan();
        $sekolah = SiteSetting::current()->school_name;

        $slips = Guru::query()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Guru $guru): string => mb_strtolower((string) $guru->user?->name))
            ->values()
            ->map(function (Guru $guru) use ($bulan, $tahun, $penandatangan, $sekolah, $now): array {
                $rincian = GajiGuru::rincianGuru($guru, $bulan, $tahun);
                $tampilan = SlipGaji::komponenRincian($rincian);

                return [
                    'rincian' => $rincian,
                    'komponen' => $tampilan['komponen'],
                    'angka' => $tampilan['angka'],
                    'rupiah' => $tampilan['rupiah'],
                    'sekolah' => $sekolah,
                    'bendahara' => $penandatangan['bendahara'],
                    'kepalaYayasan' => $penandatangan['kepalaYayasan'],
                    'dicetak' => $now,
                ];
            })
            ->all();

        return view('slip-gaji-print-all', [
            'slips' => $slips,
            'periode' => CarbonImmutable::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y'),
        ]);
    }
}
