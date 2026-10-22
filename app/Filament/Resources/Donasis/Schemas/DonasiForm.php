<?php

namespace App\Filament\Resources\Donasis\Schemas;

use App\Models\Guru;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DonasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guru_id')
                    ->label('Guru')
                    ->relationship('guru', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Guru $record) => $record->nama ?? $record->id)
                    ->default(fn (): ?int => Filament::auth()->user()?->guru()->value('id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (): bool => ! self::canManageAll())
                    ->dehydrated(fn (): bool => self::canManageAll()),
                DatePicker::make('tanggal')
                    ->label('Tanggal Donasi')
                    ->default(fn (): string => now()->toDateString())
                    ->required(),
                TextInput::make('jumlah_anak')
                    ->label('Jumlah Anak')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(20)
                    ->required()
                    ->helperText('Nominal donasi dihitung otomatis: jumlah anak × Rp 50.000.'),
            ]);
    }

    private static function canManageAll(): bool
    {
        return Filament::auth()->user()?->can('Create:Donasi') ?? false;
    }
}
