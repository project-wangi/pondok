<?php

namespace App\Http\Controllers;

use App\Filament\Pages\RaporAdmin;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Murid;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lembar cetak rapor satu siswa. Dibuka di tab baru lalu dialog cetak browser
 * dipakai untuk menyimpan PDF, sama seperti cetak slip gaji.
 */
class RaporPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user !== null && $user->can('View:RaporAdmin'), 403);

        $kelas = Kelas::query()->findOrFail((int) $request->query('kelas'));
        $murid = Murid::query()
            ->where('kelas_id', $kelas->id)
            ->findOrFail((int) $request->query('murid'));

        $this->pastikanBolehMencetak($user, $kelas->id);

        $active = JadwalPelajaran::activePeriod();
        $semester = (string) $request->query('semester', $active['semester']);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $active['tahun_ajaran']);

        if (! array_key_exists($semester, JadwalPelajaran::SEMESTERS)) {
            $semester = $active['semester'];
        }

        if (trim($tahunAjaran) === '') {
            $tahunAjaran = $active['tahun_ajaran'];
        }

        $rapor = (new RaporAdmin)->laporanCetak($kelas, $murid, [
            'semester' => $semester,
            'tahun_ajaran' => $tahunAjaran,
        ]);

        return view('rapor-print', ['rapor' => $rapor]);
    }

    /**
     * Wali kelas hanya boleh mencetak rapor kelas yang ia walikan.
     */
    private function pastikanBolehMencetak(User $user, int $kelasId): void
    {
        if ($user->isAdministrator() || ! $user->hasRole('wali_kelas')) {
            return;
        }

        abort_unless((int) $user->guru()->value('kelas_id') === $kelasId, 403);
    }
}
