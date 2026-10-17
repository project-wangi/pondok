<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ Illuminate\Support\Str::limit($post->excerpt ?? $settings->hero_description, 160) }}">
    <meta name="theme-color" content="#183e63">
    <link rel="icon" href="data:,">
    <title>{{ isset($post) ? $post->title.' | '.$settings->school_name : $settings->school_name.' | '.$settings->tagline }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="school-site font-sans antialiased">
    <a href="#konten" class="school-skip-link">Lewati ke konten</a>
    <header class="school-header">
        <div class="school-container flex min-h-24 items-center justify-between gap-6 py-5 sm:min-h-28">
            <a href="{{ route('home') }}" class="school-brand flex min-w-0 items-center gap-4" aria-label="{{ $settings->school_name }}, beranda">
                @if ($settings->logo)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) }}" alt="Logo {{ $settings->school_name }}" class="size-14 shrink-0 object-contain">
                @endif
                <span class="min-w-0">
                    <span class="school-display block text-xl leading-tight font-semibold sm:text-3xl">{{ $settings->school_name }}</span>
                    <span class="mt-1.5 hidden text-xs leading-5 text-[var(--school-muted)] sm:block">{{ $settings->tagline }}</span>
                </span>
            </a>
            <a href="{{ route('filament.admin.auth.login') }}" class="school-login hidden shrink-0 items-center gap-2 text-sm font-semibold lg:inline-flex"><x-heroicon-o-lock-closed class="size-4" />Masuk admin</a>
            <details class="school-mobile-menu lg:hidden" data-school-menu>
                <summary class="flex min-h-11 min-w-11 cursor-pointer list-none items-center justify-center gap-2 border border-[var(--school-line)] px-3 text-sm font-semibold"><x-heroicon-o-bars-3 class="size-5" /><span>Menu</span></summary>
                <nav aria-label="Navigasi seluler" class="school-mobile-links">
                    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                    <a href="{{ route('home') }}#profil">Profil sekolah</a>
                    <a href="{{ route('posts.index') }}">Artikel & berita</a>
                    <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}">Pengumuman</a>
                    <a href="{{ route('home') }}#kontak">Kontak</a>
                    <a href="{{ route('filament.admin.auth.login') }}">Masuk admin</a>
                </nav>
            </details>
        </div>
        <div class="school-navigation hidden lg:block">
            <nav aria-label="Navigasi utama" class="school-container flex items-center justify-center gap-8">
                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                <a href="{{ route('home') }}#profil">Profil sekolah</a>
                <a href="{{ route('posts.index') }}" @if (request()->routeIs('posts.*')) aria-current="page" @endif>Artikel & berita</a>
                <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}">Pengumuman</a>
                <a href="{{ route('posts.index', ['category' => 'agenda']) }}">Agenda</a>
                <a href="{{ route('home') }}#kontak">Kontak</a>
            </nav>
        </div>
    </header>

    <main id="konten" tabindex="-1">@yield('content')</main>

    <footer id="kontak" class="school-footer scroll-mt-6">
        <div class="school-container grid gap-10 py-12 md:grid-cols-[1.25fr_1fr_1fr] md:gap-12 lg:py-16">
            <div>
                <p class="school-display max-w-sm text-3xl leading-tight">{{ $settings->school_name }}</p>
                <p class="mt-4 max-w-sm text-sm leading-7 text-[var(--school-footer-muted)]">{{ $settings->tagline }}</p>
                <a href="{{ route('home') }}#profil" class="school-footer-link mt-4">Profil sekolah</a>
            </div>
            <div>
                <h2 class="mb-3 text-base font-semibold">Informasi sekolah</h2>
                <div class="grid justify-items-start text-sm">
                    <a href="{{ route('posts.index') }}" class="school-footer-link">Artikel & berita</a>
                    <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}" class="school-footer-link">Pengumuman</a>
                    <a href="{{ route('posts.index', ['category' => 'agenda']) }}" class="school-footer-link">Agenda kegiatan</a>
                    <a href="{{ route('posts.index', ['category' => 'prestasi']) }}" class="school-footer-link">Prestasi</a>
                </div>
            </div>
            <div>
                <h2 class="mb-5 text-base font-semibold">Hubungi sekolah</h2>
                <div class="grid gap-4 text-sm leading-7 text-[var(--school-footer-muted)]">
                    @if ($settings->address)
                        <p class="whitespace-pre-line">{{ $settings->address }}</p>
                    @endif
                    @if ($settings->email)
                        <a href="mailto:{{ $settings->email }}" class="school-footer-link break-all">{{ $settings->email }}</a>
                    @endif
                    @if ($settings->phone)
                        <p>{{ $settings->phone }}</p>
                    @endif
                    @if (!$settings->address && !$settings->email && !$settings->phone)
                        <p>Informasi kontak belum ditambahkan.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="school-container border-t border-white/20 py-5 text-xs leading-6 text-[var(--school-footer-muted)]">&copy; {{ date('Y') }} {{ $settings->school_name }}. Seluruh hak cipta dilindungi.</div>
    </footer>
</body>
</html>
