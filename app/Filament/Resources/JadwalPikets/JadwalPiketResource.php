<?php

namespace App\Filament\Resources\JadwalPikets;

use App\Filament\Resources\JadwalPikets\Pages\CreateJadwalPiket;
use App\Filament\Resources\JadwalPikets\Pages\EditJadwalPiket;
use App\Filament\Resources\JadwalPikets\Pages\ListJadwalPikets;
use App\Filament\Resources\JadwalPikets\Schemas\JadwalPiketForm;
use App\Filament\Resources\JadwalPikets\Tables\JadwalPiketsTable;
use App\Models\JadwalPiket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JadwalPiketResource extends Resource
{
    protected static ?string $model = JadwalPiket::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Jadwal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return JadwalPiketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JadwalPiketsTable::configure($table);
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
            'index' => ListJadwalPikets::route('/'),
            'create' => CreateJadwalPiket::route('/create'),
            'edit' => EditJadwalPiket::route('/{record}/edit'),
        ];
    }
}
