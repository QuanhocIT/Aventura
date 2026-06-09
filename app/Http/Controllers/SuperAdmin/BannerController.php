<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SiteBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(): Response
    {
        $banners = SiteBanner::orderBy('slot')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SiteBanner $b) => [
                'id'         => $b->id,
                'slot'       => $b->slot,
                'title'      => $b->title,
                'subtitle'   => $b->subtitle,
                'image_url'  => $b->image_url,
                'link_url'   => $b->link_url,
                'is_active'  => $b->is_active,
                'sort_order' => $b->sort_order,
                'starts_at'  => $b->starts_at?->format('Y-m-d'),
                'ends_at'    => $b->ends_at?->format('Y-m-d'),
            ]);

        return Inertia::render('super-admin/banners/Index', [
            'banners' => $banners,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot'      => ['required', 'in:hero,promo'],
            'image'     => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'title'     => ['nullable', 'string', 'max:120'],
            'subtitle'  => ['nullable', 'string', 'max:200'],
            'link_url'  => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $nextOrder = SiteBanner::where('slot', $data['slot'])->max('sort_order') + 1;

        $banner = SiteBanner::create([
            'slot'       => $data['slot'],
            'image_path' => $path,
            'title'      => $data['title'] ?? null,
            'subtitle'   => $data['subtitle'] ?? null,
            'link_url'   => $data['link_url'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'sort_order' => $nextOrder,
            'starts_at'  => $data['starts_at'] ?? null,
            'ends_at'    => $data['ends_at'] ?? null,
        ]);

        $this->logCmsAction($request, 'created', 'banner_create', $banner, null, $banner->only(['slot', 'title', 'is_active']));

        return back()->with('success', 'Banner đã được thêm.');
    }

    public function update(Request $request, SiteBanner $banner): RedirectResponse
    {
        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:120'],
            'subtitle'   => ['nullable', 'string', 'max:200'],
            'link_url'   => ['nullable', 'url', 'max:500'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'starts_at'  => ['nullable', 'date'],
            'ends_at'    => ['nullable', 'date'],
            'image'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $old = $banner->only(['title', 'subtitle', 'link_url', 'is_active', 'sort_order']);

        unset($data['image']);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($banner->image_path);
            $data['image_path'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        $this->logCmsAction($request, 'updated', 'banner_update', $banner, $old, $banner->only(['title', 'subtitle', 'link_url', 'is_active', 'sort_order']));

        return back()->with('success', 'Banner đã được cập nhật.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.id'         => 'required|integer|exists:site_banners,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($data['items'] as $item) {
            SiteBanner::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'banner_reorder',
            'subject_type'  => SiteBanner::class,
            'subject_id'    => null,
            'old_values'    => [],
            'new_values'    => ['items' => $data['items']],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return back()->with('success', 'Đã cập nhật thứ tự banner.');
    }

    public function destroy(Request $request, SiteBanner $banner): RedirectResponse
    {
        $old = $banner->only(['slot', 'title', 'is_active']);

        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        $this->logCmsAction($request, 'deleted', 'banner_delete', $banner, $old, null);

        return back()->with('success', 'Banner đã được xóa.');
    }

    private function logCmsAction(Request $request, string $event, string $action, SiteBanner $banner, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => $event,
            'action'        => $action,
            'subject_type'  => SiteBanner::class,
            'subject_id'    => $banner->id,
            'old_values'    => $old,
            'new_values'    => $new,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }
}
