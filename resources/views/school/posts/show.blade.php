@extends('school.layout')

@section('content')
    <article class="mx-auto max-w-4xl px-5 py-10 sm:px-8 lg:py-16">
        <nav aria-label="Breadcrumb" class="mb-8 flex flex-wrap items-center gap-2 text-xs text-slate-500"><a href="{{ route('home') }}" class="hover:text-blue-700">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('posts.index') }}" class="hover:text-blue-700">Artikel & Informasi</a><span aria-hidden="true">/</span><span aria-current="page">{{ App\Models\Post::CATEGORY_LABELS[$post->category] ?? 'Informasi' }}</span></nav>
        <header>
            <a href="{{ route('posts.index', ['category' => $post->category]) }}" class="inline-flex rounded-full bg-blue-100 px-4 py-1.5 text-xs font-bold text-blue-800">{{ App\Models\Post::CATEGORY_LABELS[$post->category] ?? 'Informasi' }}</a>
            <h1 class="mt-5 text-3xl leading-tight font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">{{ $post->title }}</h1>
            <p class="mt-5 flex flex-wrap items-center gap-3 text-sm text-slate-500"><x-heroicon-o-calendar-days class="size-4" /><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->locale('id')->translatedFormat('d F Y') }}</time><span aria-hidden="true">·</span>{{ $settings->school_name }}</p>
        </header>
        @if ($post->image)<img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($post->image) }}" alt="{{ $post->title }}" class="mt-8 max-h-140 w-full rounded-2xl object-cover">@endif
        @if ($post->excerpt)<p class="mt-8 border-l-4 border-amber-400 pl-5 text-lg leading-8 text-slate-600">{{ $post->excerpt }}</p>@endif
        <div class="mt-8 text-base leading-8 whitespace-pre-line text-slate-700">{{ $post->body }}</div>
        <div class="mt-12 border-t border-slate-200 pt-6"><a href="{{ route('posts.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700"><x-heroicon-o-arrow-left class="size-4" />Kembali ke semua artikel</a></div>
    </article>
    @if ($relatedPosts->isNotEmpty())
        <section class="border-t border-slate-200 bg-white"><div class="mx-auto max-w-7xl px-5 py-14 sm:px-8"><h2 class="mb-8 text-2xl font-bold text-slate-900">Informasi Lainnya</h2><div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach ($relatedPosts as $item)@include('school.partials.post-card', ['item' => $item])@endforeach</div></div></section>
    @endif
@endsection
