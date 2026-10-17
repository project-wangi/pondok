@php
    $isFeatured = $featured ?? false;
    $isCompact = $compact ?? false;
@endphp
<article @class(['school-story', 'school-story-featured' => $isFeatured, 'school-story-compact' => $isCompact])>
    @if ($item->image)
        <figure @class(['school-story-image overflow-hidden bg-slate-100', 'mb-5' => !$isCompact]) data-school-photo>
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($item->image) }}" alt="" loading="lazy" width="640" height="400" class="aspect-[16/10] h-full w-full object-cover" data-school-image>
            <p data-photo-fallback hidden class="p-5 text-sm text-slate-600">Gambar artikel tidak dapat ditampilkan.</p>
        </figure>
    @endif
    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs leading-6 text-[var(--school-muted)]">
            <span class="font-semibold text-[var(--school-blue)]">{{ App\Models\Post::CATEGORY_LABELS[$item->category] ?? 'Informasi' }}</span>
            <span aria-hidden="true">/</span>
            <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->locale('id')->translatedFormat('d F Y') }}</time>
        </p>
        <h3 @class(['school-display mt-2 leading-snug font-semibold', 'text-2xl sm:text-3xl' => $isFeatured, 'text-xl' => !$isFeatured])><a href="{{ route('posts.show', ['post' => $item->slug]) }}" class="school-article-link">{{ $item->title }}</a></h3>
        <p class="mt-3 line-clamp-3 text-sm leading-7 text-[var(--school-muted)]">{{ Illuminate\Support\Str::limit($item->excerpt ?: $item->body, $isFeatured ? 240 : 140) }}</p>
        @if (!$isCompact)
            <a href="{{ route('posts.show', ['post' => $item->slug]) }}" class="school-text-link mt-4 text-sm">Baca artikel<span class="sr-only">: {{ $item->title }}</span></a>
        @endif
    </div>
</article>
