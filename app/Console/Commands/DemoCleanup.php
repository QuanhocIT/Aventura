<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ẩn dữ liệu demo/ảo trước khi go-live: ẨN (soft-delete) các nhà hàng KHÔNG nằm trong
 * danh sách giữ lại (--keep) khỏi toàn bộ app, và xoá tài khoản thuộc nhà hàng đó.
 *
 *   php artisan demo:cleanup --keep=chuquan@quan.vn           # DRY-RUN: chỉ liệt kê
 *   php artisan demo:cleanup --keep=chuquan@quan.vn --force   # THỰC SỰ ẩn
 *
 * AN TOÀN: mặc định DRY-RUN (không đụng gì). Phải có --force mới thực thi. Giữ tài
 * khoản super_admin. Nhà hàng bị ẩn (SoftDeletes) — hoàn tác được, KHÔNG xoá vĩnh viễn.
 * ⚠️ Muốn DB production HOÀN TOÀN sạch: deploy DB TRỐNG mới rồi `demo:showcase`,
 *    đừng dọn DB dev cũ (web FK khiến hard-delete không sạch).
 */
class DemoCleanup extends Command
{
    protected $signature = 'demo:cleanup
        {--keep= : Danh sách email chủ hoặc ID nhà hàng cần GIỮ (phân tách bởi dấu phẩy)}
        {--force : Thực sự xoá (mặc định chỉ liệt kê)}';

    protected $description = 'Ẩn (soft-delete) các nhà hàng demo/ảo, chỉ giữ nhà hàng chỉ định (dry-run mặc định).';

    public function handle(): int
    {
        $keepTokens = collect(explode(',', (string) $this->option('keep')))
            ->map(fn ($t) => trim($t))->filter()->all();

        if (empty($keepTokens)) {
            $this->error('Phải chỉ định --keep=<email hoặc id nhà hàng cần giữ> để tránh xoá nhầm tất cả.');

            return self::FAILURE;
        }

        // Giải các token giữ lại thành danh sách restaurant_id.
        $keepIds = collect();
        foreach ($keepTokens as $token) {
            if (ctype_digit($token)) {
                $keepIds->push((int) $token);
            } else {
                $rid = User::where('email', $token)->value('restaurant_id');
                if ($rid) {
                    $keepIds->push((int) $rid);
                } else {
                    $this->warn("  Bỏ qua --keep '{$token}': không tìm thấy nhà hàng.");
                }
            }
        }
        $keepIds = $keepIds->unique()->values();

        if ($keepIds->isEmpty()) {
            $this->error('Không giải được nhà hàng nào để giữ — dừng cho an toàn.');

            return self::FAILURE;
        }

        $toDelete = Restaurant::whereNotIn('id', $keepIds)->get(['id', 'name']);

        if ($toDelete->isEmpty()) {
            $this->info('Không có nhà hàng nào cần xoá (ngoài danh sách giữ lại).');

            return self::SUCCESS;
        }

        $rows = $toDelete->map(fn ($r) => [
            $r->id,
            $r->name,
            DB::table('orders')->where('restaurant_id', $r->id)->count(),
            User::where('restaurant_id', $r->id)->count(),
        ])->all();

        $this->newLine();
        $this->line('  <options=bold>Nhà hàng SẼ BỊ XOÁ (kèm toàn bộ dữ liệu con):</>');
        $this->table(['ID', 'Tên', 'Số đơn', 'Tài khoản'], $rows);
        $this->line('  Giữ lại: nhà hàng ID '.$keepIds->implode(', ').' + tài khoản super_admin.');

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('  DRY-RUN — chưa xoá gì. Thêm --force để thực sự xoá.');

            return self::SUCCESS;
        }

        $superAdminIds = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id');

        $deletedR = 0;
        $deletedU = 0;
        // Dùng SOFT-delete (Restaurant có SoftDeletes) — ẩn tenant demo khỏi toàn app
        // một cách an toàn, hoàn tác được. Hard-delete không khả thi sạch vì web FK
        // (vd product_recipes→ingredients ON DELETE RESTRICT). Muốn DB production HOÀN
        // TOÀN sạch: deploy với DB TRỐNG mới (migrate + demo:showcase), đừng dọn DB cũ.
        DB::transaction(function () use ($toDelete, $superAdminIds, &$deletedR, &$deletedU) {
            foreach ($toDelete as $r) {
                $deletedU += User::where('restaurant_id', $r->id)
                    ->whereNotIn('id', $superAdminIds)->delete();
                Restaurant::whereKey($r->id)->delete();
                $deletedR++;
            }
        });

        $this->newLine();
        $this->info("✅ Đã ẩn {$deletedR} nhà hàng và xoá {$deletedU} tài khoản. Giữ lại nhà hàng ID ".$keepIds->implode(', ').'.');

        return self::SUCCESS;
    }
}
