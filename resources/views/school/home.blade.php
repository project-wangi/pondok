@extends('school.layout')

@section('content')
    <section class="school-hero" aria-labelledby="welcome-title">
        <div class="school-container grid items-center gap-9 py-10 sm:py-14 lg:grid-cols-[1fr_1.08fr] lg:gap-14 lg:py-16">
            <div class="min-w-0">
                <p class="mb-5 text-sm font-medium text-[var(--school-muted)]">{{ $settings->tagline }}</p>
                <h1 id="welcome-title" class="school-display school-hero-title">{{ $settings->hero_title }}</h1>
                <p class="mt-6 max-w-lg text-base leading-8 text-[var(--school-muted)]">{{ $settings->hero_description }}</p>
                <div class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-3">
                    @if ($settings->registration_url && App\Models\SiteSetting::isSafeUrl($settings->registration_url))
                        <a href="{{ $settings->registration_url }}" class="school-button school-button-accent">{{ $settings->registration_label }}</a>
                    @endif
                    <a href="#profil" class="school-text-link">Profil sekolah</a>
                </div>
            </div>
            <figure class="school-hero-photo min-w-0" data-school-photo>
                @if ($settings->hero_image)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image) }}" alt="Lingkungan {{ $settings->school_name }}" fetchpriority="high" width="960" height="720" class="aspect-[4/3] w-full object-cover" data-school-image>
                    <div class="school-photo-placeholder" data-photo-fallback hidden><p>Foto sekolah tidak dapat ditampilkan.</p></div>
                @else
                    <div class="school-photo-placeholder">
                        <p class="school-display text-2xl sm:text-3xl">Ruang untuk foto sekolah</p>
                        <p class="mt-3 max-w-xs text-sm leading-6">Foto belum ditambahkan. Gambar utama dapat diunggah melalui Pengaturan Website.</p>
                    </div>
                @endif
                <figcaption class="flex items-center justify-between gap-4 border-t border-[var(--school-line)] bg-white px-5 py-4 text-xs leading-5 text-[var(--school-muted)]">
                    <span class="min-w-0">{{ $settings->school_name }}</span>
                    <span class="h-1 w-10 shrink-0 bg-[var(--school-accent)]" aria-hidden="true"></span>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="school-search-band" aria-labelledby="search-title">
        <div class="school-container grid items-center gap-5 py-7 lg:grid-cols-[1fr_1.08fr] lg:gap-14">
            <div><h2 id="search-title" class="text-base font-semibold">Ada informasi yang kamu cari?</h2><p class="mt-1 text-sm text-[var(--school-footer-muted)]">Berita, pengumuman, dan kegiatan dalam satu tempat.</p></div>
            <form action="{{ route('posts.index') }}" method="GET" role="search" data-school-search>
                <label for="home-search" class="sr-only">Cari informasi sekolah</label>
                <div class="flex gap-2">
                    <input id="home-search" name="q" type="search" maxlength="150" placeholder="Ketik kata kunci…" class="school-search-input min-w-0 flex-1" aria-describedby="home-search-status">
                    <button type="submit" class="school-button school-button-white">Cari</button>
                </div>
                <p id="home-search-status" data-search-status role="status" aria-live="polite" class="mt-3 text-sm" hidden></p>
            </form>
        </div>
    </section>

    @php
        $shortcuts = $settings->shortcuts ?: [
            ['label' => 'Profil Sekolah', 'url' => '#profil', 'icon' => 'heroicon-o-book-open'],
            ['label' => 'Berita', 'url' => route('posts.index', ['category' => 'berita']), 'icon' => 'heroicon-o-clipboard-document-list'],
            ['label' => 'Pengumuman', 'url' => route('posts.index', ['category' => 'pengumuman']), 'icon' => 'heroicon-o-megaphone'],
            ['label' => 'Agenda', 'url' => route('posts.index', ['category' => 'agenda']), 'icon' => 'heroicon-o-calendar-days'],
            ['label' => 'Prestasi', 'url' => route('posts.index', ['category' => 'prestasi']), 'icon' => 'heroicon-o-trophy'],
            ['label' => 'Kontak', 'url' => '#kontak', 'icon' => 'heroicon-o-information-circle'],
        ];
    @endphp
    <nav aria-label="Akses cepat" class="school-container py-6 sm:py-8">
        <div class="school-shortcuts grid grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-wrap">
            @foreach ($shortcuts as $shortcut)
                @if (App\Models\SiteSetting::isSafeUrl($shortcut['url'] ?? ''))
                    <a href="{{ $shortcut['url'] }}" class="flex min-w-0 items-center gap-3 px-4 py-5 lg:flex-1">
                        <x-dynamic-component :component="array_key_exists($shortcut['icon'] ?? '', App\Models\SiteSetting::SHORTCUT_ICONS) ? $shortcut['icon'] : 'heroicon-o-information-circle'" class="size-5 shrink-0" />
                        <span class="min-w-0 text-sm font-medium">{{ $shortcut['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    <section class="school-container pt-6 pb-14 sm:pt-9 sm:pb-20" aria-labelledby="news-title">
        <div class="school-section-heading">
            <div><p class="school-section-label">Dari lingkungan sekolah</p><h2 id="news-title" class="school-display text-3xl sm:text-4xl">Kabar & cerita terbaru</h2></div>
            <a href="{{ route('posts.index') }}" class="school-text-link">Semua artikel</a>
        </div>
        @if ($posts->isNotEmpty())
            <div class="grid gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-12">
                @include('school.partials.post-card', ['item' => $posts->first(), 'featured' => true])
                @if ($posts->count() > 1)
                    <div class="school-news-list divide-y divide-[var(--school-line)]">
                        @foreach ($posts->skip(1) as $item)
                            @include('school.partials.post-card', ['item' => $item, 'compact' => true])
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="school-empty-state">
                <p class="school-display text-2xl">Belum ada artikel yang dipublikasikan.</p>
                <p class="mt-3 max-w-xl text-sm leading-7 text-[var(--school-muted)]">Berita dan cerita kegiatan sekolah akan tampil di sini setelah diterbitkan oleh pengelola.</p>
            </div>
        @endif
    </section>

    <section class="school-information" aria-label="Papan informasi sekolah">
        <div class="school-container grid gap-10 py-12 lg:grid-cols-2 lg:gap-16 lg:py-14">
            <div>
                <div class="school-section-heading"><h2 class="school-display text-2xl sm:text-3xl">Pengumuman</h2><a href="{{ route('posts.index', ['category' => 'pengumuman']) }}" class="school-text-link text-sm">Semua pengumuman</a></div>
                <div class="divide-y divide-[var(--school-line)]">
                    @forelse ($announcements as $announcement)
                        <article class="school-bulletin py-5 first:pt-0">
                            <time datetime="{{ $announcement->published_at->toDateString() }}" class="text-xs text-[var(--school-muted)]">{{ $announcement->published_at->locale('id')->translatedFormat('d F Y') }}</time>
                            <h3 class="mt-1 text-base leading-7 font-semibold"><a href="{{ route('posts.show', ['post' => $announcement->slug]) }}" class="school-article-link">{{ $announcement->title }}</a></h3>
                        </article>
                    @empty
                        <p class="py-3 text-sm leading-7 text-[var(--school-muted)]">Belum ada pengumuman terbaru.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <div class="school-section-heading"><h2 class="school-display text-2xl sm:text-3xl">Agenda kegiatan</h2><a href="{{ route('posts.index', ['category' => 'agenda']) }}" class="school-text-link text-sm">Semua agenda</a></div>
                <div class="divide-y divide-[var(--school-line)]">
                    @forelse ($events as $event)
                        <article class="school-bulletin py-5 first:pt-0">
                            <p class="text-xs text-[var(--school-muted)]">Dipublikasikan {{ $event->published_at->locale('id')->translatedFormat('d F Y') }}</p>
                            <h3 class="mt-1 text-base leading-7 font-semibold"><a href="{{ route('posts.show', ['post' => $event->slug]) }}" class="school-article-link">{{ $event->title }}</a></h3>
                        </article>
                    @empty
                        <p class="py-3 text-sm leading-7 text-[var(--school-muted)]">Belum ada agenda yang dipublikasikan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section id="profil" class="school-container scroll-mt-6 py-14 sm:py-20" aria-labelledby="profile-title">
        <div class="grid gap-7 lg:grid-cols-[0.65fr_1.35fr] lg:gap-20">
            <div><p class="school-section-label">Profil sekolah</p><h2 id="profile-title" class="school-display text-3xl leading-tight sm:text-4xl">{{ $settings->about_title }}</h2></div>
            <div>
                <p class="text-base leading-8 whitespace-pre-line text-[var(--school-muted)]">{{ $settings->about_body }}</p>
                @if ($settings->stats)
                    <dl class="mt-8 grid grid-cols-2 gap-6 border-y border-[var(--school-line)] py-6 sm:grid-cols-4">
                        @foreach ($settings->stats as $stat)
                            <div class="min-w-0"><dt class="text-sm leading-6 text-[var(--school-muted)]">{{ $stat['label'] }}</dt><dd class="school-display mt-1 text-3xl">{{ $stat['value'] }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                <a href="#kontak" class="school-text-link mt-5">Hubungi sekolah</a>
            </div>
        </div>
    </section>
@endsection
