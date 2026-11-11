<?php

namespace App\Filament\Imports;

use App\Models\Kelas;
use App\Models\Murid;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

class MuridImporter
{
    private const MAKS_GALAT = 50;

    /**
     * Baca berkas XLSX/CSV lalu buat atau perbarui data siswa.
     *
     * Baris dicocokkan berdasarkan NIS: NIS yang sudah ada diperbarui,
     * NIS kosong atau belum terdaftar akan dibuat sebagai siswa baru.
     *
     * Baris yang tidak valid dilewati tanpa membatalkan baris lainnya.
     *
     * @return array{dibuat: int, diperbarui: int, gagal: list<string>, jumlah_gagal: int}
     */
    public function import(string $path): array
    {
        $reader = $this->reader($path);
        $reader->open($path);

        $dibuat = 0;
        $diperbarui = 0;
        $gagal = [];
        $jumlahGagal = 0;
        $baris = 0;
        $kolom = [];
        $kelas = $this->petaKelas();

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $baris++;

                    $nilai = array_map(fn (Cell $cell): mixed => $this->nilaiSel($cell), $row->getCells());

                    if ($baris === 1) {
                        $kolom = $this->petaKolom($nilai);

                        continue;
                    }

                    if ($this->semuaKosong($nilai)) {
                        continue;
                    }

                    $hasil = $this->simpanBaris($nilai, $kolom, $kelas, $baris);

                    if (! $hasil['ok']) {
                        $jumlahGagal++;

                        if (count($gagal) < self::MAKS_GALAT) {
                            $gagal[] = $hasil['galat'];
                        }

                        continue;
                    }

                    $hasil['diperbarui'] ? $diperbarui++ : $dibuat++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return [
            'dibuat' => $dibuat,
            'diperbarui' => $diperbarui,
            'gagal' => $gagal,
            'jumlah_gagal' => $jumlahGagal,
        ];
    }

    private function reader(string $path): ReaderInterface
    {
        return str_ends_with(strtolower($path), '.xlsx') ? new XlsxReader : new CsvReader;
    }

    /** @return array<string, int> */
    private function petaKelas(): array
    {
        return Kelas::query()
            ->get()
            ->mapWithKeys(fn (Kelas $kelas): array => [mb_strtolower(trim($kelas->nama_kelas)) => $kelas->id])
            ->all();
    }

    /**
     * @param  list<mixed>  $nilai
     * @return array<string, int>
     */
    private function petaKolom(array $nilai): array
    {
        $peta = [];

        foreach ($nilai as $index => $judul) {
            $kunci = mb_strtolower(trim((string) $judul));
            // Buang keterangan dalam tanda kurung, mis. "Status (Aktif/Lulus/Keluar)".
            $kunci = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $kunci));

            if ($kunci !== '') {
                $peta[$kunci] = $index;
            }
        }

        return $peta;
    }

    /**
     * @param  list<mixed>  $nilai
     * @param  array<string, int>  $kolom
     * @param  array<string, int>  $kelas
     * @return array{ok: true, diperbarui: bool}|array{ok: false, galat: string}
     */
    private function simpanBaris(array $nilai, array $kolom, array $kelas, int $baris): array
    {
        $ambil = fn (string $judul): string => trim((string) ($nilai[$kolom[$judul] ?? -1] ?? ''));

        $nama = $ambil('nama');
        $label = $nama === '' ? "Baris {$baris}" : "Baris {$baris} ({$nama})";

        if ($nama === '') {
            return ['ok' => false, 'galat' => "{$label}: kolom Nama wajib diisi."];
        }

        $email = mb_strtolower($ambil('email'));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'galat' => "{$label}: email tidak valid."];
        }

        $jenisKelamin = $this->jenisKelamin($ambil('jenis kelamin'));

        if ($jenisKelamin === null) {
            return ['ok' => false, 'galat' => "{$label}: jenis kelamin harus Laki-laki atau Perempuan."];
        }

        $status = $this->status($ambil('status'));

        if ($status === null) {
            return ['ok' => false, 'galat' => "{$label}: status harus Aktif, Lulus, atau Keluar."];
        }

        $namaKelas = mb_strtolower($ambil('kelas'));
        $kelasId = null;

        if ($namaKelas !== '') {
            if (! isset($kelas[$namaKelas])) {
                return ['ok' => false, 'galat' => "{$label}: kelas \"{$ambil('kelas')}\" belum terdaftar."];
            }

            $kelasId = $kelas[$namaKelas];
        }

        $nis = $ambil('nis');
        $murid = $nis !== '' ? Murid::query()->where('nis', $nis)->first() : null;

        // Bila NIS kosong atau belum terdaftar, cocokkan lewat email supaya unggah
        // ulang berkas yang sama tidak membuat data ganda.
        $murid ??= Murid::query()->where('email', $email)->first();

        $emailDipakai = Murid::query()
            ->where('email', $email)
            ->when($murid !== null, fn ($query) => $query->whereKeyNot($murid->id))
            ->exists();

        if ($emailDipakai) {
            return ['ok' => false, 'galat' => "{$label}: email {$email} sudah dipakai siswa lain."];
        }

        $atribut = [
            'nama' => $nama,
            'email' => $email,
            'jenis_kelamin' => $jenisKelamin,
            'status' => $status,
            'nis' => $nis !== '' ? $nis : null,
            'nisn' => $this->nullable($ambil('nisn')),
            'nik' => $this->nullable($ambil('nik')),
            'no_kk' => $this->nullable($ambil('no. kk')),
            'alamat' => $this->nullable($ambil('alamat')),
            'nohp_siswa' => $this->nullable($ambil('no. hp')),
            'kelas_id' => $kelasId,
        ];

        try {
            $tanggalLulus = $this->tanggal($ambil('tanggal lulus'));
            $tanggalKeluar = $this->tanggal($ambil('tanggal keluar'));
        } catch (Throwable) {
            return ['ok' => false, 'galat' => "{$label}: format tanggal harus YYYY-MM-DD atau DD/MM/YYYY."];
        }

        if ($tanggalLulus !== null) {
            $atribut['tanggal_lulus'] = $tanggalLulus;
        }

        if ($tanggalKeluar !== null) {
            $atribut['tanggal_keluar'] = $tanggalKeluar;
        }

        try {
            if ($murid !== null) {
                $murid->update($atribut);

                return ['ok' => true, 'diperbarui' => true];
            }

            Murid::query()->create($atribut);

            return ['ok' => true, 'diperbarui' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'galat' => "{$label}: data gagal disimpan."];
        }
    }

    private function jenisKelamin(string $nilai): ?string
    {
        return match (mb_strtolower(trim($nilai))) {
            'l', 'lk', 'laki-laki', 'laki laki', 'pria' => 'laki-laki',
            'p', 'pr', 'perempuan', 'wanita' => 'perempuan',
            default => null,
        };
    }

    private function status(string $nilai): ?string
    {
        if (trim($nilai) === '') {
            return Murid::STATUS_AKTIF;
        }

        foreach (array_keys(Murid::STATUS) as $status) {
            if (mb_strtolower($status) === mb_strtolower(trim($nilai))) {
                return $status;
            }
        }

        return null;
    }

    private function tanggal(string $nilai): ?CarbonImmutable
    {
        if ($nilai === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $tanggal = CarbonImmutable::createFromFormat($format, $nilai);

            if ($tanggal !== false) {
                return $tanggal->startOfDay();
            }
        }

        return CarbonImmutable::parse($nilai);
    }

    private function nullable(string $nilai): ?string
    {
        return $nilai === '' ? null : $nilai;
    }

    /** @param list<mixed> $nilai */
    private function semuaKosong(array $nilai): bool
    {
        foreach ($nilai as $sel) {
            if (trim((string) $sel) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nilaiSel(Cell $cell): mixed
    {
        $nilai = $cell->getValue();

        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_float($nilai) && floor($nilai) === $nilai) {
            return (string) (int) $nilai;
        }

        return $nilai;
    }
}
