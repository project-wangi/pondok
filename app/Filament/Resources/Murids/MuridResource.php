<?php

namespace App\Filament\Resources\Murids;

use App\Filament\Resources\Murids\Pages\CreateMurid;
use App\Filament\Resources\Murids\Pages\EditMurid;
use App\Filament\Resources\Murids\Pages\ListMurids;
use App\Filament\Resources\Murids\Schemas\MuridForm;
use App\Filament\Resources\Murids\Tables\MuridsTable;
use App\Models\Murid;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MuridResource extends Resource
{
    protected static ?string $model = Murid::class;

    protected static ?string $modelLabel = 'Murid';

    protected static ?string $pluralModelLabel = 'Data Murid';

    protected static ?string $slug = 'murid';

    protected static ?string $navigationLabel = 'Murid';

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return MuridForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MuridsTable::configure($table);
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
            'index' => ListMurids::route('/'),
            'create' => CreateMurid::route('/create'),
            'edit' => EditMurid::route('/{record}/edit'),
        ];
    }
}
