<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolWebsiteController extends Controller
{
    public function home(): View
    {
        return view('school.home', [
            'settings' => SiteSetting::current(),
            'posts' => Post::query()->published()->latest('published_at')->limit(4)->get(),
            'announcements' => Post::query()->published()->where('category', 'pengumuman')->latest('published_at')->limit(3)->get(),
            'events' => Post::query()->published()->where('category', 'agenda')->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', Rule::in(array_keys(Post::categoryLabels()))],
        ]);

        $filters = [
            'q' => trim($validated['q'] ?? ''),
            'category' => $validated['category'] ?? '',
        ];

        $posts = Post::query()->published()
            ->when($filters['category'] !== '', fn (Builder $query): Builder => $query->where('category', $filters['category']))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $search) use ($filters): void {
                    $term = '%'.$filters['q'].'%';
                    $search->where('title', 'like', $term)
                        ->orWhere('excerpt', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('school.posts.index', [
            'settings' => SiteSetting::current(),
            'posts' => $posts,
            'filters' => $filters,
        ]);
    }

    public function show(Post $post): View
    {
        abort_unless(Post::query()->published()->whereKey($post->getKey())->exists(), 404);

        return view('school.posts.show', [
            'settings' => SiteSetting::current(),
            'post' => $post,
            'relatedPosts' => Post::query()->published()
                ->where('id', '!=', $post->getKey())
                ->latest('published_at')->limit(3)->get(),
        ]);
    }
}
