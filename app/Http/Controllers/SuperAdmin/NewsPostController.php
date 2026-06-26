<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

        $posts = $query->paginate(10)->withQueryString()
            ->through(fn (NewsPost $p) => [
                'id'                 => $p->id,
                'title'              => $p->title,
                'slug'               => $p->slug,
                'excerpt'            => $p->excerpt,
                'category'           => $p->category,
                'featured_image_url' => $p->featured_image_url,
                'is_published'       => $p->is_published,
                'is_featured'        => $p->is_featured,
                'view_count'         => $p->view_count,
                'published_at'       => $p->published_at?->format('d/m/Y'),
                'author_name'        => $p->author?->name ?? 'Aventura Team',
                'created_at'         => $p->created_at->format('d/m/Y'),
            ]);

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

        $post = NewsPost::create([
            ...$data,
            'author_id'      => $request->user()?->id,
            'slug'           => NewsPost::generateSlug($data['title']),
            'featured_image' => $path,
            'published_at'   => $data['is_published'] ? ($data['published_at'] ?? now()) : null,
        ]);

        $this->logCmsAction($request, 'created', 'news_post_create', $post, null, $post->only(['title', 'category', 'is_published']));

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

        $old = $post->only(['title', 'category', 'is_published', 'is_featured']);
        $post->update($data);

        $this->logCmsAction($request, 'updated', 'news_post_update', $post, $old, $post->only(['title', 'category', 'is_published', 'is_featured']));

        return back()->with('success', 'Đã cập nhật bài viết.');
    }

    public function destroy(Request $request, NewsPost $post): RedirectResponse
    {
        $old = $post->only(['title', 'category', 'is_published']);

        if ($post->featured_image) {
            Storage::disk('public')->delete($post->featured_image);
        }
        $post->delete();

        $this->logCmsAction($request, 'deleted', 'news_post_delete', $post, $old, null);

        return back()->with('success', 'Đã xóa bài viết.');
    }

    public function togglePublish(Request $request, NewsPost $post): RedirectResponse
    {
        $old = $post->only(['is_published']);

        $post->update([
            'is_published' => ! $post->is_published,
            'published_at' => ! $post->is_published ? ($post->published_at ?? now()) : $post->published_at,
        ]);

        $this->logCmsAction($request, 'updated', 'news_post_toggle_publish', $post, $old, $post->only(['is_published']));

        return back()->with('success', $post->is_published ? 'Đã đăng bài.' : 'Đã ẩn bài.');
    }

    public function getContent(NewsPost $post): \Illuminate\Http\JsonResponse
    {
        return response()->json(['content' => $post->content, 'tags' => $post->tags ?? []]);
    }

    public function toggleFeatured(Request $request, NewsPost $post): RedirectResponse
    {
        $old = $post->only(['is_featured']);

        $post->update(['is_featured' => ! $post->is_featured]);

        $this->logCmsAction($request, 'updated', 'news_post_toggle_featured', $post, $old, $post->only(['is_featured']));

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

    private function logCmsAction(Request $request, string $event, string $action, NewsPost $post, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => $event,
            'action'        => $action,
            'subject_type'  => NewsPost::class,
            'subject_id'    => $post->id,
            'old_values'    => $old,
            'new_values'    => $new,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }
}
