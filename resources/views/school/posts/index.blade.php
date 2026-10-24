@extends('school.layout')

@section('content')
    <section class="school-page-header">
        <div class="school-container school-page-header-inner">
            <nav aria-label="Breadcrumb" class="school-breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Artikel & Informasi</span>
            </nav>
            <p class="school-eyebrow mt-5">Ruang informasi sekolah</p>
            <h1 class="school-page-title">Artikel & Informasi</h1>
            <p class="school-page-lead">Ikuti berita, pengumuman, agenda, dan cerita prestasi dari {{ $settings->school_name }}.</p>
        </div>
    </section>

    <section class="school-container school-section">
        <form action="{{ route('posts.index') }}" method="GET" role="search" class="school-filter-card">
            <div class="school-field">
                <label for="article-search">Cari informasi</label>
                <input id="article-search" name="q" type="search" maxlength="150" value="{{ $filters['q'] }}" placeholder="Kata kunci berita atau kegiatan…">
            </div>
            <div class="school-field">
                <label for="article-category">Kategori</label>
                <select id="article-category" name="category">
                    <option value="">Semua kategori</option>
                    @foreach (App\Models\Post::categoryLabels() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="school-button school-button-primary w-full justify-center md:w-auto">Cari</button>
        </form>

        @if ($errors->any())
            <div role="alert" class="school-alert mt-5">{{ $errors->first() }}</div>
        @endif

        <p class="mt-6 text-sm text-[var(--school-muted)]">
            {{ $posts->total() }} publikasi ditemukan
            @if ($filters['q'])
                untuk <strong class="text-[var(--school-ink)]">“{{ $filters['q'] }}”</strong>
            @endif
        </p>

        <div class="school-post-grid mt-6">
            @forelse ($posts as $item)
                @include('school.partials.post-card', ['item' => $item])
            @empty
                <div class="school-empty-state">
                    <h2>Belum ada informasi yang ditemukan</h2>
                    <p>Coba kata kunci lain atau lihat kembali semua kategori.</p>
                    <a href="{{ route('posts.index') }}" class="school-text-link mt-4">Lihat semua publikasi</a>
                </div>
            @endforelse
        </div>

        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
@endsection
