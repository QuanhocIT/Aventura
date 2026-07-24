<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationValidationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Kiểm toán tự động tính cô lập dữ liệu.
     * Quét toàn bộ Models, nếu bảng tương ứng có cột 'restaurant_id' thì BẮT BUỘC 
     * Model đó phải sử dụng trait BelongsToRestaurant để tránh lỗi rò rỉ dữ liệu chéo giữa các Tenant.
     */
    public function test_all_tenant_models_must_use_belongs_to_restaurant_trait(): void
    {
        $modelsDir = app_path('Models');
        if (!File::isDirectory($modelsDir)) {
            $this->markTestSkipped('Thư mục app/Models không tồn tại.');
        }

        $files = File::allFiles($modelsDir);
        $checkedCount = 0;

        // Các Models ngoại lệ được cấu hình thủ công hoặc xử lý tách biệt (không dùng Trait BelongsToRestaurant trực tiếp)
        $exclusions = [
            \App\Models\User::class,                     // Được cô lập thông qua phiên đăng nhập auth() và context
            \App\Models\Restaurant::class,               // Bản thân Tenant root, không tự thuộc về chính nó
            \App\Models\AuditLog::class,                 // Bảng lịch sử kiểm toán của toàn hệ thống (Super Admin có thể xem chéo)
            \App\Models\BillingInvoice::class,           // Được quản lý và đối soát bởi Billing Service trung tâm
            \App\Models\BillingAdjustment::class,        // Hóa đơn/điều chỉnh billing trung tâm
            \App\Models\RestaurantSubscription::class,   // Gói đăng ký dịch vụ của nhà hàng
            \App\Models\PlatformFeedback::class,         // Đánh giá dịch vụ & nền tảng toàn hệ thống cho SuperAdmin
            \App\Models\MediaAsset::class,               // Polymorphic Asset đính kèm được xử lý bằng các mối quan hệ động
        ];

        foreach ($files as $file) {
            $relativePathname = $file->getRelativePathname();
            
            // Bỏ qua Concerns hoặc các thư mục Helper con bên trong app/Models
            if (str_starts_with($relativePathname, 'Concerns' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $className = 'App\\Models\\' . str_replace(['/', '.php'], ['\\', ''], $relativePathname);
            
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            
            // Chỉ kiểm tra các Class cụ thể (bỏ qua Abstract, Interface, Trait)
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            // Chỉ quan tâm các Eloquent Model kế thừa từ Model chính của Laravel
            if (!$reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            // Bỏ qua danh sách loại trừ
            if (in_array($className, $exclusions, true)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $modelInstance */
            $modelInstance = new $className();
            $tableName = $modelInstance->getTable();

            // Nếu bảng trong DB thực tế có cột 'restaurant_id'
            if (Schema::hasColumn($tableName, 'restaurant_id')) {
                $traits = array_keys($reflection->getTraits());
                $usesTrait = in_array(\App\Models\Concerns\BelongsToRestaurant::class, $traits, true);

                $this->assertTrue(
                    $usesTrait,
                    "CẢNH BÁO BẢO MẬT: Model [{$className}] có cột 'restaurant_id' trong bảng [{$tableName}] nhưng CHƯA sử dụng trait BelongsToRestaurant! Điều này dẫn tới nguy cơ rò rỉ dữ liệu chéo giữa các nhà hàng khách hàng."
                );
                $checkedCount++;
            }
        }

        $this->assertGreaterThan(0, $checkedCount, "Không tìm thấy bất kỳ Model Tenant nào để thực hiện kiểm toán.");
    }
}
