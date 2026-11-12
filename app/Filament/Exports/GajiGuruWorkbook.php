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

class GajiGuruWorkbook
{
    private const LAST_COLUMN = 10;

    /** @var list<string> */
    public const HEADERS = [
        'No',
        'Nama Guru',
        'JP',
        'Hari Hadir',
        'Mengajar',
        'Transport',
        'Jabatan',
        'Rapat',
        'Peminatan',
        'Piket',
        'Total',
    ];

    /**
     * @param  array{periode: string, rows: list<array<string, mixed>>, totals: array<string, int>}  $rekap
     */
    public function download(array $rekap): StreamedResponse
    {
        $filename = 'gaji-guru-'.Str::slug($rekap['periode']).'.xlsx';

        return response()->streamDownload(function () use ($rekap): void {
            $path = tempnam(sys_get_temp_dir(), 'gaji-guru-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor gaji guru.');
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
     * @param  array{periode: string, rows: list<array<string, mixed>>, totals: array<string, int>}  $rekap
     */
    public function write(string $path, array $rekap): void
    {
        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Gaji Guru');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(32, 2);
            $sheet->setColumnWidth(8, 3);
            $sheet->setColumnWidth(10, 4);
            $sheet->setColumnWidthForRange(14, 5, 11);

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
            $cell = (clone $base)->setBorder($border);
            $cellLeft = (clone $left)->setBorder($border);
            $quantity = (clone $cell)->setFormat('0.##;-0.##;0');
            $count = (clone $cell)->setFormat('0');
            $money = (clone $cell)->setFormat('"Rp"#,##0');
            $totalLabel = (clone $bold)->setBorder($border);
            $totalQuantity = (clone $bold)->setBorder($border)->setFormat('0.##;-0.##;0');
            $totalMoney = (clone $bold)->setBorder($border)->setFormat('"Rp"#,##0');

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber): void {
                $writer->addRow($this->row($values, $styles, $height));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, self::LAST_COLUMN]]);
            $add(['REKAP GAJI GURU'], $title, 24, [[0, self::LAST_COLUMN]]);
            $add(['Periode: '.$rekap['periode']], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add([], $base, 8);
            $add(self::HEADERS, $header, 24);

            foreach ($rekap['rows'] as $index => $row) {
                $add([
                    $index + 1,
                    (string) $row['nama'],
                    (float) $row['jp'],
                    (int) $row['hari_hadir'],
                    (int) $row['mengajar'],
                    (int) $row['transport'],
                    (int) $row['jabatan'],
                    (int) $row['rapat'],
                    (int) $row['peminatan'],
                    (int) $row['piket'],
                    (int) $row['total'],
                ], [$cell, $cellLeft, $quantity, $count, $money, $money, $money, $money, $money, $money, $money], 20);
            }

            $totals = $rekap['totals'];

            $add([
                'Total',
                '',
                array_sum(array_column($rekap['rows'], 'jp')),
                '',
                $totals['mengajar'],
                $totals['transport'],
                $totals['jabatan'],
                $totals['rapat'],
                $totals['peminatan'],
                $totals['piket'],
                $totals['total'],
            ], [$totalLabel, $totalLabel, $totalQuantity, $totalLabel, $totalMoney, $totalMoney, $totalMoney, $totalMoney, $totalMoney, $totalMoney, $totalMoney], 22, [[0, 1]]);

            $add([], $base, 8);
            $add([
                'JP mengajar dihitung dari sesi yang sudah memiliki presensi siswa (1 JP = 40 menit). '
                .'Transport dihitung dari hari hadir: tanggal unik mengajar yang sudah berpresensi siswa, '
                .'piket, peminatan, atau rapat.',
            ], $leftWrap, 32, [[0, self::LAST_COLUMN]]);
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  list<mixed>  $values
     * @param  Style|array<int, Style>  $styles
     */
    private function row(array $values, Style|array $styles, float $height): Row
    {
        $values = array_pad(array_values($values), self::LAST_COLUMN + 1, '');
        $cells = [];

        foreach (array_slice($values, 0, self::LAST_COLUMN + 1) as $index => $value) {
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
