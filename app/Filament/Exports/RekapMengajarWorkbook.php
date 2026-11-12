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

class RekapMengajarWorkbook
{
    /**
     * @param  array{
     *     periode: string,
     *     columns: list<array{id: int, nama: string}>,
     *     rows: list<array{guru_id: int, nama: string, cells: array<int, array{scheduled: bool, jp: float}>, total: float}>,
     *     totals: array{kelas: array<int, float>, total: float}
     * }  $rekap
     */
    public function download(array $rekap): StreamedResponse
    {
        $filename = 'rekap-mengajar-'.Str::slug($rekap['periode']).'.xlsx';

        return response()->streamDownload(function () use ($rekap): void {
            $path = tempnam(sys_get_temp_dir(), 'rekap-mengajar-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor rekap mengajar.');
            }

            try {
                $this->write($path, $rekap);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{
     *     periode: string,
     *     columns: list<array{id: int, nama: string}>,
     *     rows: list<array{guru_id: int, nama: string, cells: array<int, array{scheduled: bool, jp: float}>, total: float}>,
     *     totals: array{kelas: array<int, float>, total: float}
     * }  $rekap
     */
    public function write(string $path, array $rekap): void
    {
        $lastColumn = count($rekap['columns']) + 2;

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Mengajar');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(32, 2);

            for ($column = 3; $column <= $lastColumn + 1; $column++) {
                $sheet->setColumnWidth($column === $lastColumn + 1 ? 10 : 12, $column);
            }

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
            $quantity = (clone $cell)->setFormat('0.##;-0.##;0');
            $totalLabel = (clone $bold)->setBorder($border);
            $totalQuantity = (clone $bold)->setBorder($border)->setFormat('0.##;-0.##;0');

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber, $lastColumn): void {
                $writer->addRow($this->row($values, $styles, $height, $lastColumn));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, $lastColumn]]);
            $add(['REKAP MENGAJAR GURU'], $title, 24, [[0, $lastColumn]]);
            $add(['Periode: '.$rekap['periode']], $subtitle, 20, [[0, $lastColumn]]);
            $add([], $base, 8);

            $headers = ['No', 'Nama Guru'];

            foreach ($rekap['columns'] as $column) {
                $headers[] = (string) $column['nama'];
            }

            $headers[] = 'Total';

            $add($headers, [$header, $headerLeft, ...array_fill(0, count($headers) - 1, $headerWrap)], 30);

            foreach ($rekap['rows'] as $index => $row) {
                $values = [$index + 1, (string) $row['nama']];
                $styles = [$cell, $cellLeft];

                foreach ($rekap['columns'] as $column) {
                    $jp = $row['cells'][$column['id']] ?? ['scheduled' => false, 'jp' => 0.0];

                    $values[] = $jp['scheduled'] ? (float) $jp['jp'] : '';
                    $styles[] = $quantity;
                }

                $values[] = (float) $row['total'];
                $styles[] = $quantity;

                $add($values, $styles, 20);
            }

            $totals = ['Total', ''];
            $totalStyles = [$totalLabel, $totalLabel];

            foreach ($rekap['columns'] as $column) {
                $totals[] = (float) ($rekap['totals']['kelas'][$column['id']] ?? 0);
                $totalStyles[] = $totalQuantity;
            }

            $totals[] = (float) $rekap['totals']['total'];
            $totalStyles[] = $totalQuantity;

            $add($totals, $totalStyles, 22, [[0, 1]]);
            $add([], $base, 8);
            $add([
                'Angka dalam JP (1 JP = 40 menit) dari sesi yang sudah memiliki presensi siswa. '
                .'Sel kosong berarti guru tidak memiliki jadwal pada kelas tersebut di semester ini.',
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
