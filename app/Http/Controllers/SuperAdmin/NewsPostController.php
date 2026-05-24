<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NewsPostController extends Controller
{
    public function index(Request $request): Response
    {
        $query = NewsPost::with('author:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('excerpt', 'like', "%{$s}%"));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'published' => $query->where('is_published', true),
                'draft'     => $query->where('is_published', false),
                default     => null,
            };
        }

        $posts = $query->paginate(20)->withQueryString();

        return Inertia::render('super-admin/news/Index', [
            'posts'   => $posts,
            'filters' => $request->only('search', 'category', 'status'),
            'stats'   => [
                'total'       => NewsPost::count(),
                'published'   => NewsPost::where('is_published', true)->count(),
                'featured'    => NewsPost::where('is_featured', true)->count(),
                'total_views' => NewsPost::sum('view_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $path = $request->hasFile('image')
            ? $request->file('image')->store('news', 'public')
            : null;

        NewsPost::create([
            ...$data,
            'author_id'      => $request->user()?->id,
            'slug'           => NewsPost::generateSlug($data['title']),
            'featured_image' => $path,
            'published_at'   => $data['is_published'] ? ($data['published_at'] ?? now()) : null,
        ]);

        return back()->with('success', 'Đã tạo bài viết thành công.');
    }

    public function update(Request $request, NewsPost $post): RedirectResponse
    {
        $data = $this->validated($request, $post);

        if ($request->hasFile('image')) {
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }
            $data['featured_image'] = $request->file('image')->store('news', 'public');
        }

        if ($data['is_published'] && ! $post->published_at) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $post->update($data);

        return back()->with('success', 'Đã cập nhật bài viết.');
    }

    public function destroy(NewsPost $post): RedirectResponse
    {
        if ($post->featured_image) {
            Storage::disk('public')->delete($post->featured_image);
        }
        $post->delete();

        return back()->with('success', 'Đã xóa bài viết.');
    }

    public function togglePublish(NewsPost $post): RedirectResponse
    {
        $post->update([
            'is_published' => ! $post->is_published,
            'published_at' => ! $post->is_published ? ($post->published_at ?? now()) : $post->published_at,
        ]);

        return back()->with('success', $post->is_published ? 'Đã đăng bài.' : 'Đã ẩn bài.');
    }

    public function getContent(NewsPost $post): \Illuminate\Http\JsonResponse
    {
        return response()->json(['content' => $post->content, 'tags' => $post->tags ?? []]);
    }

    public function toggleFeatured(NewsPost $post): RedirectResponse
    {
        $post->update(['is_featured' => ! $post->is_featured]);

        return back()->with('success', $post->is_featured ? 'Đã ghim bài nổi bật.' : 'Đã bỏ ghim.');
    }

    private function validated(Request $request, ?NewsPost $post = null): array
    {
        return $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'content'      => ['required', 'string'],
            'category'     => ['required', 'in:tin-tuc,khuyen-mai,thanh-cong,cap-nhat,thong-bao'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:50'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_published' => ['boolean'],
            'is_featured'  => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
