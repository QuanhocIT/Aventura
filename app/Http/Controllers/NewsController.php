<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = NewsPost::published()->latest('published_at');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $q = $request->string('search');
            $query->where(fn ($b) => $b->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"));
        }

        $posts = $query->paginate(12)->withQueryString()
            ->through(fn (NewsPost $p) => $this->postCard($p));

        $featuredPosts = NewsPost::published()->featured()
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(fn (NewsPost $p) => $this->postCard($p));

        $categoryCounts = NewsPost::published()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return Inertia::render('News', [
            'posts' => $posts,
            'featuredPosts' => $featuredPosts,
            'activeCategory' => $request->string('category')->toString() ?: null,
            'activeSearch' => $request->string('search')->toString() ?: null,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    public function show(string $slug): Response
    {
        $post = NewsPost::published()->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        $relatedPosts = NewsPost::published()
            ->where('category', $post->category)
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(fn (NewsPost $p) => $this->postCard($p));

        return Inertia::render('NewsShow', [
            'post' => $this->postDetail($post),
            'relatedPosts' => $relatedPosts,
        ]);
    }

    private function postCard(NewsPost $post): array
    {
        $wordCount = str_word_count(strip_tags($post->content ?? ''));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'category' => $post->category,
            'tags' => $post->tags ?? [],
            'featured_image_url' => $post->featured_image_url,
            'is_featured' => $post->is_featured,
            'view_count' => $post->view_count,
            'published_at' => $post->published_at?->format('d/m/Y'),
            'author_name' => $post->author?->name ?? 'Aventura Team',
            'reading_time' => $readingTime,
        ];
    }

    private function postDetail(NewsPost $post): array
    {
        return [
            ...$this->postCard($post),
            'content' => $post->content,
        ];
    }
}
