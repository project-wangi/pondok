<?php

namespace App\Filament\Resources\Donasis;

use App\Filament\Resources\Donasis\Pages\CreateDonasi;
use App\Filament\Resources\Donasis\Pages\EditDonasi;
use App\Filament\Resources\Donasis\Pages\ListDonasis;
use App\Filament\Resources\Donasis\Schemas\DonasiForm;
use App\Filament\Resources\Donasis\Tables\DonasisTable;
use App\Models\Donasi;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DonasiResource extends Resource
{
    protected static ?string $model = Donasi::class;

    protected static ?string $modelLabel = 'Donasi';

    protected static ?string $pluralModelLabel = 'Donasi';

    protected static ?string $slug = 'donasi';

    protected static ?string $navigationLabel = 'Donasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user === null || $user->can('ViewAny:Donasi')) {
            return $query;
        }

        return $query->where('guru_id', $user->guru()->value('id') ?? 0);
    }

    public static function canCreate(): bool
    {
        $user = Filament::auth()->user();

        if ($user?->can('Create:Donasi')) {
            return true;
        }

       return $user?->guru()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return DonasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DonasisTable::configure($table);
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
            'index' => ListDonasis::route('/'),
            'create' => CreateDonasi::route('/create'),
            'edit' => EditDonasi::route('/{record}/edit'),
        ];
    }
}
