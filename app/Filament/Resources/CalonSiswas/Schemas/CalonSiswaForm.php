<?php

namespace App\Filament\Resources\CalonSiswas\Schemas;

use App\Models\CalonSiswa;
use App\Models\Murid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class CalonSiswaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Hasil Seleksi')
                    ->description('Hanya bagian ini yang dapat diisi admin. Data calon siswa hanya dapat dilihat.')
                    ->schema([
                        TextInput::make('nilai_tes')
                            ->label('Nilai Tes Masuk')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Skala 0–100. Nilai akan tampil di akun calon siswa.'),
                        Select::make('status')
                            ->label('Status')
                            ->options(CalonSiswa::STATUS)
                            ->disableOptionWhen(fn (string $value): bool => $value === CalonSiswa::STATUS_DITERIMA)
                            ->required()
                            ->default(CalonSiswa::STATUS_MENUNGGU)
                            ->disabled(fn (?CalonSiswa $record): bool => $record?->murid_id !== null)
                            ->dehydrated(fn (?CalonSiswa $record): bool => $record?->murid_id === null)
                            ->helperText('Status Lulus/Tidak Lulus langsung terlihat oleh calon siswa. Status Diterima diatur otomatis saat siswa dibuat.'),
                        Textarea::make('catatan')
                            ->label('Catatan Internal Admin')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Data Pendaftar')
                    ->description('Diisi calon siswa saat membuat akun. Admin tidak dapat mengubah data ini.')
                    ->schema([
                        TextInput::make('nomor_pendaftaran')
                            ->label('Nomor Pendaftaran')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options(CalonSiswa::JENIS_KELAMIN)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('email')
                            ->label('Email Akun')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Email login calon siswa.'),
                        TextInput::make('nohp')
                            ->label('Nomor WhatsApp')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('Data Pribadi')
                    ->description('Diisi calon siswa di panel siswa.')
                    ->schema([
                        TextInput::make('nik')
                            ->label('NIK')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('no_kk')
                            ->label('Nomor Kartu Keluarga')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nisn')
                            ->label('NISN')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->disabled()
                            ->dehydrated(false),
                        DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('agama')
                            ->label('Agama')
                            ->options(CalonSiswa::AGAMA)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nohp_siswa')
                            ->label('No. HP Calon Murid')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Data Orang Tua')
                    ->description('Diisi calon siswa di panel siswa.')
                    ->schema([
                        TextInput::make('nama_ayah')->label('Nama Ayah')->disabled()->dehydrated(false),
                        Select::make('status_ayah')
                            ->label('Status Ayah')
                            ->options(Murid::STATUS_ORANG_TUA)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nik_ayah')->label('NIK Ayah')->disabled()->dehydrated(false),
                        TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah')->disabled()->dehydrated(false),
                        TextInput::make('penghasilan_ayah')->label('Penghasilan Ayah')->disabled()->dehydrated(false),
                        TextInput::make('nohp_ayah')->label('No. HP Ayah')->disabled()->dehydrated(false),
                        TextInput::make('nama_ibu')->label('Nama Ibu')->disabled()->dehydrated(false),
                        Select::make('status_ibu')
                            ->label('Status Ibu')
                            ->options(Murid::STATUS_ORANG_TUA)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('nik_ibu')->label('NIK Ibu')->disabled()->dehydrated(false),
                        TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu')->disabled()->dehydrated(false),
                        TextInput::make('penghasilan_ibu')->label('Penghasilan Ibu')->disabled()->dehydrated(false),
                        TextInput::make('nohp_ibu')->label('No. HP Ibu')->disabled()->dehydrated(false),
                    ])->columns(2),

                Section::make('Dokumen Pendaftaran')
                    ->description('Diunggah calon siswa. Dokumen dapat dipratinjau langsung di halaman ini.')
                    ->schema([
                        TextEntry::make('dokumen_foto')
                            ->label('Foto Calon Murid')
                            ->state(fn (?CalonSiswa $record): HtmlString => self::dokumen($record, 'foto')),
                        TextEntry::make('dokumen_kk')
                            ->label('Kartu Keluarga (KK)')
                            ->state(fn (?CalonSiswa $record): HtmlString => self::dokumen($record, 'kk')),
                        TextEntry::make('dokumen_ijazah')
                            ->label('Ijazah')
                            ->state(fn (?CalonSiswa $record): HtmlString => self::dokumen($record, 'ijazah')),
                        TextEntry::make('dokumen_kip')
                            ->label('KIP (opsional)')
                            ->state(fn (?CalonSiswa $record): HtmlString => self::dokumen($record, 'kip')),
                    ])->columns(1),
            ]);
    }

    private static function dokumen(?CalonSiswa $record, string $field): HtmlString
    {
        $path = $record?->{$field};

        if (blank($path)) {
            return new HtmlString('<span style="color:#9ca3af">Belum diunggah</span>');
        }

        try {
            $url = Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(15));
        } catch (Throwable) {
            return new HtmlString('Sudah diunggah (pratinjau tidak tersedia)');
        }

        $aman = e($url);
        $label = e(CalonSiswa::DOKUMEN[$field] ?? $field);
        $tautan = '<a href="'.$aman.'" target="_blank" rel="noopener" style="font-size:.75rem;color:#2563eb;text-decoration:underline">Buka di tab baru</a>';

        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
            return new HtmlString(
                '<figure style="margin:0;display:grid;gap:.4rem">'
                .'<a href="'.$aman.'" target="_blank" rel="noopener">'
                .'<img src="'.$aman.'" alt="'.$label.'" loading="lazy" style="max-height:14rem;width:auto;max-width:100%;border:1px solid #e5e7eb;border-radius:.5rem">'
                .'</a>'
                .$tautan
                .'</figure>'
            );
        }

        return new HtmlString(
            '<details style="display:grid;gap:.4rem">'
            .'<summary style="cursor:pointer;font-size:.8125rem;font-weight:600">Lihat pratinjau dokumen</summary>'
            .'<iframe src="'.$aman.'" title="'.$label.'" style="margin-top:.5rem;width:100%;height:22rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff"></iframe>'
            .$tautan
            .'</details>'
        );
    }
}
