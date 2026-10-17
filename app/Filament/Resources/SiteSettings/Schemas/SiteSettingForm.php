<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Models\SiteSetting;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaults = (new SiteSetting)->attributesToArray();

        return $schema->components([
            Section::make('Identitas Sekolah')->schema([
                TextInput::make('school_name')->label('Nama Sekolah')->required()->maxLength(255)->default($defaults['school_name']),
                TextInput::make('tagline')->label('Slogan')->required()->maxLength(255)->default($defaults['tagline']),
                self::imageUpload('logo', 'Logo'),
            ])->columnSpanFull(),
            Section::make('Beranda')->schema([
                TextInput::make('hero_title')->label('Judul Utama')->required()->maxLength(255)->default($defaults['hero_title']),
                Textarea::make('hero_description')->label('Deskripsi Utama')->required()->rows(3)->maxLength(5000)->default($defaults['hero_description']),
                self::imageUpload('hero_image', 'Gambar Utama'),
                TextInput::make('registration_label')->label('Teks Tombol Pendaftaran')->required()->maxLength(255)->default($defaults['registration_label']),
                TextInput::make('registration_url')->label('Tautan Pendaftaran')->maxLength(2048)
                    ->rules(fn (): array => [self::safeUrlRule()])
                    ->helperText('Gunakan https://, http://, /halaman, atau #bagian. Kosongkan jika belum tersedia.'),
            ])->columnSpanFull(),
            Section::make('Tentang dan Kontak')->schema([
                TextInput::make('about_title')->label('Judul Tentang')->required()->maxLength(255)->default($defaults['about_title']),
                Textarea::make('about_body')->label('Tentang Pondok')->required()->rows(6)->maxLength(20000)
                    ->helperText('Teks biasa, bukan HTML.')->default($defaults['about_body']),
                Textarea::make('address')->label('Alamat')->maxLength(2000)->rows(3),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(50),
            ])->columnSpanFull(),
            Section::make('Tautan Cepat dan Statistik')->schema([
                Repeater::make('shortcuts')->label('Tautan Cepat')->schema([
                    TextInput::make('label')->label('Label')->required()->maxLength(100),
                    TextInput::make('url')->label('Tautan')->required()->maxLength(2048)->rules(fn (): array => [self::safeUrlRule()]),
                    Select::make('icon')->label('Ikon')->options(SiteSetting::SHORTCUT_ICONS)->required()->default('heroicon-o-information-circle'),
                ])->default([])->defaultItems(0)->maxItems(10)->addActionLabel('Tambah Tautan'),
                Repeater::make('stats')->label('Statistik')->schema([
                    TextInput::make('value')->label('Nilai')->required()->maxLength(50),
                    TextInput::make('label')->label('Label')->required()->maxLength(100),
                ])->default([])->defaultItems(0)->maxItems(4)->addActionLabel('Tambah Statistik')
                    ->helperText('Isi hanya dengan data terverifikasi. Biarkan kosong jika belum tersedia.'),
            ])->columnSpanFull(),
        ]);
    }

    private static function imageUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)->label($label)->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)->disk('public')->directory('website')->visibility('public');
    }

    private static function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! SiteSetting::isSafeUrl($value)) {
                $fail('Tautan harus menggunakan http://, https://, /halaman, atau #bagian.');
            }
        };
    }
}
