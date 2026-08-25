<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Chốt chặn kiến trúc: mọi route ghi dữ liệu phải có gate phân quyền.
 *
 * Cùng tinh thần với Tests\Unit\TenantSecurityScanTest — thay vì đi sửa từng
 * controller rồi vài tháng sau lại phát sinh chỗ mới, test này bắt lỗi ngay khi
 * route được thêm. Ba controller từng bị bỏ sót (ViolationReport, Customer,
 * MenuEngineering) đều sẽ bị test này chặn nếu ai đó lỡ gỡ gate ra.
 *
 * Muốn thêm một action vào danh sách miễn trừ thì phải ghi rõ LÝ DO ở
 * self-service map bên dưới — đó là điểm để review soi.
 */
class PermissionGateScanTest extends TestCase
{
    // gatherMiddleware() dựng middleware của controller, trong đó có nhánh tra
    // cứu bảng permissions — nên test này cần schema thật.
    use RefreshDatabase;

    /**
     * Action ghi dữ liệu nhưng chỉ tác động lên chính người đang đăng nhập,
     * hoặc lên bản ghi mà chủ sở hữu đã được kiểm bên trong service.
     *
     * @var array<string, string>
     */
    private array $selfServiceExemptions = [
        'AttendanceController@checkOut' => 'Nhân viên tự chấm công ra cho ca của chính mình.',
        'AttendanceController@requestCheckIn' => 'Nhân viên tự xin chấm công vào.',
        'AttendanceController@requestCheckOut' => 'Nhân viên tự xin chấm công ra.',
        'ScheduleController@register' => 'Nhân viên tự đăng ký ca rảnh của mình.',
        'ShiftSwapController@requestSwap' => 'Nhân viên tự đề nghị đổi ca của mình.',
        'EmployeePortalController@storeLeaveRequest' => 'Nhân viên tự nộp đơn nghỉ phép.',
        'EmployeePortalController@requestSwap' => 'Nhân viên tự đề nghị đổi ca.',
        'Settings\SecurityController@update' => 'Người dùng tự đổi mật khẩu của mình.',
        'Settings\SecurityController@updatePin' => 'Người dùng tự đổi mã PIN của mình.',
        'Settings\ProfileController@update' => 'Người dùng tự sửa hồ sơ của mình.',
        'Settings\ProfileController@updateDeviceToken' => 'Đăng ký token thiết bị nhận thông báo của chính mình.',
        'Settings\ReferralSettingsController@withdraw' => 'Rút hoa hồng của chính mình; số dư được khoá và kiểm trong transaction.',
        'SupportController@storeTicket' => 'Người dùng tự mở phiếu hỗ trợ.',
        'SupportController@storeBooking' => 'Người dùng tự đặt lịch hỗ trợ.',
        'PlatformFeedbackController@store' => 'Người dùng tự gửi góp ý về nền tảng.',
        'OnboardingController@updateProgress' => 'Tiến độ hướng dẫn của chính người dùng.',
        'OnboardingController@resetProgress' => 'Tiến độ hướng dẫn của chính người dùng.',
        'Delivery\ShipperPwaController@updateLocation' => 'Shipper tự báo vị trí của mình.',
        'Delivery\ShipperPwaController@updateLocationBatch' => 'Shipper tự báo vị trí của mình.',
        'Auth\GoogleController@handleGoogleCallback' => 'Luồng đăng nhập, chưa có phiên để phân quyền.',
        'Auth\TwoFactorEmailCodeController@store' => 'Luồng xác thực hai lớp của chính người dùng.',
        'Auth\VerifyEmailCodeController@store' => 'Luồng xác thực email của chính người dùng.',
        'Auth\EmailVerificationNotificationController@store' => 'Gửi lại email xác thực cho chính mình.',
        'Billing\CheckoutController@applyCoupon' => 'Áp mã giảm giá lên đơn gói cước của chính nhà hàng đang đăng nhập.',
    ];

    /** Từ khoá cho thấy route đã được gate ở tầng middleware. */
    private const GATE_MIDDLEWARE = '/role_or_permission|permission:|role:|can:/i';

    /** Từ khoá cho thấy gate nằm trong thân method. */
    private const GATE_CODE = '/abort_unless|abort_if|authorize\(|->can\(|Gate::|hasRole\(|isOwner\(|isSuperAdmin\(|\$this->(authorize|assert|ensure|guard|require)[A-Za-z]*\(/';

    /** Method có ghi dữ liệu. */
    private const WRITES = '/->(update|delete|forceDelete|save|increment|decrement|insert)\(|::create\(|::destroy\(|->create\(/';

    public function test_every_write_route_is_gated_or_explicitly_exempted(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_contains($action, '@') || ! str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }

            // Chỉ soi khu vực đã đăng nhập; route công khai được bảo vệ bằng
            // throttle/token đã ký và có bộ test riêng.
            $middleware = $route->gatherMiddleware();
            $middleware = array_map(fn ($m) => is_string($m) ? $m : 'Closure', $middleware);
            if (! $this->hasAuthMiddleware($middleware)) {
                continue;
            }

            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            [$class, $method] = explode('@', $action);

            // SuperAdmin có nhóm middleware riêng của nó.
            if (str_contains($class, '\\SuperAdmin\\')) {
                continue;
            }

            // Gate ở tầng route?
            foreach ($middleware as $m) {
                if (preg_match(self::GATE_MIDDLEWARE, $m)) {
                    continue 2;
                }
            }

            // Controller khai báo middleware qua HasMiddleware?
            if (is_a($class, HasMiddleware::class, true)) {
                continue;
            }

            $short = str_replace('App\\Http\\Controllers\\', '', $class).'@'.$method;

            if (isset($this->selfServiceExemptions[$short])) {
                continue;
            }

            $body = $this->methodBody($class, $method);
            if ($body === null) {
                continue;
            }

            if (! preg_match(self::WRITES, $body)) {
                continue;
            }

            if (preg_match(self::GATE_CODE, $body)) {
                continue;
            }

            $violations[$short] = sprintf(
                '%s  (%s /%s)',
                $short,
                implode('|', array_diff($route->methods(), ['HEAD'])),
                $route->uri(),
            );
        }

        $this->assertSame([], array_values($violations), sprintf(
            "Có %d action ghi dữ liệu không kiểm quyền ở cả method lẫn route.\n\n%s\n\n".
            "Cách xử lý:\n".
            "  1. Thêm abort_unless(...) dùng quyền đã có trong PermissionsSeeder, HOẶC\n".
            "  2. Thêm middleware phân quyền cho route, HOẶC\n".
            "  3. Nếu action chỉ tác động lên chính người đang đăng nhập, khai báo vào\n".
            "     \$selfServiceExemptions trong test này KÈM LÝ DO.\n",
            count($violations),
            implode("\n", array_map(fn ($v) => '  - '.$v, $violations)),
        ));
    }

    /** @param  list<string>  $middleware */
    private function hasAuthMiddleware(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (str_contains($m, 'Authenticate') || $m === 'auth' || str_starts_with($m, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    private function methodBody(string $class, string $method): ?string
    {
        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return null;
        }

        $file = $reflection->getFileName();
        if ($file === false || ! is_file($file)) {
            return null;
        }

        $lines = file($file);
        $start = $reflection->getStartLine() - 1;
        $length = $reflection->getEndLine() - $start;

        return implode('', array_slice($lines, $start, $length));
    }
}
