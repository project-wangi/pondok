<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('image')->label('Gambar')->disk('public')->visibility('public'),
            TextColumn::make('title')->label('Judul')->searchable()->sortable()->limit(70),
            TextColumn::make('category')->label('Kategori')->badge()
                ->formatStateUsing(fn (string $state): string => Post::categoryLabels()[$state] ?? $state),
            IconColumn::make('is_published')->label('Publikasi Aktif')->boolean(),
            TextColumn::make('published_at')->label('Waktu Terbit')->dateTime('d M Y H:i')->sortable()->placeholder('Belum diatur'),
            TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d M Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('category')->label('Kategori')->options(Post::categoryLabels()),
            TernaryFilter::make('is_published')->label('Publikasi Aktif'),
        ])->recordActions([
            EditAction::make()->label('Ubah'),
            DeleteAction::make()->label('Hapus'),
        ])->toolbarActions([])->defaultSort('published_at', 'desc');
    }
}
