<?php

namespace App\Filament\Resources\Murids\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;


class MuridForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Akademik & Pribadi')
                ->schema([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->relationship('kelas', 'nama_kelas')
                        ->searchable()
                        ->preload()
                        ->required(),

                    FileUpload::make('foto')
                        ->label('Foto Siswa')
                        ->image()
                        ->maxSize(2048)
                        ->disk('local')
                        ->directory('murid-fotos')
                        ->columnSpanFull(),

                    TextInput::make('nama')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('nisn')
                        ->label('NISN')
                        ->type('number')
                        ->required()
                        ->maxLength(10),

                    TextInput::make('nis')
                        ->label('NIS')
                        ->type('number')
                        ->required()
                        ->maxLength(30),

                    TextInput::make('nik')
                        ->label('NIK')
                        ->type('number')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('no_kk')
                        ->type('number')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('nohp_siswa')
                        ->type('number')
                        ->label('No. HP Siswa')
                        ->tel(),

                    Select::make('jenis_kelamin')
                        ->label('Jenis Kelamin')
                        ->options([
                            'laki-laki' => 'Laki-laki',
                            'perempuan' => 'Perempuan',
                        ])
                        ->required(),

                    Select::make('agama')
                        ->label('Agama')
                        ->options([
                            'Islam' => 'Islam',
                            'Kristen' => 'Kristen',
                            'Katolik' => 'Katolik',
                            'Hindu' => 'Hindu',
                            'Buddha' => 'Buddha',
                            'Konghucu' => 'Konghucu',
                        ]),

                    TextInput::make('tempat_lahir')
                        ->label('Tempat Lahir'),

                    DatePicker::make('tanggal_lahir')
                        ->label('Tanggal Lahir'),

                    Textarea::make('alamat')
                        ->label('Alamat Lengkap')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Data Orang Tua / Wali')
                ->schema([
                    TextInput::make('nama_ayah')->label('Nama Ayah'),
                    TextInput::make('nik_ayah')->label('NIK Ayah'),
                    TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah'),
                    TextInput::make('penghasilan_ayah')->label('Penghasilan Ayah'),
                    TextInput::make('nohp_ayah')
                        ->type('number')
                        ->label('No. HP Ayah'),
                    TextInput::make('nama_ibu')->label('Nama Ibu'),
                    TextInput::make('nik_ibu')->label('NIK Ibu'),
                    TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu'),
                    TextInput::make('penghasilan_ibu')->label('Penghasilan Ibu'),
                    TextInput::make('nohp_ibu')
                        ->type('number')
                        ->label('No. HP Ibu'),
                ])->columns(2),

            Section::make('Dokumen Pendukung (PDF / Gambar)')
                ->schema([
                    FileUpload::make('ijazah')
                        ->label('Ijazah')
                        ->disk('local')
                        ->maxSize(2048)
                        ->helperText('Format: PDF / Gambar, maksimal 2MB')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->directory('murid-dokumen/ijazah'),

                    FileUpload::make('kk')
                        ->label('Kartu Keluarga (KK)')
                        ->disk('local')
                        ->maxSize(2048)
                        ->helperText('Format: PDF / Gambar, maksimal 2MB')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->directory('murid-dokumen/kk'),

                    FileUpload::make('kip')
                        ->label('KIP (Kartu Indonesia Pintar - Opsional)')
                        ->disk('local')
                        ->maxSize(2048)
                        ->helperText('Format: PDF / Gambar, maksimal 2MB')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->directory('murid-dokumen/kip'),
                ])->columns(3),
            ])
            ->columns(1);
    }
}
