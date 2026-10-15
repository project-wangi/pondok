<?php

namespace App\Filament\Resources\JadwalPeminatans;

use App\Filament\Resources\JadwalPeminatans\Pages\CreateJadwalPeminatan;
use App\Filament\Resources\JadwalPeminatans\Pages\EditJadwalPeminatan;
use App\Filament\Resources\JadwalPeminatans\Pages\ListJadwalPeminatans;
use App\Filament\Resources\JadwalPeminatans\Schemas\JadwalPeminatanForm;
use App\Filament\Resources\JadwalPeminatans\Tables\JadwalPeminatansTable;
use App\Models\JadwalPeminatan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JadwalPeminatanResource extends Resource
{
    protected static ?string $model = JadwalPeminatan::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Jadwal';

    protected static ?string $navigationLabel = 'Jadwal Peminatan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return JadwalPeminatanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JadwalPeminatansTable::configure($table);
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
            'index' => ListJadwalPeminatans::route('/'),
            'create' => CreateJadwalPeminatan::route('/create'),
            'edit' => EditJadwalPeminatan::route('/{record}/edit'),
        ];
    }
}
