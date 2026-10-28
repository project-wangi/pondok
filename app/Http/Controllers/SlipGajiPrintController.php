<?php

namespace App\Http\Controllers;

use App\Filament\Pages\GajiGuru;
use App\Filament\Pages\SlipGaji;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SlipGajiPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $guru = $request->user()?->guru()->with('user')->first();

        abort_if($guru === null, 403);

        $now = CarbonImmutable::now();
        $bulan = min(12, max(1, (int) $request->query('bulan', $now->month)));
        $tahun = min(2100, max(2000, (int) $request->query('tahun', $now->year)));
        $rincian = GajiGuru::rincianGuru($guru, $bulan, $tahun);
        $tampilan = SlipGaji::komponenRincian($rincian);

        return view('slip-gaji-print', [
            'rincian' => $rincian,
            'komponen' => $tampilan['komponen'],
            'angka' => $tampilan['angka'],
            'rupiah' => $tampilan['rupiah'],
            'sekolah' => SiteSetting::current()->school_name,
            'dicetak' => $now,
        ]);
    }
}
