<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const CATEGORY_LABELS = [
        'berita' => 'Berita',
        'pengumuman' => 'Pengumuman',
        'agenda' => 'Agenda',
        'prestasi' => 'Prestasi',
    ];

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body', 'image',
        'is_published', 'published_at',
    ];

    protected $attributes = [
        'category' => 'berita',
        'is_published' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Isi artikel disimpan sebagai Markdown dan dirender menjadi HTML yang aman.
     *
     * `soft_break` menjaga baris tunggal tetap tampil sebagai baris baru, seperti
     * artikel lama yang dulu disimpan sebagai teks biasa.
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return self::CATEGORY_LABELS;
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
