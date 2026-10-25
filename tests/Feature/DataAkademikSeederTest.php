<?php

use App\Models\AbsenPeminatan;
use App\Models\AbsenPeminatanMurid;
use App\Models\AbsenPiket;
use App\Models\AbsenPiketMurid;
use App\Models\AsesmenFormatif;
use App\Models\AsesmenFormatifNilai;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPeminatan;
use App\Models\JadwalPiket;
use App\Models\JenisPeminatan;
use App\Models\JurnalMengajar;
use App\Models\JurnalMengajarBaris;
use App\Models\Kelas;
use App\Models\LingkupMateri;
use App\Models\Murid;
use App\Models\PresensiMurid;
use App\Models\SesiMengajar;
use App\Models\SikapKeaktifan;
use App\Models\SikapKeaktifanNilai;
use App\Models\TujuanPembelajaran;
use Database\Seeders\DataAkademikSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(DataAkademikSeeder::class);
});

test('the academic seeder fills every requested entity', function () {
    expect(Murid::count())->toBe(72)
        ->and(JadwalPelajaran::count())->toBe(108)
        ->and(SesiMengajar::count())->toBe(216)
        ->and(PresensiMurid::count())->toBe(2592)
        ->and(JadwalPiket::count())->toBe(6)
        ->and(AbsenPiket::count())->toBe(36)
        ->and(AbsenPiketMurid::count())->toBe(432)
        ->and(JenisPeminatan::count())->toBe(3)
        ->and(JadwalPeminatan::count())->toBe(3)
        ->and(AbsenPeminatan::count())->toBe(6)
        ->and(AbsenPeminatanMurid::count())->toBe(144)
        ->and(LingkupMateri::count())->toBe(72)
        ->and(TujuanPembelajaran::count())->toBe(216)
        ->and(JurnalMengajar::count())->toBe(216)
        ->and(JurnalMengajarBaris::count())->toBe(216)
        ->and(AsesmenFormatif::count())->toBe(18)
        ->and(AsesmenFormatifNilai::count())->toBe(2592)
        ->and(SikapKeaktifan::count())->toBe(18)
        ->and(SikapKeaktifanNilai::count())->toBe(216);
});

test('the seeder covers classes seven through twelve with twelve students each', function () {
    $classes = Kelas::query()->orderBy('id')->get();

    expect($classes->pluck('nama_kelas')->all())->toBe(['VII', 'VIII', 'IX', 'X', 'XI', 'XII']);

    foreach ($classes as $class) {
        expect(Murid::where('kelas_id', $class->id)->count())->toBe(12);
    }

    expect(JadwalPelajaran::query()->distinct()->pluck('kelas_id')->count())->toBe(6);
});

test('friday is a weekly holiday without schedules or attendance', function () {
    expect(JadwalPelajaran::where('hari', 'Jumat')->exists())->toBeFalse()
        ->and(JadwalPiket::where('hari', 'Jumat')->exists())->toBeFalse()
        ->and(JadwalPeminatan::where('hari', 'Jumat')->exists())->toBeFalse();

    $datedRecords = [
        SesiMengajar::pluck('tanggal'),
        JurnalMengajar::pluck('tanggal'),
        AbsenPiket::pluck('tanggal'),
        AbsenPeminatan::pluck('tanggal'),
    ];

    foreach ($datedRecords as $dates) {
        expect($dates)->not->toBeEmpty()
            ->and($dates->every(fn ($date): bool => $date->dayOfWeek !== 5))->toBeTrue();
    }
});

test('specialization schedules and attendance only happen on thursday', function () {
    expect(JadwalPeminatan::count())->toBe(3)
        ->and(JadwalPeminatan::where('hari', 'Kamis')->count())->toBe(3)
        ->and(AbsenPeminatan::count())->toBe(6)
        ->and(AbsenPeminatan::pluck('tanggal')->every(fn ($date): bool => $date->dayOfWeek === 4))->toBeTrue();
});

test('running the seeder again does not duplicate rows', function () {
    $counts = [
        'murid' => Murid::count(),
        'jadwal' => JadwalPelajaran::count(),
        'sesi' => SesiMengajar::count(),
        'presensi' => PresensiMurid::count(),
        'piket' => AbsenPiket::count(),
        'piket_murid' => AbsenPiketMurid::count(),
        'peminatan' => AbsenPeminatan::count(),
        'peminatan_murid' => AbsenPeminatanMurid::count(),
        'lingkup' => LingkupMateri::count(),
        'tujuan' => TujuanPembelajaran::count(),
        'jurnal' => JurnalMengajar::count(),
        'jurnal_baris' => JurnalMengajarBaris::count(),
        'asesmen' => AsesmenFormatif::count(),
        'asesmen_nilai' => AsesmenFormatifNilai::count(),
        'sikap' => SikapKeaktifan::count(),
        'sikap_nilai' => SikapKeaktifanNilai::count(),
    ];

    $this->seed(DataAkademikSeeder::class);

    expect([
        'murid' => Murid::count(),
        'jadwal' => JadwalPelajaran::count(),
        'sesi' => SesiMengajar::count(),
        'presensi' => PresensiMurid::count(),
        'piket' => AbsenPiket::count(),
        'piket_murid' => AbsenPiketMurid::count(),
        'peminatan' => AbsenPeminatan::count(),
        'peminatan_murid' => AbsenPeminatanMurid::count(),
        'lingkup' => LingkupMateri::count(),
        'tujuan' => TujuanPembelajaran::count(),
        'jurnal' => JurnalMengajar::count(),
        'jurnal_baris' => JurnalMengajarBaris::count(),
        'asesmen' => AsesmenFormatif::count(),
        'asesmen_nilai' => AsesmenFormatifNilai::count(),
        'sikap' => SikapKeaktifan::count(),
        'sikap_nilai' => SikapKeaktifanNilai::count(),
    ])->toBe($counts);
});
