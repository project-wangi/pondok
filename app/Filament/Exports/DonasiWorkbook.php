<?php

namespace App\Filament\Exports;

use App\Models\Donasi;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Collection;
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

class DonasiWorkbook
{
    private const LAST_COLUMN = 4;

    /** @var list<string> */
    public const HEADERS = [
        'No',
        'Tanggal',
        'Guru',
        'Jumlah Anak',
        'Nominal',
    ];

    /** @param Collection<int, Donasi> $donations */
    public function download(Collection $donations): StreamedResponse
    {
        $filename = 'data-donasi-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($donations): void {
            $path = tempnam(sys_get_temp_dir(), 'donasi-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor data donasi.');
            }

            try {
                $this->write($path, $donations);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param Collection<int, Donasi> $donations */
    public function write(string $path, Collection $donations): void
    {
        $donations->loadMissing('guru.user');

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Donasi');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(16, 2);
            $sheet->setColumnWidth(34, 3);
            $sheet->setColumnWidth(12, 4);
            $sheet->setColumnWidth(16, 5);

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
            $headerLeft = (clone $header)->setCellAlignment(CellAlignment::LEFT);
            $cell = (clone $base)->setBorder($border);
            $cellLeft = (clone $left)->setBorder($border);
            $count = (clone $cell)->setFormat('0');
            $money = (clone $cell)->setFormat('"Rp"#,##0');
            $totalLabel = (clone $bold)->setBorder($border);
            $totalCount = (clone $bold)->setBorder($border)->setFormat('0');
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
            $add(['DATA DONASI GURU'], $title, 24, [[0, self::LAST_COLUMN]]);
            $add(['Dicetak: '.now()->locale('id')->translatedFormat('d F Y H:i')], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add([], $base, 8);
            $add(self::HEADERS, [$header, $header, $headerLeft, $header, $header], 24);

            foreach ($donations as $index => $donasi) {
                $add([
                    $index + 1,
                    $donasi->tanggal?->format('d/m/Y') ?? '',
                    (string) ($donasi->guru?->user?->name ?? '-'),
                    (int) $donasi->jumlah_anak,
                    (int) $donasi->nominal,
                ], [$cell, $cell, $cellLeft, $count, $money], 20);
            }

            $add([
                'Total',
                '',
                '',
                (int) $donations->sum('jumlah_anak'),
                (int) $donations->sum(fn (Donasi $donasi): int => $donasi->nominal),
            ], [$totalLabel, $totalLabel, $totalLabel, $totalCount, $totalMoney], 22, [[0, 2]]);

            $add([], $base, 8);
            $add([
                'Nominal dihitung otomatis: jumlah anak × Rp50.000. Data mengikuti pencarian dan filter yang aktif saat ekspor.',
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
