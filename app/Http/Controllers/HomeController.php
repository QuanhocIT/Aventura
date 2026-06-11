<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\SiteBanner;
use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function index(): Response
    {
        $banners = SiteBanner::active()
            ->orderBy('slot')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('slot')
            ->map(fn ($group) => $group->map(fn (SiteBanner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image_url' => $b->image_url,
                'link_url' => $b->link_url,
            ])->values()->all())
            ->all();

        $latestNews = NewsPost::published()
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map(fn (NewsPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'category' => $p->category,
                'featured_image_url' => $p->featured_image_url,
                'is_featured' => $p->is_featured,
                'published_at' => $p->published_at?->format('d/m/Y'),
            ])->all();

        $plans = SubscriptionPlan::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'price' => (int) $p->price,
                'billing_cycle' => $p->billing_cycle,
                'max_branches' => $p->max_branches,
                'max_tables' => $p->max_tables,
                'max_users' => $p->max_users,
                'features' => $p->features ?? [],
            ])->all();

        return Inertia::render('Khach', [
            'canRegister' => Features::enabled(Features::registration()),
            'banners' => $banners,
            'latestNews' => $latestNews,
            'plans' => $plans,
        ]);
    }
}
