<?php

namespace App\Filament\Resources\JadwalRapats;

use App\Filament\Resources\JadwalRapats\Pages\CreateJadwalRapat;
use App\Filament\Resources\JadwalRapats\Pages\EditJadwalRapat;
use App\Filament\Resources\JadwalRapats\Pages\ListJadwalRapats;
use App\Filament\Resources\JadwalRapats\Schemas\JadwalRapatForm;
use App\Filament\Resources\JadwalRapats\Tables\JadwalRapatsTable;
use App\Models\JadwalRapat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JadwalRapatResource extends Resource
{
    protected static ?string $model = JadwalRapat::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Jadwal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return JadwalRapatForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JadwalRapatsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwalRapats::route('/'),
            'create' => CreateJadwalRapat::route('/create'),
            'edit' => EditJadwalRapat::route('/{record}/edit'),
        ];
    }
}
