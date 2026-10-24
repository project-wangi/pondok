@php
    $isFeatured = $featured ?? false;
    $isCompact = $compact ?? false;
@endphp
<article @class(['school-story', 'school-story-featured' => $isFeatured, 'school-story-compact' => $isCompact])>
    @if ($item->image)
        <figure class="school-story-image" data-school-photo>
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($item->image) }}" alt="" loading="lazy" width="640" height="400" data-school-image>
            <p data-photo-fallback hidden class="school-story-fallback">Gambar artikel tidak dapat ditampilkan.</p>
        </figure>
    @endif
    <div class="school-story-body">
        <p class="school-story-meta">
            <span class="school-story-category">{{ App\Models\Post::CATEGORY_LABELS[$item->category] ?? 'Informasi' }}</span>
            <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->locale('id')->translatedFormat('d F Y') }}</time>
        </p>
        <h3 class="school-story-title"><a href="{{ route('posts.show', ['post' => $item->slug]) }}">{{ $item->title }}</a></h3>
        <p class="school-story-excerpt">{{ Illuminate\Support\Str::limit($item->excerpt ?: $item->body, $isFeatured ? 240 : 140) }}</p>
        @if (! $isCompact)
            <a href="{{ route('posts.show', ['post' => $item->slug]) }}" class="school-story-link">Baca artikel<span class="sr-only">: {{ $item->title }}</span></a>
        @endif
    </div>
</article>
