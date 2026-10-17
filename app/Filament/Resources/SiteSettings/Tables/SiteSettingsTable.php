<?php

namespace App\Filament\Resources\SiteSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('school_name')->label('Nama Sekolah'),
            TextColumn::make('tagline')->label('Slogan')->limit(60),
            TextColumn::make('updated_at')->label('Terakhir Diperbarui')->dateTime('d M Y H:i'),
        ])->recordActions([
            EditAction::make()->label('Ubah'),
        ])->toolbarActions([])->emptyStateHeading('Pengaturan website belum disimpan')
            ->emptyStateDescription('Buat pengaturan untuk menyesuaikan isi website publik.');
    }
}
