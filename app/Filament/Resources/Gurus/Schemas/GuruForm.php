<?php

namespace App\Filament\Resources\Gurus\Schemas;

use App\Models\Jabatan;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class GuruForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('foto')
                    ->label('Foto Guru')
                    ->image()
                    ->disk('r2')
                    ->deleteUploadedFileUsing(function ($file) {
                        // Perintah untuk menghapus file fisik dari Cloudflare R2 secara permanen
                        Storage::disk('r2')->delete($file);
                    })
                    ->maxSize(1024 * 10)
                    ->directory('guru-fotos')
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('Nama')
                    ->relationship('user', 'name', function ($query, $livewire) {
                        $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
                            ->when($livewire instanceof EditRecord, function ($q) use ($livewire) {
                                $q->where(function ($subQ) use ($livewire) {
                                    $subQ->whereDoesntHave('guru')
                                        ->orWhere('id', $livewire->record->user_id);
                                });
                            }, function ($q) {
                                $q->whereDoesntHave('guru');
                            });
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! self::canManageAccount())
                    ->dehydrated(fn (): bool => self::canManageAccount()),

                Select::make('agama')
                    ->label('Agama')
                    ->options([
                        'Islam' => 'Islam',
                        'Kristen' => 'Kristen',
                        'Katolik' => 'Katolik',
                        'Hindu' => 'Hindu',
                        'Buddha' => 'Buddha',
                        'Konghucu' => 'Konghucu',
                    ])
                    ->required(),

                TextInput::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->maxLength(255),

                DatePicker::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->required(),

                TextInput::make('jarak_rumah')
                    ->label('Jarak Rumah (KM)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->extraInputAttributes([
                        'oninput' => 'if(this.value > 10) this.value = 10; if(this.value < 0) this.value = 0;',
                    ])
                    ->suffix('KM'),

                Textarea::make('alamat')
                    ->label('Alamat Lengkap')
                    ->columnSpanFull(),

                Select::make('jabatans')
                    ->label('Jabatan')
                    ->relationship('jabatans', 'nama_jabatan')
                    ->multiple()
                    ->live()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (): bool => ! self::canManageAccount())
                    ->dehydrated(fn (): bool => self::canManageAccount()),

                Select::make('kelas_id')
                    ->label('Kelas Wali')
                    ->relationship('kelasWali', 'nama_kelas')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::isWaliKelas($get('jabatans')))
                    ->required(fn (Get $get): bool => self::canManageAccount() && self::isWaliKelas($get('jabatans')))
                    ->unique(ignoreRecord: true)
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn (mixed $state, Get $get): mixed => self::isWaliKelas($get('jabatans')) ? $state : null)
                    ->helperText('Satu kelas hanya boleh memiliki satu wali kelas.')
                    ->disabled(fn (): bool => ! self::canManageAccount())
                    ->dehydrated(fn (): bool => self::canManageAccount()),
                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'laki-laki' => 'laki-laki',
                        'perempuan' => 'perempuan',
                    ])
                    ->required(),
                Section::make('Dokumen')
                    ->schema([
                        FileUpload::make('ijazah')
                            ->label('Ijazah')
                            ->disk('r2')
                            ->deleteUploadedFileUsing(function ($file) {
                                Storage::disk('r2')->delete($file);
                            })
                            ->maxSize(2048)
                            ->helperText('Format: PDF / Gambar, maksimal 2MB')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('guru-dokumen/ijazah'),

                        FileUpload::make('ktp')
                            ->label('Kartu Tanda Penduduk (KTP)')
                            ->disk('r2')
                            ->deleteUploadedFileUsing(function ($file) {
                                Storage::disk('r2')->delete($file);
                            })
                            ->maxSize(2048)
                            ->helperText('Format: PDF / Gambar, maksimal 2MB')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('guru-dokumen/ktp'),

                        FileUpload::make('kk')
                            ->label('Kartu Keluarga (KK)')
                            ->disk('r2')
                            ->deleteUploadedFileUsing(function ($file) {
                                Storage::disk('r2')->delete($file);
                            })
                            ->maxSize(2048)
                            ->helperText('Format: PDF / Gambar, maksimal 2MB')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('guru-dokumen/kk'),
                    ])->columns(3),
            ])
            ->columns(1);
    }

    private static function canManageAccount(): bool
    {
        return Filament::auth()->user()?->can('Update:Guru') ?? false;
    }

    private static function isWaliKelas(mixed $jabatans): bool
    {
        if (! is_array($jabatans) || $jabatans === []) {
            return false;
        }

        $waliKelasJabatanIds = Jabatan::query()
            ->where('nama_jabatan', 'Wali Kelas')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $waliKelasJabatanIds !== []
            && array_intersect($waliKelasJabatanIds, array_map('intval', $jabatans)) !== [];
    }
}
