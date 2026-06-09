<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\CustomLoginResponse;
use App\Models\User;
use App\Services\Onboarding\RestaurantOnboardingService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function __construct(
        private readonly RestaurantOnboardingService $onboarding,
    ) {}

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $googleEmail = $googleUser->getEmail();

            if (! $googleEmail) {
                return redirect('/login')->withErrors(['msg' => 'Đăng nhập Google thất bại. Tài khoản Google không có email hợp lệ.']);
            }

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleEmail)
                ->first();

            if ($user) {
                if ($user->status !== 'active') {
                    return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ quản lý.']);
                }

                if (!$user->isSuperAdmin() && !$user->hasAnyRole(['owner', 'manager'])) {
                    $employee = $user->employee;
                    if (!$employee || !$employee->isWithinScheduledShift()) {
                        return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn chỉ được phép truy cập trong khung giờ ca làm việc được xếp.']);
                    }
                }

                // Tài khoản đã tồn tại — cập nhật google_id nếu chưa có
                $user->google_id     = $googleUser->getId();
                $user->last_login_at = now();

                if (! $user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();

                // Nếu tài khoản cũ chưa có restaurant (đã đăng nhập Google trước khi onboarding),
                // tự động khởi tạo nhà hàng với thông tin từ Google profile.
                if (! $user->restaurant_id) {
                    $user = $this->autoOnboardGoogleUser($user, $googleUser->getName() ?: $googleEmail);
                }
            } else {
                // Tài khoản mới qua Google → chạy onboarding tự động ngay lập tức
                // Tên nhà hàng tạm dùng tên Google, owner có thể đổi sau trong cài đặt.
                $user = $this->onboarding->onboard([
                    'name'            => $googleUser->getName() ?: $googleEmail,
                    'restaurant_name' => ($googleUser->getName() ?: $googleEmail) . ' Restaurant',
                    'email'           => $googleEmail,
                    'password'        => Str::random(40), // mật khẩu ngẫu nhiên, đăng nhập bằng Google
                    'phone'           => null,
                    'plan_code'       => 'free',
                ]);

                // Gắn google_id sau khi onboard (onboard tạo user mới)
                $user->forceFill([
                    'google_id'    => $googleUser->getId(),
                    'last_login_at' => now(),
                ])->save();
            }

            Auth::login($user, true);

            return CustomLoginResponse::redirectForUser($user);
        } catch (Exception $exception) {
            Log::error('Google login failed', [
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ]);
            return redirect('/login')->withErrors(['msg' => 'Đăng nhập Google thất bại. Vui lòng thử lại.']);
        }
    }

    /**
     * Chạy onboarding cho tài khoản Google cũ chưa có restaurant.
     * Dùng updateOrCreate-safe: tạo restaurant mới và gán role owner.
     */
    private function autoOnboardGoogleUser(User $user, string $displayName): User
    {
        try {
            // Tái dùng pipeline onboarding nhưng với email đã tồn tại → cần tạo restaurant riêng.
            // Vì onboard() tạo User mới, ta gọi trực tiếp seedDefaults thông qua service helper.
            // Đơn giản nhất: gọi onboard với email placeholder rồi merge user.
            $tempUser = $this->onboarding->onboard([
                'name'            => $displayName,
                'restaurant_name' => $displayName . ' Restaurant',
                'email'           => 'google_temp_' . $user->id . '_' . Str::random(6) . '@temp.local',
                'password'        => Str::random(40),
                'phone'           => null,
                'plan_code'       => 'free',
            ]);

            // Chuyển restaurant sang user gốc và xóa user tạm
            $restaurantId = $tempUser->restaurant_id;

            // Cập nhật owner_user_id trên restaurant về user gốc
            $tempUser->restaurant()->update(['owner_user_id' => $user->id]);

            $user->forceFill(['restaurant_id' => $restaurantId])->save();
            $user->assignRole('owner');

            // Xóa user tạm
            $tempUser->syncRoles([]);
            $tempUser->delete();

            return $user->fresh();
        } catch (\Throwable $e) {
            Log::warning('autoOnboardGoogleUser failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return $user;
        }
    }
}
