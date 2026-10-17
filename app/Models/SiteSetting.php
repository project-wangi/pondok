<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public const SHORTCUT_ICONS = [
        'heroicon-o-book-open' => 'Buku',
        'heroicon-o-academic-cap' => 'Pendidikan',
        'heroicon-o-clipboard-document-list' => 'Pendaftaran',
        'heroicon-o-trophy' => 'Prestasi',
        'heroicon-o-calendar-days' => 'Agenda',
        'heroicon-o-megaphone' => 'Pengumuman',
        'heroicon-o-user-group' => 'Komunitas',
        'heroicon-o-information-circle' => 'Informasi',
    ];

    public $incrementing = false;

    protected $fillable = [
        'school_name', 'tagline', 'hero_title', 'hero_description', 'hero_image',
        'logo', 'about_title', 'about_body', 'address', 'email', 'phone',
        'registration_label', 'registration_url', 'shortcuts', 'stats',
    ];

    protected $attributes = [
        'id' => 1,
        'school_name' => 'Pondok Pesantren',
        'tagline' => 'Pendidikan, ilmu, dan akhlak',
        'hero_title' => 'Selamat Datang di Pondok Pesantren',
        'hero_description' => 'Temukan informasi tentang pendidikan, kegiatan, dan pengumuman pondok di sini.',
        'hero_image' => null,
        'logo' => null,
        'about_title' => 'Tentang Pondok',
        'about_body' => 'Website ini menjadi sarana informasi pondok bagi santri, orang tua, dan masyarakat.',
        'address' => null,
        'email' => null,
        'phone' => null,
        'registration_label' => 'Informasi Pendaftaran',
        'registration_url' => null,
        'shortcuts' => '[]',
        'stats' => '[]',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $settings): void {
            if ((int) $settings->getKey() !== 1) {
                throw ValidationException::withMessages([
                    'school_name' => 'Pengaturan website hanya boleh menggunakan ID 1.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shortcuts' => 'array',
            'stats' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new static;
    }

    public static function isSafeUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true);
    }
}
