<?php

namespace App\Filament\Resources\AbsenRapats;

use App\Filament\Resources\AbsenRapats\Pages\ListAbsenRapats;
use App\Filament\Resources\AbsenRapats\Tables\AbsenRapatsTable;
use App\Models\AbsenRapat;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AbsenRapatResource extends Resource
{
    protected static ?string $model = AbsenRapat::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Absensi';

    protected static ?string $navigationLabel = 'Rekap Absensi Rapat';

    protected static ?string $modelLabel = 'Absensi Rapat';

    protected static ?string $pluralModelLabel = 'Rekap Absensi Rapat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'rekap-absensi-rapat';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->guru()->exists() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AbsenRapatsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbsenRapats::route('/'),
        ];
    }
}
