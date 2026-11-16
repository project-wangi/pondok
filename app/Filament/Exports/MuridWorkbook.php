<?php

namespace App\Filament\Exports;

use App\Models\Kelas;
use App\Models\Murid;
use Illuminate\Database\Eloquent\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MuridWorkbook
{
    /** @var list<string> */
    public const HEADERS = [
        'NIS',
        'NISN',
        'Nama',
        'Jenis Kelamin',
        'Email',
        'No. HP',
        'Kelas',
        'NIK',
        'No. KK',
        'Alamat',
        'Status',
        'Tanggal Lulus',
        'Tanggal Keluar',
        'Tanggal Masuk',
        'Nama Ayah',
        'Kategori Ayah',
        'NIK Ayah',
        'Pekerjaan Ayah',
        'Penghasilan Ayah',
        'No. HP Ayah',
        'Nama Ibu',
        'Kategori Ibu',
        'NIK Ibu',
        'Pekerjaan Ibu',
        'Penghasilan Ibu',
        'No. HP Ibu',
    ];

    /**
     * Judul kolom template unggahan, dengan petunjuk nilai yang diterima
     * agar pengisi tahu pilihan yang sah.
     *
     * @var list<string>
     */
    /** @var list<string> */
    public const TEMPLATE_HEADERS = [
        'NIS',
        'NISN',
        'Nama',
        'Jenis Kelamin (L/P)',
        'Email',
        'No. HP',
        'Kelas (lihat sheet Petunjuk)',
        'NIK',
        'No. KK',
        'Alamat',
        'Status (Aktif/Lulus/Keluar)',
        'Tanggal Lulus (YYYY-MM-DD)',
        'Tanggal Keluar (YYYY-MM-DD)',
        'Tanggal Masuk (YYYY-MM-DD)',
        'Nama Ayah',
        'Kategori Ayah (Kandung/Tiri)',
        'NIK Ayah',
        'Pekerjaan Ayah',
        'Penghasilan Ayah',
        'No. HP Ayah',
        'Nama Ibu',
        'Kategori Ibu (Kandung/Tiri)',
        'NIK Ibu',
        'Pekerjaan Ibu',
        'Penghasilan Ibu',
        'No. HP Ibu',
    ];

    /** @param Collection<int, Murid> $murids */
    public function download(Collection $murids): StreamedResponse
    {
        $filename = 'data-siswa-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($murids): void {
            $this->write('php://output', $murids);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Berkas contoh kosong: baris judul kolom + sheet petunjuk pilihan. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer(new Options);
            $writer->openToFile('php://output');

            try {
                $writer->getCurrentSheet()->setName('Data Siswa');
                $writer->addRow(Row::fromValues(self::TEMPLATE_HEADERS, $this->headerStyle()));

                $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');

                foreach ($this->petunjukRows() as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            } finally {
                $writer->close();
            }
        }, 'template-data-siswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  Collection<int, Murid>  $murids
     */
    public function write(string $path, Collection $murids): void
    {
        $writer = new Writer(new Options);
        $writer->openToFile($path);

        try {
            $writer->addRow($this->headerRow());

            foreach ($murids as $murid) {
                $writer->addRow(Row::fromValues([
                    (string) ($murid->nis ?? ''),
                    (string) ($murid->nisn ?? ''),
                    (string) $murid->nama,
                    (string) $murid->jenis_kelamin,
                    (string) $murid->email,
                    (string) ($murid->nohp_siswa ?? ''),
                    (string) ($murid->kelas?->nama_kelas ?? ''),
                    (string) ($murid->nik ?? ''),
                    (string) ($murid->no_kk ?? ''),
                    (string) ($murid->alamat ?? ''),
                    (string) ($murid->status ?? Murid::STATUS_AKTIF),
                    $murid->tanggal_lulus?->format('Y-m-d') ?? '',
                    $murid->tanggal_keluar?->format('Y-m-d') ?? '',
                    $murid->tanggal_masuk?->format('Y-m-d') ?? '',
                    (string) ($murid->nama_ayah ?? ''),
                    (string) ($murid->status_ayah ?? ''),
                    (string) ($murid->nik_ayah ?? ''),
                    (string) ($murid->pekerjaan_ayah ?? ''),
                    (string) ($murid->penghasilan_ayah ?? ''),
                    (string) ($murid->nohp_ayah ?? ''),
                    (string) ($murid->nama_ibu ?? ''),
                    (string) ($murid->status_ibu ?? ''),
                    (string) ($murid->nik_ibu ?? ''),
                    (string) ($murid->pekerjaan_ibu ?? ''),
                    (string) ($murid->penghasilan_ibu ?? ''),
                    (string) ($murid->nohp_ibu ?? ''),
                ]));
            }
        } finally {
            $writer->close();
        }
    }

    private function headerRow(): Row
    {
        return Row::fromValues(self::HEADERS, $this->headerStyle());
    }

    private function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);
    }

    /**
     * Isi sheet "Petunjuk": aturan pengisian dan daftar pilihan yang sah.
     *
     * @return list<list<string>>
     */
    private function petunjukRows(): array
    {
        $kelas = Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas')->all();

        $rows = [
            ['PETUNJUK PENGISIAN'],
            ['Isi data mulai baris ke-2 pada sheet "Data Siswa". Jangan mengubah nama kolom.'],
            [],
            ['Kolom', 'Keterangan'],
            ['NIS', 'Kosongkan untuk siswa baru. Jika NIS sudah terdaftar, datanya diperbarui.'],
            ['Nama', 'Wajib diisi.'],
            ['Email', 'Wajib diisi dan tidak boleh sama dengan siswa lain.'],
            ['Jenis Kelamin', 'Pilih salah satu: Laki-laki / Perempuan (boleh disingkat L / P).'],
            ['Kelas', 'Pilih nama kelas dari daftar di bawah. Kosongkan bila belum masuk kelas.'],
            ['Status', 'Pilih salah satu: Aktif / Lulus / Keluar. Kosong berarti Aktif.'],
            ['Tanggal Lulus', 'Format YYYY-MM-DD atau DD/MM/YYYY. Kosong = terisi otomatis saat status Lulus.'],
            ['Tanggal Keluar', 'Format YYYY-MM-DD atau DD/MM/YYYY. Kosong = terisi otomatis saat status Keluar.'],
            ['Tanggal Masuk', 'Format YYYY-MM-DD atau DD/MM/YYYY. Tanggal siswa mulai masuk madrasah.'],
            ['Nama Ayah / Nama Ibu', 'Opsional. Isi nama orang tua atau wali.'],
            ['Kategori Ayah / Kategori Ibu', 'Pilih Kandung atau Tiri. Kosong berarti Kandung.'],
            ['NIK / Pekerjaan / Penghasilan', 'Opsional. Penghasilan boleh ditulis seperti Rp2.000.000.'],
            ['No. HP Ayah / No. HP Ibu', 'Opsional. Nomor telepon orang tua atau wali.'],
            [],
            ['PILIHAN JENIS KELAMIN'],
            ['laki-laki', 'perempuan'],
            [],
            ['PILIHAN STATUS'],
            array_keys(Murid::STATUS),
            [],
            ['PILIHAN KATEGORI ORANG TUA'],
            array_keys(Murid::KATEGORI_ORANG_TUA),
            [],
            ['PILIHAN KELAS'],
        ];

        if ($kelas === []) {
            $rows[] = ['Belum ada kelas. Tambahkan kelas lewat menu Kelas terlebih dahulu.'];
        }

        foreach ($kelas as $nama) {
            $rows[] = [(string) $nama];
        }

        return $rows;
    }
}
