<?php

namespace App\Filament\Exports;

use Illuminate\Support\Facades\Storage;
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
use ZipArchive;

class RaporWorkbook
{
    private const LAST_COLUMN = 4;

    /** Ukuran logo pada berkas Excel (piksel). */
    private const LOGO_SIZE_PX = 56;

    /** 1 piksel dalam English Metric Unit (EMU) yang dipakai OOXML. */
    private const EMU_PER_PX = 9525;

    /** Tipe gambar yang bisa disisipkan ke Excel. */
    private const LOGO_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

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

            if (filled($report['school_logo'] ?? null)) {
                // Kop: logo di kolom A, nama dan alamat sekolah di B:E agar
                // tampil di samping logo seperti kop surat.
                $add([], $base, 15);
                $add(['', (string) $report['school_name']], [$base, $title], 26, [[1, self::LAST_COLUMN]]);
                $add(['', (string) ($report['school_address'] ?? '')], [$base, $base], 18, [[1, self::LAST_COLUMN]]);
                $add([], $base, 15);
            } else {
                $add([(string) $report['school_name']], $title, 28, [[0, self::LAST_COLUMN]]);
                $add([(string) ($report['school_address'] ?? '')], $base, 18, [[0, self::LAST_COLUMN]]);
                $add([], $base, 8);
            }

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

        $this->tempelLogo($path, (string) ($report['school_logo'] ?? ''));
    }

    /**
     * Sisipkan logo sekolah ke berkas xlsx yang sudah dibuat OpenSpout.
     *
     * OpenSpout belum mendukung gambar, jadi berkas ditambal langsung pada
     * arsip OOXML: media + drawing + relasi + content type.
     */
    private function tempelLogo(string $path, string $logo): void
    {
        $extension = strtolower(pathinfo($logo, PATHINFO_EXTENSION));

        if (blank($logo) || ! isset(self::LOGO_TYPES[$extension])) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($logo)) {
            return;
        }

        $binary = $disk->get($logo);
        $zip = new ZipArchive;

        if ($binary === null || $zip->open($path) !== true) {
            return;
        }

        $mediaFile = 'school-logo.'.$extension;

        try {
            $zip->addFromString('xl/media/'.$mediaFile, $binary);
            $zip->addFromString('xl/drawings/drawing1.xml', $this->drawingXml());
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->drawingRelsXml($mediaFile));

            $zip->addFromString(
                'xl/worksheets/_rels/sheet1.xml.rels',
                $this->sheetRelsXml((string) $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels')),
            );

            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

            if (! str_contains($sheet, '<drawing')) {
                $drawing = '<drawing xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rIdLogo"/>';

                // Urutan elemen worksheet wajib mengikuti skema OOXML: <drawing>
                // harus berada sebelum <legacyDrawing> (komentar).
                $sheet = str_contains($sheet, '<legacyDrawing')
                    ? str_replace('<legacyDrawing', $drawing.'<legacyDrawing', $sheet)
                    : str_replace('</worksheet>', $drawing.'</worksheet>', $sheet);

                $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
            }

            $zip->addFromString(
                '[Content_Types].xml',
                $this->contentTypesXml((string) $zip->getFromName('[Content_Types].xml'), $extension, self::LOGO_TYPES[$extension]),
            );
        } finally {
            $zip->close();
        }
    }

    private function drawingXml(): string
    {
        $size = self::LOGO_SIZE_PX * self::EMU_PER_PX;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<xdr:oneCellAnchor>'
            .'<xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:ext cx="'.$size.'" cy="'.$size.'"/>'
            .'<xdr:pic>'
            .'<xdr:nvPicPr>'
            .'<xdr:cNvPr id="1" name="Logo Sekolah"/>'
            .'<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr>'
            .'</xdr:nvPicPr>'
            .'<xdr:blipFill>'
            .'<a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rIdLogo"/>'
            .'<a:stretch><a:fillRect/></a:stretch>'
            .'</xdr:blipFill>'
            .'<xdr:spPr>'
            .'<a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$size.'" cy="'.$size.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            .'</xdr:spPr>'
            .'</xdr:pic>'
            .'<xdr:clientData/>'
            .'</xdr:oneCellAnchor>'
            .'</xdr:wsDr>';
    }

    private function drawingRelsXml(string $mediaFile): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$mediaFile.'"/>'
            .'</Relationships>';
    }

    private function sheetRelsXml(string $existing): string
    {
        $relationship = '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>';

        if ($existing === '') {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .$relationship
                .'</Relationships>';
        }

        if (str_contains($existing, 'drawing1.xml')) {
            return $existing;
        }

        return str_replace('</Relationships>', $relationship.'</Relationships>', $existing);
    }

    private function contentTypesXml(string $types, string $extension, string $mime): string
    {
        if ($types === '') {
            return $types;
        }

        if (! str_contains($types, 'Extension="'.$extension.'"')) {
            $types = preg_replace(
                '/(<Types[^>]*>)/',
                '$1<Default Extension="'.$extension.'" ContentType="'.$mime.'"/>',
                $types,
                1,
            ) ?? $types;
        }

        if (! str_contains($types, '/xl/drawings/drawing1.xml')) {
            $types = str_replace(
                '</Types>',
                '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>',
                $types,
            );
        }

        return $types;
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
