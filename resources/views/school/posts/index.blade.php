@extends('school.layout')

@section('content')
    <section class="border-b border-blue-100 bg-blue-50">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:py-16">
            <nav aria-label="Breadcrumb" class="mb-5 flex items-center gap-2 text-xs text-slate-500"><a href="{{ route('home') }}" class="hover:text-blue-700">Beranda</a><span aria-hidden="true">/</span><span aria-current="page">Artikel & Informasi</span></nav>
            <p class="mb-3 text-xs font-bold tracking-[0.2em] text-blue-600 uppercase">Ruang informasi sekolah</p>
            <h1 class="text-3xl font-bold tracking-tight text-blue-950 sm:text-4xl">Artikel & Informasi</h1>
            <p class="mt-4 max-w-xl leading-7 text-slate-600">Ikuti berita, pengumuman, agenda, dan cerita prestasi dari {{ $settings->school_name }}.</p>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:py-14">
        <form action="{{ route('posts.index') }}" method="GET" role="search" class="mb-8 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-end">
            <div class="flex-1"><label for="article-search" class="mb-2 block text-sm font-semibold text-slate-700">Cari informasi</label><input id="article-search" name="q" type="search" maxlength="150" value="{{ $filters['q'] }}" placeholder="Kata kunci berita atau kegiatan…" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none"></div>
            <div class="sm:w-52"><label for="article-category" class="mb-2 block text-sm font-semibold text-slate-700">Kategori</label><select id="article-category" name="category" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none"><option value="">Semua kategori</option>@foreach (App\Models\Post::categoryLabels() as $value => $label)<option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-xl bg-blue-900 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-800"><x-heroicon-o-magnifying-glass class="size-5" />Cari</button>
        </form>
        @if ($errors->any())<div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        <p class="mb-6 text-sm text-slate-500">{{ $posts->total() }} publikasi ditemukan @if ($filters['q'])untuk <strong class="text-slate-800">“{{ $filters['q'] }}”</strong>@endif</p>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $item)
                @include('school.partials.post-card', ['item' => $item])
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-blue-200 bg-white px-6 py-20 text-center"><x-heroicon-o-magnifying-glass class="mx-auto mb-4 size-10 text-blue-300" /><h2 class="text-xl font-bold text-slate-900">Belum ada informasi yang ditemukan</h2><p class="mt-3 text-sm text-slate-500">Coba kata kunci lain atau lihat kembali semua kategori.</p><a href="{{ route('posts.index') }}" class="mt-6 inline-flex rounded-xl bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-700">Lihat semua publikasi</a></div>
            @endforelse
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
@endsection
