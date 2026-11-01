<?php

namespace App\Filament\Resources\CalonSiswas;

use App\Filament\Resources\CalonSiswas\Pages\EditCalonSiswa;
use App\Filament\Resources\CalonSiswas\Pages\ListCalonSiswas;
use App\Filament\Resources\CalonSiswas\Schemas\CalonSiswaForm;
use App\Filament\Resources\CalonSiswas\Tables\CalonSiswasTable;
use App\Models\CalonSiswa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CalonSiswaResource extends Resource
{
    protected static ?string $model = CalonSiswa::class;

    protected static ?string $modelLabel = 'Calon Siswa';

    protected static ?string $pluralModelLabel = 'Calon Siswa';

    protected static ?string $navigationLabel = 'Calon Siswa';

    protected static string|UnitEnum|null $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'calon-siswa';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CalonSiswa::query()
            ->where('status', CalonSiswa::STATUS_MENUNGGU)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return CalonSiswaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalonSiswasTable::configure($table);
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
            'index' => ListCalonSiswas::route('/'),
            'edit' => EditCalonSiswa::route('/{record}/edit'),
        ];
    }
}
