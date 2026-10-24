<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ Illuminate\Support\Str::limit($post->excerpt ?? $settings->hero_description, 160) }}">
    <meta name="theme-color" content="#0a2342">
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ isset($post) ? $post->title.' | '.$settings->school_name : $settings->school_name.' | '.$settings->tagline }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="school-site font-sans antialiased">
    <a href="#konten" class="school-skip-link">Lewati ke konten</a>

    <div class="school-topbar">
        <div class="school-container school-topbar-inner">
            <div class="school-topbar-list">
                @if ($settings->email)
                    <a href="mailto:{{ $settings->email }}"><x-heroicon-o-envelope class="inline size-3.5 align-[-2px]" /> {{ $settings->email }}</a>
                @endif
                @if ($settings->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->phone) }}"><x-heroicon-o-phone class="inline size-3.5 align-[-2px]" /> {{ $settings->phone }}</a>
                @endif
                @if (! $settings->email && ! $settings->phone)
                    <span>Informasi dan kegiatan {{ $settings->school_name }}</span>
                @endif
            </div>
            <div class="school-topbar-list">
                <a href="{{ route('filament.admin.auth.login') }}"><x-heroicon-o-lock-closed class="inline size-3.5 align-[-2px]" /> Masuk admin</a>
            </div>
        </div>
    </div>

    <header class="school-header">
        <div class="school-container school-header-inner">
            <a href="{{ route('home') }}" class="school-brand" aria-label="{{ $settings->school_name }}, beranda">
                @if ($settings->logo)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) }}" alt="Logo {{ $settings->school_name }}" class="school-brand-logo">
                @endif
                <span class="min-w-0">
                    <span class="school-brand-name">{{ $settings->school_name }}</span>
                    <span class="school-brand-tagline">{{ $settings->tagline }}</span>
                </span>
            </a>

            <nav aria-label="Navigasi utama" class="school-nav">
                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                <a href="{{ route('home') }}#profil">Profil</a>
                <a href="{{ route('home') }}#program">Program</a>
                <a href="{{ route('posts.index') }}" @if (request()->routeIs('posts.*')) aria-current="page" @endif>Artikel</a>
                <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}">Pengumuman</a>
                <a href="{{ route('posts.index', ['category' => 'agenda']) }}">Agenda</a>
                <a href="{{ route('home') }}#kontak">Kontak</a>
            </nav>

            <div class="flex items-center gap-3">
                @if ($settings->registration_url && App\Models\SiteSetting::isSafeUrl($settings->registration_url))
                    <a href="{{ $settings->registration_url }}" class="school-button school-button-gold school-header-cta">{{ $settings->registration_label }}</a>
                @else
                    <a href="{{ route('home') }}#kontak" class="school-button school-button-gold school-header-cta">Hubungi Kami</a>
                @endif

                <details class="school-mobile-menu" data-school-menu>
                    <summary class="school-mobile-summary">
                        <x-heroicon-o-bars-3 class="size-5" />
                        <span>Menu</span>
                    </summary>
                    <nav aria-label="Navigasi seluler" class="school-mobile-panel">
                        <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                        <a href="{{ route('home') }}#profil">Profil sekolah</a>
                        <a href="{{ route('home') }}#program">Program & layanan</a>
                        <a href="{{ route('posts.index') }}" @if (request()->routeIs('posts.*')) aria-current="page" @endif>Artikel & berita</a>
                        <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}">Pengumuman</a>
                        <a href="{{ route('posts.index', ['category' => 'agenda']) }}">Agenda</a>
                        <a href="{{ route('home') }}#kontak">Kontak</a>
                        @if ($settings->registration_url && App\Models\SiteSetting::isSafeUrl($settings->registration_url))
                            <a href="{{ $settings->registration_url }}" class="school-button school-button-gold">{{ $settings->registration_label }}</a>
                        @endif
                        <a href="{{ route('filament.admin.auth.login') }}">Masuk admin</a>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="konten" tabindex="-1">@yield('content')</main>

    <footer id="kontak" class="school-footer scroll-mt-24">
        <div class="school-container school-footer-grid">
            <div>
                <p class="school-footer-brand">{{ $settings->school_name }}</p>
                <p class="school-footer-copy">{{ $settings->tagline }}</p>
                <a href="{{ route('home') }}#profil" class="school-footer-link">Profil sekolah</a>
            </div>
            <div>
                <h2 class="school-footer-title">Tautan</h2>
                <div class="school-footer-list">
                    <a href="{{ route('home') }}" class="school-footer-link">Beranda</a>
                    <a href="{{ route('home') }}#program" class="school-footer-link">Program & layanan</a>
                    <a href="{{ route('posts.index') }}" class="school-footer-link">Artikel & berita</a>
                    <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}" class="school-footer-link">Pengumuman</a>
                    <a href="{{ route('posts.index', ['category' => 'agenda']) }}" class="school-footer-link">Agenda kegiatan</a>
                </div>
            </div>
            <div>
                <h2 class="school-footer-title">Informasi</h2>
                <div class="school-footer-list">
                    <a href="{{ route('posts.index', ['category' => 'berita']) }}" class="school-footer-link">Berita sekolah</a>
                    <a href="{{ route('posts.index', ['category' => 'prestasi']) }}" class="school-footer-link">Prestasi santri</a>
                    <a href="{{ route('home') }}#kontak" class="school-footer-link">Kontak</a>
                </div>
            </div>
            <div>
                <h2 class="school-footer-title">Hubungi sekolah</h2>
                <div class="school-footer-contact">
                    @if ($settings->address)
                        <p class="whitespace-pre-line">{{ $settings->address }}</p>
                    @endif
                    @if ($settings->email)
                        <a href="mailto:{{ $settings->email }}" class="school-footer-link break-all">{{ $settings->email }}</a>
                    @endif
                    @if ($settings->phone)
                        <p>{{ $settings->phone }}</p>
                    @endif
                    @if (! $settings->address && ! $settings->email && ! $settings->phone)
                        <p>Informasi kontak belum ditambahkan.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="school-footer-bottom">
            <div class="school-container school-footer-bottom-inner">
                <p>&copy; {{ date('Y') }} {{ $settings->school_name }}. Seluruh hak cipta dilindungi.</p>
                <a href="{{ route('filament.admin.auth.login') }}" class="school-footer-link">Masuk admin</a>
            </div>
        </div>
    </footer>
</body>
</html>
