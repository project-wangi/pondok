@extends('school.layout')

@section('content')
    <article class="school-article">
        <div class="school-container school-article-inner">
            <nav aria-label="Breadcrumb" class="school-breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('posts.index') }}">Artikel & Informasi</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ App\Models\Post::CATEGORY_LABELS[$post->category] ?? 'Informasi' }}</span>
            </nav>

            <header class="mt-6">
                <a href="{{ route('posts.index', ['category' => $post->category]) }}" class="school-story-category">{{ App\Models\Post::CATEGORY_LABELS[$post->category] ?? 'Informasi' }}</a>
                <h1 class="school-article-title">{{ $post->title }}</h1>
                <p class="school-article-meta">
                    <x-heroicon-o-calendar-days class="size-4" />
                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->locale('id')->translatedFormat('d F Y') }}</time>
                    <span aria-hidden="true">·</span>
                    <span>{{ $settings->school_name }}</span>
                </p>
            </header>

            @if ($post->image)
                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($post->image) }}" alt="{{ $post->title }}" class="school-article-image">
            @endif

            @if ($post->excerpt)
                <p class="school-article-excerpt">{{ $post->excerpt }}</p>
            @endif

            <div class="school-article-body">{{ $post->body }}</div>

            <div class="school-article-footer">
                <a href="{{ route('posts.index') }}" class="school-text-link">
                    <x-heroicon-o-arrow-left class="size-4" />
                    Kembali ke semua artikel
                </a>
            </div>
        </div>
    </article>

    @if ($relatedPosts->isNotEmpty())
        <section class="school-related school-section">
            <div class="school-container">
                <div class="school-section-head">
                    <div>
                        <p class="school-eyebrow">Bacaan lain</p>
                        <h2>Informasi Lainnya</h2>
                    </div>
                </div>
                <div class="school-post-grid">
                    @foreach ($relatedPosts as $item)
                        @include('school.partials.post-card', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
