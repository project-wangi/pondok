<?php

namespace App\Filament\Exports;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
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

class JadwalPelajaranWorkbook
{
    public function download(Builder $query, string $semester, string $tahunAjaran, ?int $kelasId = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $semester, $tahunAjaran, $kelasId): void {
            $path = tempnam(sys_get_temp_dir(), 'jadwal-');

            if ($path === false) {
                throw new \RuntimeException('Tidak dapat membuat file sementara export jadwal.');
            }

            try {
                $this->write($path, $query, $semester, $tahunAjaran, $kelasId);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, 'jadwal-pelajaran-'.strtolower($semester).'-'.str_replace('/', '-', $tahunAjaran).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function write(string $path, Builder $query, string $semester, string $tahunAjaran, ?int $kelasId = null): void
    {
        $schedules = (clone $query)
            ->when($kelasId !== null, fn (Builder $query): Builder => $query->where('kelas_id', $kelasId))
            ->with(['kelas', 'guru.user', 'mataPelajaran'])
            ->reorder()->orderBy('jam_mulai')->orderBy('jam_selesai')->orderBy('id')->get();
        $groups = $schedules->groupBy('kelas_id');

        if ($groups->isEmpty()) {
            $groups->put($kelasId ?? 0, collect());
        }

        $settings = SiteSetting::current();
        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));
        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            foreach ($groups as $classId => $records) {
                $sheet = $writer->getCurrentSheet();
                $className = $records->first()?->kelas?->nama_kelas
                    ?? ($kelasId !== null ? Kelas::query()->find($kelasId)?->nama_kelas : null)
                    ?? 'Tidak ada jadwal';
                // OpenSpout does not quote sheet names in print-title formulas.
                $sheet->setName('Kelas_'.$classId);
                $days = ['Sabtu' => 'SABTU', 'Minggu' => 'AHAD', 'Senin' => 'SENIN', 'Selasa' => 'SELASA', 'Rabu' => 'RABU', 'Kamis' => 'KAMIS'];

                // Keep any additional stored day (including Friday) rather than dropping lessons.
                foreach ($records->pluck('hari')->unique() as $day) {
                    if (! array_key_exists($day, $days)) {
                        $days[$day] = mb_strtoupper($day);
                    }
                }

                $lastColumn = count($days);
                $sheet->setColumnWidth(20, 1);
                $sheet->setColumnWidthForRange(24, 2, $lastColumn + 1);
                $sheet->setPrintTitleRows('$1:$8');
                $base = (new Style)->setFontName('Arial')->setFontSize(11)
                    ->setCellAlignment(CellAlignment::CENTER)
                    ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setShouldWrapText();
                $bold = (clone $base)->setFontBold();
                $border = new Border(...array_map(
                    fn (string $side): BorderPart => new BorderPart($side, width: Border::WIDTH_THIN),
                    [Border::TOP, Border::BOTTOM, Border::LEFT, Border::RIGHT],
                ));
                $tableStyle = (clone $base)->setBorder($border);
                $headerStyle = (clone $tableStyle)->setFontBold();
                $headings = [
                    [(string) $settings->school_name, 28, (clone $bold)->setFontSize(16)],
                    [(string) $settings->tagline, 24, $base],
                    [(string) $settings->address, 28, $base],
                    ['', 10, $base],
                    ['JADWAL PELAJARAN', 26, (clone $bold)->setFontSize(14)],
                    ['SEMESTER '.mb_strtoupper($semester).' '.$tahunAjaran, 24, $bold],
                    ['Kelas: '.$className, 24, (clone $bold)->setCellAlignment(CellAlignment::LEFT)],
                ];

                foreach ($headings as $index => [$text, $height, $style]) {
                    $startColumn = $index < 3 ? 1 : 0;
                    $options->mergeCells($startColumn, $index + 1, $lastColumn, $index + 1, $sheet->getIndex());
                    $values = array_fill(0, $lastColumn + 1, '');
                    $values[$startColumn] = $text;
                    $writer->addRow($this->row($values, $style, $height));
                }

                $writer->addRow($this->row(['JAM', ...array_values($days)], $headerStyle, 28));
                $slots = $records->groupBy(fn (JadwalPelajaran $schedule): string => ($schedule->jam_mulai ?? '').'|'.($schedule->jam_selesai ?? ''));

                foreach ($slots as $lessons) {
                    $first = $lessons->first();
                    $values = [$this->time($first->jam_mulai).' - '.$this->time($first->jam_selesai)];

                    foreach ($days as $day => $label) {
                        $values[] = $lessons->where('hari', $day)->map(fn (JadwalPelajaran $lesson): string => ($lesson->mataPelajaran?->nama_mapel ?? 'Mapel tidak tersedia')."\n".($lesson->guru?->user?->name ?? 'Guru tidak tersedia')
                        )->implode("\n\n");
                    }

                    $wrapped = array_map($this->wrappedLines(...), $values);
                    $rowCount = (int) ceil(max(array_map('count', $wrapped)) / 20);

                    for ($part = 0; $part < $rowCount; $part++) {
                        $rowValues = array_map(fn (array $lines): string => implode("\n", array_slice($lines, $part * 20, 20)), $wrapped);
                        $rowValues[0] = $values[0];
                        $lines = max(array_map(fn (string $value): int => substr_count($value, "\n") + 1, $rowValues));
                        $writer->addRow($this->row($rowValues, $tableStyle, max(60, $lines * 18)));
                    }
                }

                if ($slots->isEmpty()) {
                    $writer->addRow($this->row(array_fill(0, $lastColumn + 1, ''), $tableStyle, 60));
                    $options->mergeCells(0, 10, $lastColumn, 10, $sheet->getIndex());
                    $writer->addRow($this->row(['Tidak ada jadwal yang sesuai dengan pilihan atau pencarian.'], $base, 24));
                }

                if ($classId !== $groups->keys()->last()) {
                    $writer->addNewSheetAndMakeItCurrent();
                }
            }
        } finally {
            $writer->close();
        }

        if ($settings->logo && Storage::disk('public')->exists($settings->logo)) {
            app(JadwalPelajaranLogo::class)->embed($path, Storage::disk('public')->path($settings->logo));
        }
    }

    /** @param list<string> $values */
    private function row(array $values, Style $style, float $height): Row
    {
        // Explicit strings prevent user-entered names from being interpreted as formulas.
        return (new Row(array_map(fn (string $value): StringCell => new StringCell($value, null), $values), $style))->setHeight($height);
    }

    /** @return list<string> */
    private function wrappedLines(string $value): array
    {
        $lines = [];

        foreach (explode("\n", $value) as $line) {
            while (mb_strwidth($line) > 22) {
                $part = mb_strimwidth($line, 0, 22);
                $lines[] = $part;
                $line = mb_substr($line, mb_strlen($part));
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function time(?string $time): string
    {
        return $time === null ? '—' : str_replace(':', '.', substr($time, 0, 5));
    }
}
