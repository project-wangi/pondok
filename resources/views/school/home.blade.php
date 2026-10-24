@extends('school.layout')

@section('content')
    <section class="school-hero" aria-labelledby="welcome-title" data-school-photo>
        @if ($settings->hero_image)
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image) }}" alt="Lingkungan {{ $settings->school_name }}" fetchpriority="high" width="1600" height="1000" class="school-hero-media" data-school-image>
            <div class="school-hero-overlay" aria-hidden="true"></div>
            <p class="school-hero-fallback" data-photo-fallback hidden>Foto sekolah tidak dapat ditampilkan.</p>
        @else
            <div class="school-hero-overlay" aria-hidden="true"></div>
        @endif

        <div class="school-container school-hero-inner">
            <p class="school-eyebrow school-eyebrow-light">{{ $settings->tagline }}</p>
            <h1 id="welcome-title" class="school-hero-title">{{ $settings->hero_title }}</h1>
            <p class="school-hero-lead">{{ $settings->hero_description }}</p>
            <div class="school-hero-actions">
                @if ($settings->registration_url && App\Models\SiteSetting::isSafeUrl($settings->registration_url))
                    <a href="{{ $settings->registration_url }}" class="school-button school-button-gold">{{ $settings->registration_label }}</a>
                @endif
                <a href="#profil" class="school-button school-button-outline">Profil sekolah</a>
            </div>
            @unless ($settings->hero_image)
                <div class="school-hero-note">
                    <strong>Ruang untuk foto sekolah</strong>
                    <span>Foto belum ditambahkan. Gambar utama dapat diunggah melalui Pengaturan Website.</span>
                </div>
            @endunless
        </div>
    </section>

    @if (! empty($settings->stats))
        <section class="school-stats" aria-label="Data sekolah">
            <div class="school-container">
                <dl class="school-stat-grid">
                    @foreach ($settings->stats as $stat)
                        <div class="school-stat">
                            <dt>{{ $stat['label'] ?? '' }}</dt>
                            <dd>{{ $stat['value'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    @php
        $shortcuts = $settings->shortcuts ?: [
            ['label' => 'Profil Sekolah', 'url' => '#profil', 'icon' => 'heroicon-o-book-open'],
            ['label' => 'Berita', 'url' => route('posts.index', ['category' => 'berita']), 'icon' => 'heroicon-o-clipboard-document-list'],
            ['label' => 'Pengumuman', 'url' => route('posts.index', ['category' => 'pengumuman']), 'icon' => 'heroicon-o-megaphone'],
            ['label' => 'Agenda', 'url' => route('posts.index', ['category' => 'agenda']), 'icon' => 'heroicon-o-calendar-days'],
            ['label' => 'Prestasi', 'url' => route('posts.index', ['category' => 'prestasi']), 'icon' => 'heroicon-o-trophy'],
            ['label' => 'Kontak', 'url' => '#kontak', 'icon' => 'heroicon-o-information-circle'],
        ];
        $safeShortcuts = collect($shortcuts)->filter(
            fn (array $shortcut): bool => App\Models\SiteSetting::isSafeUrl($shortcut['url'] ?? ''),
        );
        $programs = collect($settings->programs)->filter(
            fn (mixed $program): bool => is_array($program) && filled($program['title'] ?? null),
        );
        $rankings = collect($settings->rankings)->filter(
            fn (mixed $ranking): bool => is_array($ranking) && filled($ranking['title'] ?? null),
        );
        $excellenceFeatures = collect($settings->excellence_features)->filter(
            fn (mixed $feature): bool => is_array($feature) && filled($feature['title'] ?? null),
        );
        $partners = collect($settings->partners)->filter(
            fn (mixed $partner): bool => is_array($partner) && filled($partner['logo'] ?? null),
        );
        $contentIcon = fn (?string $icon): string => is_string($icon) && array_key_exists($icon, App\Models\SiteSetting::CONTENT_ICONS)
            ? $icon
            : 'heroicon-o-star';
    @endphp

    @if ($programs->isNotEmpty() || $safeShortcuts->isNotEmpty())
        <section id="program" class="school-container school-section scroll-mt-24" aria-labelledby="program-title">
            <div class="school-section-head school-section-head-centered">
                <div>
                    <p class="school-eyebrow">Program & Layanan</p>
                    <h2 id="program-title">{{ $programs->isNotEmpty() ? 'Program pendidikan kami' : 'Semua informasi sekolah dalam satu tempat' }}</h2>
                    <p>
                        {{ $programs->isNotEmpty()
                            ? 'Pilihan program yang diselenggarakan ' . $settings->school_name . '.'
                            : 'Akses cepat ke profil, berita, pengumuman, agenda, prestasi, dan kontak sekolah.' }}
                    </p>
                </div>
            </div>

            @if ($programs->isNotEmpty())
                <div class="school-program-grid">
                    @foreach ($programs as $program)
                        <article class="school-program-card school-program-card-detailed">
                            <span class="school-program-icon">
                                <x-dynamic-component :component="$contentIcon($program['icon'] ?? null)" />
                            </span>
                            <h3 class="school-program-title">{{ $program['title'] }}</h3>
                            <p class="school-program-text">{{ $program['description'] ?? '' }}</p>
                            @if (filled($program['target'] ?? null))
                                <p class="school-program-target">{{ $program['target'] }}</p>
                            @endif
                            @if (filled($program['url'] ?? null) && App\Models\SiteSetting::isSafeUrl($program['url']))
                                <a href="{{ $program['url'] }}" class="school-program-action">{{ ($program['button_label'] ?? '') ?: 'Informasi Program' }}</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="school-program-grid">
                    @foreach ($safeShortcuts as $shortcut)
                        <a href="{{ $shortcut['url'] }}" class="school-program-card">
                            <span class="school-program-icon">
                                <x-dynamic-component :component="array_key_exists($shortcut['icon'] ?? '', App\Models\SiteSetting::SHORTCUT_ICONS) ? $shortcut['icon'] : 'heroicon-o-information-circle'" />
                            </span>
                            <span class="min-w-0">
                                <span class="school-program-title">{{ $shortcut['label'] }}</span>
                                <span class="school-program-meta">Selengkapnya</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section id="profil" class="school-container school-section scroll-mt-24" aria-labelledby="profile-title">
        <div class="school-about-grid">
            <div class="school-about-panel">
                <p class="school-eyebrow school-eyebrow-light">Tentang Kami</p>
                <h2 id="profile-title">{{ $settings->about_title }}</h2>
                <p>{{ $settings->tagline }}</p>
            </div>
            <div class="school-about-body">
                <p>{{ $settings->about_body }}</p>
                <div class="school-about-actions">
                    <a href="#kontak" class="school-button school-button-primary">Hubungi sekolah</a>
                    <a href="#program" class="school-text-link">Lihat layanan</a>
                </div>
            </div>
        </div>
    </section>

    @if ($rankings->isNotEmpty())
        <section class="school-rankings" aria-labelledby="rankings-title">
            <div class="school-container">
                <div class="school-section-head school-section-head-centered">
                    <div>
                        <p class="school-eyebrow school-eyebrow-light">Peringkat & Pencapaian</p>
                        <h2 id="rankings-title">Posisi sekolah dalam data resmi</h2>
                        <p>Angka dan peringkat berikut berasal dari data yang dicatat pengelola sekolah.</p>
                    </div>
                </div>
                <div class="school-rank-grid">
                    @foreach ($rankings as $ranking)
                        <article class="school-rank-card">
                            <span class="school-rank-icon">
                                <x-dynamic-component :component="$contentIcon($ranking['icon'] ?? null)" />
                            </span>
                            <p class="school-rank-title">{{ $ranking['title'] }}</p>
                            <p class="school-rank-value">{{ $ranking['value'] ?? '' }}</p>
                            @if (filled($ranking['description'] ?? null))
                                <p class="school-rank-desc">{{ $ranking['description'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($excellenceFeatures->isNotEmpty())
        <section class="school-container school-section" aria-labelledby="excellence-title">
            <div class="school-section-head school-section-head-centered">
                <div>
                    <p class="school-eyebrow">Keunggulan</p>
                    <h2 id="excellence-title">Keunggulan program</h2>
                    <p>Program pendukung yang membentuk pengalaman belajar santri di {{ $settings->school_name }}.</p>
                </div>
            </div>
            <div class="school-excellence-grid">
                @php($featureNumber = 0)
                @foreach ($excellenceFeatures as $feature)
                    @php($featureNumber++)
                    <article class="school-excellence-card">
                        <span class="school-excellence-number" aria-hidden="true">{{ str_pad((string) $featureNumber, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="school-excellence-icon">
                            <x-dynamic-component :component="$contentIcon($feature['icon'] ?? null)" />
                        </span>
                        <h3 class="school-excellence-title">{{ $feature['title'] }}</h3>
                        @if (filled($feature['description'] ?? null))
                            <p class="school-excellence-text">{{ $feature['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="school-cta" aria-labelledby="registration-title">
        <div class="school-container school-cta-inner">
            <div>
                <p class="school-eyebrow school-eyebrow-light">Pendaftaran</p>
                <h2 id="registration-title" class="school-cta-title">Penerimaan Santri Baru</h2>
                <p class="school-cta-text">Informasi pendaftaran dan penerimaan santri baru dapat dilihat melalui kanal resmi sekolah. Hubungi admin untuk jadwal, persyaratan, dan alur pendaftaran.</p>
            </div>
            <div class="school-cta-actions">
                @if ($settings->registration_url && App\Models\SiteSetting::isSafeUrl($settings->registration_url))
                    <a href="{{ $settings->registration_url }}" class="school-button school-button-gold">{{ $settings->registration_label }}</a>
                @endif
                <a href="#kontak" class="school-text-link">Tanya admin</a>
            </div>
        </div>
    </section>

    <section class="school-container school-section" aria-labelledby="news-title">
        <div class="school-section-head">
            <div>
                <p class="school-eyebrow">Kabar & Informasi</p>
                <h2 id="news-title">Kabar & cerita terbaru</h2>
                <p>Berita, kegiatan, dan cerita dari lingkungan {{ $settings->school_name }}.</p>
            </div>
            <div class="grid justify-items-start gap-3">
                <form action="{{ route('posts.index') }}" method="GET" role="search" class="grid gap-2" data-school-search>
                    <label for="home-search" class="sr-only">Cari informasi sekolah</label>
                    <div class="school-search-inline">
                        <input id="home-search" name="q" type="search" maxlength="150" placeholder="Cari berita atau kegiatan…" aria-describedby="home-search-status">
                        <button type="submit" class="school-button school-button-gold">Cari</button>
                    </div>
                    <p id="home-search-status" data-search-status role="status" aria-live="polite" class="school-search-status" hidden></p>
                </form>
                <a href="{{ route('posts.index') }}" class="school-text-link">Semua artikel</a>
            </div>
        </div>

        @if ($posts->isNotEmpty())
            <div class="school-news-grid">
                @include('school.partials.post-card', ['item' => $posts->first(), 'featured' => true])
                @if ($posts->count() > 1)
                    <div class="school-news-list">
                        @foreach ($posts->skip(1) as $item)
                            @include('school.partials.post-card', ['item' => $item, 'compact' => true])
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="school-empty-state">
                <h2>Belum ada artikel yang dipublikasikan</h2>
                <p>Berita dan cerita kegiatan sekolah akan tampil di sini setelah diterbitkan oleh pengelola.</p>
            </div>
        @endif
    </section>

    @if ($partners->isNotEmpty())
        <section class="school-container school-section" aria-labelledby="partners-title">
            <div class="school-section-head school-section-head-centered">
                <div>
                    <p class="school-eyebrow">Kerja Sama</p>
                    <h2 id="partners-title">Mitra sekolah</h2>
                    <p>Lembaga dan institusi yang bekerja sama dengan {{ $settings->school_name }}.</p>
                </div>
            </div>
            <div class="school-partner-grid">
                @foreach ($partners as $partner)
                    <div class="school-partner">
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($partner['logo']) }}"
                            alt="{{ $partner['name'] ?? '' }}" loading="lazy" width="200" height="80">
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="school-information school-section" aria-label="Papan informasi sekolah">
        <div class="school-container school-information-grid">
            <div class="school-info-card">
                <div class="school-info-card-head">
                    <h2>Pengumuman</h2>
                    <a href="{{ route('posts.index', ['category' => 'pengumuman']) }}" class="school-text-link">Semua pengumuman</a>
                </div>
                <div>
                    @forelse ($announcements as $announcement)
                        <article class="school-bulletin">
                            <time datetime="{{ $announcement->published_at->toDateString() }}">{{ $announcement->published_at->locale('id')->translatedFormat('d F Y') }}</time>
                            <h3><a href="{{ route('posts.show', ['post' => $announcement->slug]) }}">{{ $announcement->title }}</a></h3>
                        </article>
                    @empty
                        <p class="school-bulletin">Belum ada pengumuman terbaru.</p>
                    @endforelse
                </div>
            </div>

            <div class="school-info-card">
                <div class="school-info-card-head">
                    <h2>Agenda kegiatan</h2>
                    <a href="{{ route('posts.index', ['category' => 'agenda']) }}" class="school-text-link">Semua agenda</a>
                </div>
                <div>
                    @forelse ($events as $event)
                        <article class="school-bulletin">
                            <p class="school-bulletin-date">Dipublikasikan {{ $event->published_at->locale('id')->translatedFormat('d F Y') }}</p>
                            <h3><a href="{{ route('posts.show', ['post' => $event->slug]) }}">{{ $event->title }}</a></h3>
                        </article>
                    @empty
                        <p class="school-bulletin">Belum ada agenda yang dipublikasikan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
