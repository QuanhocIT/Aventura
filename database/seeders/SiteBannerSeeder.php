<?php

namespace Database\Seeders;

use App\Models\SiteBanner;
use Illuminate\Database\Seeder;

class SiteBannerSeeder extends Seeder
{
    public function run(): void
    {
        SiteBanner::truncate();

        SiteBanner::insert([
            [
                'slot'       => 'hero',
                'title'      => null,
                'subtitle'   => null,
                'image_path' => 'banners/hero-dashboard.svg',
                'link_url'   => null,
                'is_active'  => true,
                'sort_order' => 1,
                'starts_at'  => null,
                'ends_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slot'       => 'hero',
                'title'      => null,
                'subtitle'   => null,
                'image_path' => 'banners/hero-analytics.svg',
                'link_url'   => null,
                'is_active'  => true,
                'sort_order' => 2,
                'starts_at'  => null,
                'ends_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slot'       => 'promo',
                'title'      => null,
                'subtitle'   => null,
                'image_path' => 'banners/promo-trial.svg',
                'link_url'   => '/register',
                'is_active'  => true,
                'sort_order' => 1,
                'starts_at'  => null,
                'ends_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
