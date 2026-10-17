<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Judul')->required()->maxLength(255)->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                    if (blank($get('slug')) || $get('slug') === Str::slug($old ?? '')) {
                        $set('slug', Str::slug($state ?? ''));
                    }
                }),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)
                ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                ->unique(ignoreRecord: true)
                ->helperText('Huruf kecil, angka, dan tanda hubung. Mengubah slug akan mengubah tautan artikel.'),
            Select::make('category')->label('Kategori')->options(Post::categoryLabels())->required()->default('berita'),
            Textarea::make('excerpt')->label('Ringkasan')->rows(3)->maxLength(2000)->columnSpanFull(),
            Textarea::make('body')->label('Isi Artikel')->required()->rows(12)->maxLength(100000)
                ->helperText('Teks biasa, bukan HTML.')->columnSpanFull(),
            FileUpload::make('image')->label('Gambar')->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)->disk('public')->directory('website')->visibility('public'),
            Toggle::make('is_published')->label('Publikasikan')->default(false)->live(),
            DateTimePicker::make('published_at')->label('Waktu Terbit')->seconds(false)
                ->requiredIf('is_published', true)
                ->helperText('Artikel hanya tampil jika publikasi aktif dan waktu terbit telah tiba. Waktu mendatang menjadwalkan publikasi.'),
        ]);
    }
}
