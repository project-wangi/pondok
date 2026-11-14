<?php

namespace App\Filament\Exports;

use App\Models\JenisPeminatan;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapAbsensiPeminatanWorkbook
{
    /**
     * @param  array<string, mixed>  $laporan
     */
    public function download(array $laporan): StreamedResponse
    {
        return response()->streamDownload(function () use ($laporan): void {
            $path = tempnam(sys_get_temp_dir(), 'rekap-peminatan-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor rekap absensi peminatan.');
            }

            try {
                $this->write($path, $laporan);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, $this->filename($laporan), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param array<string, mixed> $laporan */
    public function filename(array $laporan): string
    {
        $peminatan = $laporan['peminatan'] ?? null;
        $nama = ($peminatan instanceof JenisPeminatan ? $peminatan->nama_peminatan : 'peminatan')
            .' '.($laporan['bulanLabel'] ?? '');

        return 'rekap-absensi-peminatan-'.Str::slug($nama).'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $laporan
     */
    public function write(string $path, array $laporan): void
    {
        /** @var list<array{id: int, nomor: int, label: string, tanggal: mixed}> $columns */
        $columns = $laporan['columns'];
        $sessionCount = count($columns);
        $lastColumn = $sessionCount + 9;

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Peminatan');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(14, 2);
            $sheet->setColumnWidth(30, 3);
            $sheet->setColumnWidth(14, 4);
            $sheet->setColumnWidth(6, 5);

            for ($column = 6; $column <= 5 + $sessionCount; $column++) {
                $sheet->setColumnWidth(18, $column);
            }

            for ($column = 6 + $sessionCount; $column <= 9 + $sessionCount; $column++) {
                $sheet->setColumnWidth(6, $column);
            }

            $sheet->setColumnWidth(12, 10 + $sessionCount);

            $base = (new Style)
                ->setFontName('Arial')
                ->setFontSize(10)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $left = (clone $base)->setCellAlignment(CellAlignment::LEFT);
            $leftWrap = (clone $left)->setShouldWrapText();
            $bold = (clone $base)->setFontBold();
            $title = (clone $bold)->setFontSize(14);
            $subtitle = (clone $bold)->setFontSize(11);

            $border = new Border(...array_map(
                fn (string $side): BorderPart => new BorderPart($side, width: Border::WIDTH_THIN),
                [Border::TOP, Border::BOTTOM, Border::LEFT, Border::RIGHT],
            ));

            $header = (clone $bold)->setBorder($border);
            $headerWrap = (clone $header)->setShouldWrapText();
            $headerLeft = (clone $headerWrap)->setCellAlignment(CellAlignment::LEFT);
            $cell = (clone $base)->setBorder($border);
            $cellLeft = (clone $left)->setBorder($border);
            $count = (clone $cell)->setFormat('0');
            $percent = (clone $cell)->setFormat('0"%"');

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber, $lastColumn): void {
                $writer->addRow($this->row($values, $styles, $height, $lastColumn));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $peminatan = $laporan['peminatan'];

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, $lastColumn]]);
            $add(['REKAP ABSENSI PEMINATAN'], $title, 24, [[0, $lastColumn]]);
            $add(['Peminatan: '.($peminatan?->nama_peminatan ?? '-')], $subtitle, 20, [[0, $lastColumn]]);
            $add(['Bulan: '.($laporan['bulanLabel'] ?? '-')], $left, 18, [[0, $lastColumn]]);
            $add([], $base, 8);

            $headers = ['No', 'NIS', 'Nama Murid', 'Kelas', 'LP'];
            $headerStyles = [$header, $header, $headerLeft, $header, $header];

            foreach ($columns as $column) {
                $headers[] = $column['label'];
                $headerStyles[] = $headerWrap;
            }

            foreach (['H', 'I', 'S', 'A', '% Kehadiran'] as $label) {
                $headers[] = $label;
                $headerStyles[] = $header;
            }

            $add($headers, $headerStyles, 40);

            foreach ($laporan['rows'] as $index => $row) {
                $values = [
                    $index + 1,
                    (string) ($row['student']->nis ?? ''),
                    (string) $row['student']->nama,
                    (string) ($row['student']->kelas?->nama_kelas ?? ''),
                    $row['student']->jenisKelaminKode(),
                ];
                $styles = [$cell, $cellLeft, $cellLeft, $cell, $cell];

                foreach ($columns as $column) {
                    $values[] = $row['codes'][$column['id']] ?? '';
                    $styles[] = $cell;
                }

                foreach (['H', 'I', 'S', 'A'] as $code) {
                    $values[] = (int) $row['counts'][$code];
                    $styles[] = $count;
                }

                $values[] = $row['percent'] ?? '';
                $styles[] = $percent;

                $add($values, $styles, 20);
            }

            $add([], $base, 8);
            $add([
                'Persentase kehadiran dihitung dari status yang tercatat: Hadir ÷ (Hadir + Izin + Sakit + Alpa) × 100. '
                .'Pertemuan yang belum memiliki presensi tidak menurunkan persentase.',
            ], $leftWrap, 32, [[0, $lastColumn]]);
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  list<mixed>  $values
     * @param  Style|array<int, Style>  $styles
     */
    private function row(array $values, Style|array $styles, float $height, int $lastColumn): Row
    {
        $values = array_pad(array_values($values), $lastColumn + 1, '');
        $cells = [];

        foreach (array_slice($values, 0, $lastColumn + 1) as $index => $value) {
            $style = is_array($styles) ? ($styles[$index] ?? $styles[0]) : $styles;

            if ($value === null || $value === '') {
                $cells[] = new EmptyCell(null, $style);

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $cells[] = new NumericCell($value, $style);

                continue;
            }

            $cells[] = new StringCell((string) $value, $style);
        }

        return (new Row($cells))->setHeight($height);
    }
}
