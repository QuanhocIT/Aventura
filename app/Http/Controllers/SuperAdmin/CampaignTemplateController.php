<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBatchCouponsJob;
use App\Models\CampaignTemplate;
use App\Models\Coupon;
use App\Models\CouponBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignTemplateController extends Controller
{
    public function index()
    {
        // Load template counters in the base query instead of issuing several
        // relationship queries for every template (N+1).
        $usageByTemplate = Coupon::query()
            ->join('coupon_batches', 'coupon_batches.id', '=', 'coupons.batch_id')
            ->whereNotNull('coupon_batches.template_id')
            ->selectRaw('coupon_batches.template_id, SUM(coupons.uses_count) as total_usages')
            ->groupBy('coupon_batches.template_id')
            ->pluck('total_usages', 'coupon_batches.template_id');

        $templates = CampaignTemplate::withCount('batches')
            ->withSum('batches', 'code_count')
            ->orderBy('season')
            ->orderBy('name')
            ->get()
            ->map(function (CampaignTemplate $t) use ($usageByTemplate) {
                $totalUsages = $usageByTemplate->get($t->id, 0);

                return [
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
                    'batches_count' => (int) $t->batches_count,
                    'total_codes' => (int) ($t->batches_sum_code_count ?? 0),
                    'total_usages' => (int) $totalUsages,
                ];
            });

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
            'discount_value' => ['required', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($request->input('discount_type') === 'percent' && (float) $value > 100) {
                    $fail('Giá trị giảm theo phần trăm không được vượt quá 100.');
                }
            }],
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
            'discount_value' => ['required', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($request->input('discount_type') === 'percent' && (float) $value > 100) {
                    $fail('Giá trị giảm theo phần trăm không được vượt quá 100.');
                }
            }],
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
        if (! $campaignTemplate->is_active) {
            return back()->withErrors(['template' => 'Không thể phát hành coupon từ campaign template đang tạm dừng.']);
        }

        $data = $request->validate([
            'code_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $batch = CouponBatch::create([
            'name' => $campaignTemplate->name.' — '.now()->format('d/m/Y'),
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

    public function seedDefaults(Request $request)
    {
        $userId = $request->user()->id;

        $defaults = [
            [
                'name' => 'Khuyến mãi Tết Nguyên Đán',
                'slug' => 'tet-nguyen-dan',
                'season' => 'tet',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'default_duration_days' => 14,
                'default_max_uses' => 500,
                'default_budget_cap' => 10000000,
                'code_prefix' => 'TET',
                'theme_color' => '#ef4444',
                'description' => 'Chiến dịch tri ân đối tác dịp Tết Nguyên Đán với mã giảm 20% cho tất cả các gói dịch vụ.',
                'created_by' => $userId,
            ],
            [
                'name' => 'Chiến dịch Valentine ngọt ngào',
                'slug' => 'valentine-love',
                'season' => 'valentine',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'default_duration_days' => 5,
                'default_max_uses' => 200,
                'default_budget_cap' => 5000000,
                'code_prefix' => 'VAL',
                'theme_color' => '#ec4899',
                'description' => 'Mã giảm giá cố định 100,000₫ cho các cửa hàng đăng ký/gia hạn gói dịch vụ Pro trở lên.',
                'created_by' => $userId,
            ],
            [
                'name' => 'Giáng Sinh ấm áp',
                'slug' => 'noel-christmas',
                'season' => 'noel',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'default_duration_days' => 10,
                'default_max_uses' => 300,
                'default_budget_cap' => 8000000,
                'code_prefix' => 'NOEL',
                'theme_color' => '#10b981',
                'description' => 'Giảm 15% tất cả các dịch vụ để chào đón mùa Giáng Sinh an lành cùng Aventura.',
                'created_by' => $userId,
            ],
            [
                'name' => 'Siêu ưu đãi Black Friday',
                'slug' => 'black-friday-sale',
                'season' => 'black-friday',
                'discount_type' => 'percent',
                'discount_value' => 30,
                'default_duration_days' => 3,
                'default_max_uses' => 1000,
                'default_budget_cap' => 30000000,
                'code_prefix' => 'BFRI',
                'theme_color' => '#0f172a',
                'description' => 'Đợt giảm giá sâu nhất trong năm lên tới 30% cho khách hàng đăng ký hoặc gia hạn dài hạn.',
                'created_by' => $userId,
            ],
        ];

        foreach ($defaults as $data) {
            CampaignTemplate::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        return back()->with('success', 'Đã khởi tạo các bản mẫu chiến dịch theo mùa thành công.');
    }
}
