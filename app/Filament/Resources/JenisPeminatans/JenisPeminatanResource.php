<?php

namespace App\Filament\Resources\JenisPeminatans;

use App\Filament\Resources\JenisPeminatans\Pages\CreateJenisPeminatan;
use App\Filament\Resources\JenisPeminatans\Pages\EditJenisPeminatan;
use App\Filament\Resources\JenisPeminatans\Pages\ListJenisPeminatans;
use App\Filament\Resources\JenisPeminatans\Schemas\JenisPeminatanForm;
use App\Filament\Resources\JenisPeminatans\Tables\JenisPeminatansTable;
use App\Models\JenisPeminatan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JenisPeminatanResource extends Resource
{
    protected static ?string $model = JenisPeminatan::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return JenisPeminatanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JenisPeminatansTable::configure($table);
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
            'index' => ListJenisPeminatans::route('/'),
            'create' => CreateJenisPeminatan::route('/create'),
            'edit' => EditJenisPeminatan::route('/{record}/edit'),
        ];
    }
}
