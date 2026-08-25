<?php

namespace Tests;

use App\Http\Middleware\SetTenantContext;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(PermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        SetTenantContext::$enforceShiftLockInTests = false;
        parent::tearDown();
    }

    /**
     * Bỏ qua test khi tính năng Fortify tương ứng đang tắt.
     *
     * Các test trong tests/Feature/Auth và tests/Feature/Settings đều gọi hàm
     * này nhưng nó chưa từng được định nghĩa ở đâu — Fortify cũng không cung cấp.
     * Hậu quả: 23 test xác thực báo lỗi "Call to undefined method" thay vì thực
     * sự chạy, nên lỗi hồi quy ở luồng đăng nhập, đặt lại mật khẩu và xác thực
     * email sẽ không ai phát hiện.
     *
     * @param  string  ...$features  giá trị trả về của Features::xxx()
     */
    protected function skipUnlessFortifyHas(string ...$features): void
    {
        foreach ($features as $feature) {
            if (! Features::enabled($feature)) {
                $this->markTestSkipped("Tính năng Fortify [{$feature}] đang tắt.");
            }
        }
    }
}
