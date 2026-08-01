<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Nhập nhanh thực đơn từ file CSV cho một nhà hàng — giảm thời gian onboarding
 * quán pilot (nhập cả trăm món trong vài giây thay vì gõ tay).
 *
 *   php artisan menu:import thucdon.csv --email=owner@quan.vn
 *   php artisan menu:import --sample > mau.csv      # xuất file CSV mẫu
 *
 * Cột CSV (dòng đầu là tiêu đề): name,category,price,cost_price,description,sku
 *   - name (bắt buộc), category (bắt buộc), price (bắt buộc)
 *   - cost_price, description, sku: tuỳ chọn
 * Idempotent: chạy lại sẽ cập nhật món cùng tên trong cùng danh mục (không nhân đôi).
 */
class ImportMenu extends Command
{
    protected $signature = 'menu:import
        {file? : Đường dẫn file CSV}
        {--email= : Email chủ nhà hàng}
        {--restaurant= : ID nhà hàng (thay cho --email)}
        {--sample : Xuất một file CSV mẫu rồi thoát}';

    protected $description = 'Nhập thực đơn (món + danh mục) từ CSV cho một nhà hàng.';

    public function handle(): int
    {
        if ($this->option('sample')) {
            $this->line('name,category,price,cost_price,description,sku');
            $this->line('Phở bò tái,Món nước,65000,28000,Phở bò truyền thống,PHO-TAI');
            $this->line('Cơm sườn,Cơm,55000,22000,,COM-SUON');
            $this->line('Trà chanh,Đồ uống,25000,8000,,');

            return self::SUCCESS;
        }

        $file = $this->argument('file');
        if (! $file || ! is_file($file)) {
            $this->error("Không tìm thấy file CSV: {$file}. Dùng --sample để xem mẫu.");

            return self::FAILURE;
        }

        $restaurant = $this->resolveRestaurant();
        if (! $restaurant) {
            return self::FAILURE;
        }
        $rid = $restaurant->id;
        $this->info("Nhà hàng: {$restaurant->name} (ID {$rid})");

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            $this->error('File CSV rỗng.');
            fclose($handle);

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $idx = array_flip($header);

        foreach (['name', 'category', 'price'] as $required) {
            if (! isset($idx[$required])) {
                $this->error("Thiếu cột bắt buộc: {$required}. Cột cần có: name, category, price.");
                fclose($handle);

                return self::FAILURE;
            }
        }

        $categories = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $name = trim((string) ($row[$idx['name']] ?? ''));
            $catName = trim((string) ($row[$idx['category']] ?? ''));
            $price = (float) preg_replace('/[^\d.]/', '', (string) ($row[$idx['price']] ?? '0'));

            if ($name === '' || $catName === '' || $price <= 0) {
                $skipped++;
                $this->warn("  Bỏ qua dòng {$line}: thiếu name/category/price hợp lệ.");

                continue;
            }

            $catSlug = Str::slug($catName) ?: 'khac';
            $categories[$catSlug] ??= ProductCategory::updateOrCreate(
                ['restaurant_id' => $rid, 'slug' => $catSlug],
                ['name' => $catName, 'status' => 'active']
            );
            $category = $categories[$catSlug];

            $cost = isset($idx['cost_price']) ? (float) preg_replace('/[^\d.]/', '', (string) ($row[$idx['cost_price']] ?? '0')) : 0;
            $desc = isset($idx['description']) ? trim((string) ($row[$idx['description']] ?? '')) : null;
            $sku = isset($idx['sku']) ? trim((string) ($row[$idx['sku']] ?? '')) : '';
            $code = $sku !== '' ? $sku : 'SP-'.strtoupper(Str::random(6));

            $existing = Product::where('restaurant_id', $rid)
                ->where('category_id', $category->id)
                ->where('name', $name)->first();

            Product::updateOrCreate(
                ['restaurant_id' => $rid, 'code' => $existing->code ?? $code],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                    'description' => $desc,
                    'price' => $price,
                    'cost_price' => $cost,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => false,
                ]
            );

            $existing ? $updated++ : $created++;
        }
        fclose($handle);

        $this->newLine();
        $this->info('✅ Xong.');
        $this->table(['Kết quả', 'Số lượng'], [
            ['Món tạo mới', $created],
            ['Món cập nhật', $updated],
            ['Dòng bỏ qua', $skipped],
            ['Danh mục dùng', count($categories)],
        ]);

        return self::SUCCESS;
    }

    private function resolveRestaurant()
    {
        if ($rid = $this->option('restaurant')) {
            $r = Restaurant::find($rid);
            if (! $r) {
                $this->error("Không tìm thấy nhà hàng ID {$rid}.");
            }

            return $r;
        }

        if ($email = $this->option('email')) {
            $owner = User::where('email', $email)->first();
            if (! $owner || ! $owner->restaurant) {
                $this->error("Không tìm thấy nhà hàng của {$email}.");

                return null;
            }

            return $owner->restaurant;
        }

        $this->error('Cần --email=<chủ quán> hoặc --restaurant=<id>.');

        return null;
    }
}
