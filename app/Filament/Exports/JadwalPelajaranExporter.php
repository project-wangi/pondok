<?php

namespace App\Filament\Exports;

use App\Models\JadwalPelajaran;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class JadwalPelajaranExporter extends Exporter
{
    protected static ?string $model = JadwalPelajaran::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('kelas.nama_kelas'),
            ExportColumn::make('hari'),
            ExportColumn::make('guru.user.name'),
            ExportColumn::make('mata_pelajaran'),
            ExportColumn::make('jam_mulai'),
            ExportColumn::make('jam_selesai'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your jadwal pelajaran export has completed and ' . Str::of('row')->counted($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Str::of('row')->counted($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
