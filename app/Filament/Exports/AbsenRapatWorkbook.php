<?php

namespace App\Filament\Exports;

use App\Models\AbsenRapat;
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

class AbsenRapatWorkbook
{
    private const LAST_COLUMN = 6;

    /** @var list<string> */
    public const HEADERS = [
        'No',
        'Tanggal',
        'Agenda',
        'Guru',
        'Jam Mulai',
        'Jam Selesai',
        'Dicatat',
    ];

    /** @param Collection<int, AbsenRapat> $rapats */
    public function download(Collection $rapats): StreamedResponse
    {
        return response()->streamDownload(function () use ($rapats): void {
            $path = tempnam(sys_get_temp_dir(), 'rekap-rapat-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor rekap absensi rapat.');
            }

            try {
                $this->write($path, $rapats);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, 'rekap-absensi-rapat-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param Collection<int, AbsenRapat> $rapats */
    public function write(string $path, Collection $rapats): void
    {
        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Absensi Rapat');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(14, 2);
            $sheet->setColumnWidth(44, 3);
            $sheet->setColumnWidth(30, 4);
            $sheet->setColumnWidth(10, 5);
            $sheet->setColumnWidth(10, 6);
            $sheet->setColumnWidth(18, 7);

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

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber): void {
                $writer->addRow($this->row($values, $styles, $height));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, self::LAST_COLUMN]]);
            $add(['REKAP ABSENSI RAPAT'], $title, 24, [[0, self::LAST_COLUMN]]);
            $add(['Dicetak: '.now()->locale('id')->translatedFormat('d F Y H:i')], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add([], $base, 8);
            $add(self::HEADERS, [$header, $header, $headerLeft, $headerLeft, $header, $header, $header], 24);

            foreach ($rapats as $index => $rapat) {
                $add([
                    $index + 1,
                    $rapat->tanggal?->format('d/m/Y') ?? '',
                    (string) $rapat->agenda,
                    (string) $rapat->nama_guru,
                    substr((string) $rapat->jam_mulai, 0, 5),
                    substr((string) $rapat->jam_selesai, 0, 5),
                    $rapat->dicatat_pada?->format('d/m/Y H:i') ?? '',
                ], [$cell, $cell, $cellLeft, $cellLeft, $cell, $cell, $cell], 20);
            }

            $add([], $base, 8);
            $add(['Data mengikuti pencarian dan filter yang aktif saat ekspor.'], $leftWrap, 24, [[0, self::LAST_COLUMN]]);
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
