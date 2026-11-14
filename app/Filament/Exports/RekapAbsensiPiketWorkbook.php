<?php

namespace App\Filament\Exports;

use App\Models\Kelas;
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

class RekapAbsensiPiketWorkbook
{
    /**
     * @param  array<string, mixed>  $laporan
     */
    public function download(array $laporan): StreamedResponse
    {
        return response()->streamDownload(function () use ($laporan): void {
            $path = tempnam(sys_get_temp_dir(), 'rekap-piket-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara ekspor rekap absensi piket.');
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
        $class = $laporan['class'] ?? null;
        $nama = ($class instanceof Kelas ? $class->nama_kelas : 'kelas')
            .' '.($laporan['bulanLabel'] ?? '');

        return 'rekap-absensi-piket-'.Str::slug($nama).'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $laporan
     */
    public function write(string $path, array $laporan): void
    {
        /** @var list<array{id: int, nomor: int, label: string, tanggal: mixed}> $columns */
        $columns = $laporan['columns'];
        $sessionCount = count($columns);
        $lastColumn = $sessionCount + 10;

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Piket');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(14, 2);
            $sheet->setColumnWidth(30, 3);
            $sheet->setColumnWidth(6, 4);

            for ($column = 5; $column <= 4 + $sessionCount; $column++) {
                $sheet->setColumnWidth(18, $column);
            }

            for ($column = 5 + $sessionCount; $column <= 9 + $sessionCount; $column++) {
                $sheet->setColumnWidth(6, $column);
            }

            $sheet->setColumnWidth(8, 10 + $sessionCount);
            $sheet->setColumnWidth(12, 11 + $sessionCount);

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

            $class = $laporan['class'];

            $add([(string) SiteSetting::current()->school_name], $title, 26, [[0, $lastColumn]]);
            $add(['REKAP ABSENSI PIKET'], $title, 24, [[0, $lastColumn]]);
            $add(['Kelas: '.($class?->nama_kelas ?? '-')], $subtitle, 20, [[0, $lastColumn]]);
            $add(['Bulan: '.($laporan['bulanLabel'] ?? '-')], $left, 18, [[0, $lastColumn]]);
            $add([], $base, 8);

            $headers = ['No', 'NIS', 'Nama Murid', 'LP'];
            $headerStyles = [$header, $header, $headerLeft, $header];

            foreach ($columns as $column) {
                $headers[] = $column['label'];
                $headerStyles[] = $headerWrap;
            }

            foreach (['H', 'T', 'I', 'S', 'A', 'Pulang', '% Kehadiran'] as $label) {
                $headers[] = $label;
                $headerStyles[] = $header;
            }

            $add($headers, $headerStyles, 40);

            foreach ($laporan['rows'] as $index => $row) {
                $values = [
                    $index + 1,
                    (string) ($row['student']->nis ?? ''),
                    (string) $row['student']->nama,
                    $row['student']->jenisKelaminKode(),
                ];
                $styles = [$cell, $cellLeft, $cellLeft, $cell];

                foreach ($columns as $column) {
                    $cellCode = $row['codes'][$column['id']] ?? ['masuk' => null, 'pulang' => null];
                    $masuk = $cellCode['masuk'] ?? null;
                    $pulang = $cellCode['pulang'] ?? null;
                    $label = $masuk !== null
                        ? (string) $masuk.($pulang !== null ? '/'.$pulang : '')
                        : (string) ($pulang ?? '');

                    $values[] = $label;
                    $styles[] = $cell;
                }

                foreach (['H', 'T', 'I', 'S', 'A'] as $code) {
                    $values[] = (int) $row['masuk_counts'][$code];
                    $styles[] = $count;
                }

                $values[] = (int) $row['pulang_count'];
                $styles[] = $count;

                $values[] = $row['percent'] ?? '';
                $styles[] = $percent;

                $add($values, $styles, 20);
            }

            $add([], $base, 8);
            $add([
                'Kode absen masuk: H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa. '
                .'Kode absen pulang: P = Pulang, I = Izin, S = Sakit, A = Alpa. '
                .'Sel pertemuan ditulis masuk/pulang, misalnya H/P.',
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
