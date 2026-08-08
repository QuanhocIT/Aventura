<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\RestaurantBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clone nguyên liệu từ chi nhánh nguồn sang chi nhánh đích.
 *
 * Dùng khi onboard chi nhánh mới — thay vì nhập tay từng nguyên liệu,
 * command này tự động:
 *   1. Lấy tất cả nguyên liệu trong công thức (recipe) của sản phẩm
 *      áp dụng cho chi nhánh đích (branch_id=null OR branch_id=target).
 *   2. Với mỗi nguyên liệu thuộc chi nhánh nguồn CHƯA tồn tại ở chi nhánh đích:
 *      - Tạo bản sao Ingredient cho chi nhánh đích (SKU mới, tồn kho = 0).
 *      - Tạo Inventory record với quantity_on_hand = 0 (chờ nhập hàng).
 *      - Cập nhật ProductRecipe của sản phẩm riêng chi nhánh đích trỏ tới ID mới.
 *
 * Idempotent — chạy lại không tạo thêm bản sao.
 *
 * Sử dụng:
 *   php artisan inventory:seed-branch --source=1 --target=10
 *   php artisan inventory:seed-branch --source=1 --target=10 --dry-run
 */
class SeedBranchIngredients extends Command
{
    protected $signature = 'inventory:seed-branch
        {--source= : branch_id của chi nhánh nguồn (để lọc nguyên liệu gốc)}
        {--target= : branch_id của chi nhánh đích (bắt buộc)}
        {--dry-run : Xem trước, không ghi DB}';

    protected $description = 'Clone nguyên liệu từ chi nhánh nguồn sang chi nhánh đích và cập nhật công thức định lượng.';

    public function handle(): int
    {
        $targetId = (int) $this->option('target');
        if (! $targetId) {
            $this->error('Thiếu --target=<branch_id>. Ví dụ: --target=10');

            return self::FAILURE;
        }

        $target = RestaurantBranch::find($targetId);
        if (! $target) {
            $this->error("Không tìm thấy chi nhánh ID {$targetId}.");

            return self::FAILURE;
        }

        $restaurantId = $target->restaurant_id;
        $isDryRun     = (bool) $this->option('dry-run');

        // ── Tìm nguyên liệu nguồn được dùng trong công thức ──────────────────
        $productIds = Product::where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $targetId))
            ->where('is_processed', true)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            $this->warn("Không có sản phẩm cần chế biến nào ở chi nhánh {$target->name}.");

            return self::SUCCESS;
        }

        $recipeIngredientIds = ProductRecipe::whereIn('product_id', $productIds)
            ->pluck('ingredient_id')
            ->unique()
            ->values();

        if ($recipeIngredientIds->isEmpty()) {
            $this->warn("Không có nguyên liệu nào trong công thức của chi nhánh {$target->name}.");

            return self::SUCCESS;
        }

        // Nguyên liệu gốc (KHÔNG thuộc chi nhánh đích)
        $sourceIngredients = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $recipeIngredientIds)
            ->where(fn ($q) => $q->where('branch_id', '!=', $targetId)->orWhereNull('branch_id'))
            ->with('unit')
            ->get()
            ->keyBy('id');

        if ($sourceIngredients->isEmpty()) {
            $this->info("✅ Tất cả nguyên liệu đã thuộc chi nhánh {$target->name} — không cần tạo thêm.");

            return self::SUCCESS;
        }

        // Nguyên liệu đã tồn tại ở chi nhánh đích (name => id, để skip)
        $existingAtTarget = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $targetId)
            ->pluck('id', 'name')
            ->toArray();

        $this->info("🏭 Chi nhánh đích  : [{$targetId}] {$target->name}");
        $this->info('📦 Nguyên liệu cần clone: '.$sourceIngredients->count());
        $this->newLine();

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN — không ghi vào DB.');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $sourceIngredients, $existingAtTarget, $restaurantId, $targetId,
            $isDryRun, &$created, &$skipped
        ) {
            $idMap = []; // old_ingredient_id => new_ingredient_id

            foreach ($sourceIngredients as $oldId => $ing) {
                if (isset($existingAtTarget[$ing->name])) {
                    $idMap[$oldId] = $existingAtTarget[$ing->name];
                    $skipped++;
                    $this->line("  ⏭  Bỏ qua (đã tồn tại): {$ing->name}");
                    continue;
                }

                $this->line("  ➕ Tạo: {$ing->name} ({$ing->unit?->symbol})");

                if ($isDryRun) {
                    $created++;
                    continue;
                }

                $newIng = Ingredient::create([
                    'restaurant_id'           => $restaurantId,
                    'branch_id'               => $targetId,
                    'name'                    => $ing->name,
                    'sku'                     => 'ING-'.strtoupper(Str::random(6)),
                    'unit_id'                 => $ing->unit_id,
                    'category_name'           => $ing->category_name,
                    'storage_type'            => $ing->storage_type ?? 'dry',
                    'default_shelf_life_days' => $ing->default_shelf_life_days,
                    'storage_location'        => $ing->storage_location,
                    'expiry_warning_days'     => $ing->expiry_warning_days ?? 3,
                    'min_stock_level'         => $ing->min_stock_level ?? 0,
                    'reorder_level'           => $ing->reorder_level ?? 0,
                    'auto_waste_end_of_day'   => $ing->auto_waste_end_of_day ?? false,
                    'average_cost'            => 0,
                    'status'                  => 'active',
                ]);

                // Tạo Inventory record tồn kho = 0 (chờ nhập hàng / đối soát đầu kỳ)
                Inventory::firstOrCreate(
                    [
                        'restaurant_id' => $restaurantId,
                        'branch_id'     => $targetId,
                        'ingredient_id' => $newIng->id,
                    ],
                    [
                        'quantity_on_hand'    => 0,
                        'theoretical_quantity' => 0,
                        'last_cost'           => 0,
                    ]
                );

                $idMap[$oldId] = $newIng->id;
                $created++;
            }

            if ($isDryRun || empty($idMap)) {
                return;
            }

            // Cập nhật ProductRecipe của sản phẩm RIÊNG chi nhánh đích
            // (sản phẩm dùng chung branch_id=null giữ nguyên để không ảnh hưởng chi nhánh khác)
            $branchProductIds = Product::where('restaurant_id', $restaurantId)
                ->where('branch_id', $targetId)
                ->where('is_processed', true)
                ->pluck('id');

            if ($branchProductIds->isNotEmpty()) {
                foreach ($idMap as $oldIngId => $newIngId) {
                    ProductRecipe::whereIn('product_id', $branchProductIds)
                        ->where('ingredient_id', $oldIngId)
                        ->update(['ingredient_id' => $newIngId]);
                }
                $this->line("  🔗 Đã cập nhật {$branchProductIds->count()} công thức sản phẩm trỏ tới nguyên liệu mới.");
            } else {
                $this->line('  ℹ️  Không có sản phẩm riêng chi nhánh này — công thức dùng chung giữ nguyên.');
            }
        });

        $this->newLine();
        $this->table(['Kết quả', 'Số lượng'], [
            ['Nguyên liệu tạo mới', $created],
            ['Bỏ qua (đã có)', $skipped],
        ]);

        if (! $isDryRun && $created > 0) {
            $this->info("✅ Hoàn tất! Chi nhánh [{$targetId}] {$target->name} có {$created} nguyên liệu mới.");
            $this->info('   📋 Bước tiếp theo: Nhập hàng / đối soát số dư đầu kỳ để kích hoạt "Chốt an toàn".');
        } elseif ($isDryRun) {
            $this->warn('Đây là dry-run. Chạy lại không có --dry-run để thực sự tạo.');
        }

        return self::SUCCESS;
    }
}
