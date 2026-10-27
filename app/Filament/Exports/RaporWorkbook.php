<?php

namespace App\Filament\Exports;

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

class RaporWorkbook
{
    private const LAST_COLUMN = 4;

    /**
     * @param  array<string, mixed>  $report
     */
    public function download(array $report): StreamedResponse
    {
        $filename = $this->filename($report);

        return response()->streamDownload(function () use ($report): void {
            $path = tempnam(sys_get_temp_dir(), 'rapor-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat file sementara export rapor.');
            }

            try {
                $this->write($path, $report);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function filename(array $report): string
    {
        $nis = trim((string) ($report['student']['nis'] ?? ''));
        $nis = preg_replace('/[^A-Za-z0-9]+/', '-', $nis) ?: 'siswa';

        return 'rapor-'.$nis.'-'.strtolower((string) $report['semester']).'-'.str_replace('/', '-', (string) $report['tahun_ajaran']).'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function write(string $path, array $report): void
    {
        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rapor');
            $sheet->setColumnWidth(5, 1);
            $sheet->setColumnWidth(34, 2);
            $sheet->setColumnWidthForRange(11, 3, 5);

            $base = (new Style)
                ->setFontName('Arial')
                ->setFontSize(10)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $left = (clone $base)->setCellAlignment(CellAlignment::LEFT);
            $leftWrap = (clone $left)->setShouldWrapText();
            $bold = (clone $base)->setFontBold();
            $title = (clone $base)->setFontBold()->setFontSize(14);
            $subtitle = (clone $base)->setFontBold()->setFontSize(11);

            $border = new Border(...array_map(
                fn (string $side): BorderPart => new BorderPart($side, width: Border::WIDTH_THIN),
                [Border::TOP, Border::BOTTOM, Border::LEFT, Border::RIGHT],
            ));
            $table = (clone $base)->setBorder($border);
            $tableLeft = (clone $left)->setBorder($border);
            $tableBold = (clone $bold)->setBorder($border);

            $rowNumber = 0;
            $add = function (array $values, Style|array $styles, float $height, array $merges = []) use ($writer, $options, $sheet, &$rowNumber): void {
                $writer->addRow($this->row($values, $styles, $height));
                $rowNumber++;

                foreach ($merges as [$start, $end]) {
                    $options->mergeCells($start, $rowNumber, $end, $rowNumber, $sheet->getIndex());
                }
            };

            $add([(string) $report['school_name']], $title, 28, [[0, self::LAST_COLUMN]]);
            $add([(string) ($report['school_address'] ?? '')], $base, 18, [[0, self::LAST_COLUMN]]);
            $add([], $base, 8);
            $add(['LAPORAN HASIL BELAJAR'], $title, 26, [[0, self::LAST_COLUMN]]);
            $add(['UJIAN AKHIR SEMESTER '.mb_strtoupper((string) $report['semester']).' '.$report['tahun_ajaran']], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add([], $base, 8);

            $identity = [
                ['Nama', (string) $report['student']['nama'], '', 'NISM', (string) ($report['student']['nis'] ?? '—')],
                ['Kelas', (string) $report['class'], '', 'NISN', (string) ($report['student']['nisn'] ?? '—')],
                ['Tahun Pelajaran', (string) $report['tahun_ajaran'], '', 'No. Absen', (string) $report['student']['no_absen']],
            ];

            foreach ($identity as [$leftLabel, $leftValue, $blank, $rightLabel, $rightValue]) {
                $add([$leftLabel, $leftValue, $blank, $rightLabel, $rightValue], [$bold, $left, $left, $bold, $left], 18, [[1, 2]]);
            }

            $add([], $base, 8);
            $add(['No', 'Mata Pelajaran', 'KKM', 'Angka', 'Huruf'], $tableBold, 22);

            foreach ($report['subjects'] as $index => $subject) {
                $add([
                    $index + 1,
                    (string) $subject['nama'],
                    $subject['kkm'],
                    $subject['angka'] ?? '',
                    $subject['huruf'] ?? '',
                ], [$table, $tableLeft, $table, $table, $table], 20);
            }

            $peringkat = $report['peringkat'] === null
                ? 'Peringkat Kelas: —'
                : 'Peringkat Kelas: '.$report['peringkat'].' dari '.$report['jumlah_siswa'];

            $add(['Jumlah Nilai', '', $report['jumlah'] ?? '—', $peringkat, ''], [$tableBold, $table, $tableBold, $tableLeft, $table], 20, [[0, 1], [3, 4]]);
            $add(['Rata-rata', '', $report['rata_rata'] ?? '—', 'Peminatan: '.($report['peminatan'] ?: '—'), ''], [$tableBold, $table, $tableBold, $tableLeft, $table], 20, [[0, 1], [3, 4]]);

            $add([], $base, 8);
            $add(['KEPRIBADIAN'], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add(['Aspek', '', 'Angka', 'Huruf', ''], [$tableBold, $table, $tableBold, $tableBold, $table], 20, [[0, 1], [3, 4]]);

            foreach ($report['kepribadian'] as $aspect) {
                $add([
                    (string) $aspect['label'],
                    '',
                    $aspect['angka'] ?? '—',
                    $aspect['huruf'] ?? '',
                    '',
                ], [$tableLeft, $table, $table, $table, $table], 20, [[0, 1], [3, 4]]);
            }

            $add([], $base, 8);
            $add(['KETIDAKHADIRAN'], $subtitle, 20, [[0, self::LAST_COLUMN]]);
            $add(['Keterangan', '', 'Jumlah', '', ''], [$tableBold, $table, $tableBold, $table, $table], 20, [[0, 1], [2, 4]]);

            foreach (['Sakit' => $report['ketidakhadiran']['sakit'], 'Izin' => $report['ketidakhadiran']['izin'], 'Alpa' => $report['ketidakhadiran']['alpa']] as $label => $total) {
                $add([$label, '', $total, '', ''], [$tableLeft, $table, $table, $table, $table], 20, [[0, 1], [2, 4]]);
            }

            $add([], $base, 8);
            $add([(string) $report['catatan']], $leftWrap, 46, [[0, self::LAST_COLUMN]]);
            $add([], $base, 12);
            $add(['Kepala Madrasah', '', 'Wali Kelas', '', 'Orang Tua/Wali'], $base, 20);
            $add([], $base, 12);
            $add([], $base, 12);
            $add([], $base, 12);
            $add([
                (string) ($report['kepala_madrasah'] ?? '—'),
                '',
                (string) ($report['wali_kelas'] ?? '—'),
                '',
                (string) ($report['orang_tua'] ?? '') ?: '................',
            ], $base, 20);
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
