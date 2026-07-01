<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBatchCouponsJob;
use App\Models\CampaignTemplate;
use App\Models\CouponBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignTemplateController extends Controller
{
    public function index()
    {
        $templates = CampaignTemplate::orderBy('season')
            ->orderBy('name')
            ->get()
            ->map(fn (CampaignTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'season' => $t->season,
                'description' => $t->description,
                'discount_type' => $t->discount_type,
                'discount_value' => $t->discount_value,
                'default_duration_days' => $t->default_duration_days,
                'default_budget_cap' => $t->default_budget_cap,
                'default_max_uses' => $t->default_max_uses,
                'code_prefix' => $t->code_prefix,
                'theme_color' => $t->theme_color,
                'is_active' => $t->is_active,
                'batches_count' => $t->batches()->count(),
            ]);

        $seasons = [
            'tet' => 'Tết Nguyên Đán',
            'valentine' => 'Valentine (14/2)',
            'women_day_8_3' => 'Quốc tế Phụ nữ (8/3)',
            'women_day_20_10' => 'Phụ nữ Việt Nam (20/10)',
            'mid_autumn' => 'Tết Trung Thu',
            'national_day' => 'Quốc Khánh (2/9)',
            'black_friday' => 'Black Friday',
            'noel' => 'Giáng Sinh (Noel)',
            'custom' => 'Tùy chỉnh',
        ];

        return Inertia::render('super-admin/campaign-templates/Index', [
            'templates' => $templates,
            'seasons' => $seasons,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:campaign_templates,slug'],
            'season' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'default_duration_days' => ['required', 'integer', 'min:1'],
            'default_budget_cap' => ['nullable', 'numeric', 'min:0'],
            'default_max_uses' => ['nullable', 'integer', 'min:1'],
            'code_prefix' => ['required', 'string', 'max:10'],
            'theme_color' => ['nullable', 'string', 'max:7'],
        ]);

        $data['created_by'] = $request->user()->id;

        CampaignTemplate::create($data);

        return back()->with('success', 'Đã tạo template chiến dịch thành công.');
    }

    public function update(Request $request, CampaignTemplate $campaignTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'default_duration_days' => ['required', 'integer', 'min:1'],
            'default_budget_cap' => ['nullable', 'numeric', 'min:0'],
            'default_max_uses' => ['nullable', 'integer', 'min:1'],
            'code_prefix' => ['required', 'string', 'max:10'],
            'theme_color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $campaignTemplate->update($data);

        return back()->with('success', 'Đã cập nhật template.');
    }

    public function destroy(CampaignTemplate $campaignTemplate)
    {
        $campaignTemplate->delete();

        return back()->with('success', 'Đã xóa template.');
    }

    public function generate(Request $request, CampaignTemplate $campaignTemplate)
    {
        $data = $request->validate([
            'code_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $batch = CouponBatch::create([
            'name' => $campaignTemplate->name . ' — ' . now()->format('d/m/Y'),
            'template_id' => $campaignTemplate->id,
            'code_prefix' => $campaignTemplate->code_prefix,
            'code_count' => $data['code_count'],
            'discount_type' => $campaignTemplate->discount_type,
            'discount_value' => $campaignTemplate->discount_value,
            'max_uses_per_code' => $campaignTemplate->default_max_uses ?? 1,
            'starts_at' => $data['starts_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? now()->addDays($campaignTemplate->default_duration_days),
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        GenerateBatchCouponsJob::dispatch($batch);

        return back()->with('success', "Đang tạo {$data['code_count']} mã coupon. Kiểm tra tiến độ tại trang Coupon.");
    }
}
