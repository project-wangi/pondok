<?php

namespace App\Filament\Exports;

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

class RekapKehadiranGuruWorkbook
{
    /** @var array<string, string> */
    private const KODE_SUMBER = [
        'Mengajar' => 'M',
        'Absensi' => 'A',
        'Piket' => 'P',
        'Peminatan' => 'N',
        'Rapat' => 'R',
    ];

    /** @param array<string, mixed> $laporan */
    public function download(array $laporan): StreamedResponse
    {
        $filename = 'rekap-kehadiran-guru-'.Str::slug($laporan['periode']).'.xlsx';

        return response()->streamDownload(function () use ($laporan): void {
            $path = tempnam(sys_get_temp_dir(), 'rekap-kehadiran-guru-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor rekap kehadiran guru.');
            }

            try {
                $this->write($path, $laporan);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param array<string, mixed> $laporan */
    public function write(string $path, array $laporan): void
    {
        /** @var list<array{tanggal: string, nomor: int, hari: string, label: string}> $columns */
        $columns = $laporan['columns'];
        $lastColumn = count($columns) + 3;

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Kehadiran');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(32, 2);

            for ($column = 3; $column <= 2 + count($columns); $column++) {
                $sheet->setColumnWidth(14, $column);
            }

            $sheet->setColumnWidth(11, $lastColumn + 1);

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

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber, $lastColumn): void {
                $writer->addRow($this->row($values, $styles, $height, $lastColumn));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, $lastColumn]]);
            $add(['REKAP KEHADIRAN GURU'], $title, 24, [[0, $lastColumn]]);
            $add(['Periode: '.$laporan['periode']], $subtitle, 20, [[0, $lastColumn]]);
            $add([
                'Hari tercatat: '.$laporan['hariTercatat']
                .' · Guru hadir: '.$laporan['guruHadir'].' dari '.$laporan['guruTotal'],
            ], $left, 18, [[0, $lastColumn]]);
            $add([], $base, 8);

            $headers = ['No', 'Nama Guru'];
            $headerStyles = [$header, $headerLeft];

            foreach ($columns as $column) {
                $headers[] = $column['hari'].', '.$column['label'];
                $headerStyles[] = $headerWrap;
            }

            $headers[] = 'Hari Hadir';
            $headerStyles[] = $headerWrap;

            $add($headers, $headerStyles, 38);

            foreach ($laporan['rows'] as $index => $row) {
                $values = [$index + 1, (string) $row['nama']];
                $styles = [$cell, $cellLeft];

                foreach ($columns as $column) {
                    $sumber = $row['cells'][$column['tanggal']] ?? [];
                    $values[] = implode('/', array_map(
                        fn (string $label): string => self::KODE_SUMBER[$label] ?? $label,
                        $sumber,
                    ));
                    $styles[] = $cell;
                }

                $values[] = (int) $row['hadir'];
                $styles[] = $count;

                $add($values, $styles, 20);
            }

            $totals = ['Total', ''];
            $totalStyles = [$header, $header];

            foreach ($columns as $column) {
                $totals[] = '';
                $totalStyles[] = $header;
            }

            $totals[] = (int) $laporan['totals']['hadir'];
            $totalStyles[] = $header;

            $add($totals, $totalStyles, 22, [[0, 1]]);
            $add([], $base, 8);
            $add([
                'Kode sumber kehadiran: M = Mengajar (sesi dengan presensi siswa), A = Absensi guru harian, '
                .'P = Piket, N = Peminatan, R = Rapat. Satu tanggal bisa memiliki lebih dari satu sumber '
                .'dan tetap dihitung satu hari hadir.',
            ], $leftWrap, 40, [[0, $lastColumn]]);
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
