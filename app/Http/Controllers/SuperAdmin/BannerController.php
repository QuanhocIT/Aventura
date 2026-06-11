<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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
                'id' => $b->id,
                'slot' => $b->slot,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image_url' => $b->image_url,
                'link_url' => $b->link_url,
                'is_active' => $b->is_active,
                'sort_order' => $b->sort_order,
                'starts_at' => $b->starts_at?->format('Y-m-d'),
                'ends_at' => $b->ends_at?->format('Y-m-d'),
            ]);

        return Inertia::render('super-admin/banners/Index', [
            'banners' => $banners,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'in:hero,promo'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $nextOrder = SiteBanner::where('slot', $data['slot'])->max('sort_order') + 1;

        SiteBanner::create([
            'slot' => $data['slot'],
            'image_path' => $path,
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $nextOrder,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return back()->with('success', 'Banner đã được thêm.');
    }

    public function update(Request $request, SiteBanner $banner): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $banner->update($data);

        return back()->with('success', 'Banner đã được cập nhật.');
    }

    public function destroy(SiteBanner $banner): RedirectResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return back()->with('success', 'Banner đã được xóa.');
    }
}
