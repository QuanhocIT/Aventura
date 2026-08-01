<?php

namespace App\Http\Controllers;

use App\Services\Onboarding\DemoDataSeederService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Cập nhật tiến trình Guided Tours.
     */
    public function updateProgress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_day' => ['required', 'integer', 'min:1', 'max:3'],
            'step' => ['nullable', 'string', 'max:100'],
            'completed' => ['nullable', 'boolean'],
            'completed_day' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $user = $request->user();
        if (! $user) {
            return back()->with('error', 'Chưa đăng nhập.');
        }

        $status = $user->onboarding_status ?? [
            'current_day' => 1,
            'day_1' => ['started_at' => now()->toIso8601String(), 'completed_at' => null, 'steps' => []],
            'day_2' => ['started_at' => null, 'completed_at' => null, 'steps' => []],
            'day_3' => ['started_at' => null, 'completed_at' => null, 'steps' => []],
        ];

        // Cập nhật ngày hiện tại đang xem/học
        $status['current_day'] = (int) $data['current_day'];

        // Cập nhật bước cụ thể nếu có
        if (! empty($data['step'])) {
            $dayKey = 'day_'.$status['current_day'];
            if (! isset($status[$dayKey])) {
                $status[$dayKey] = ['started_at' => now()->toIso8601String(), 'completed_at' => null, 'steps' => []];
            }
            if (! isset($status[$dayKey]['steps'])) {
                $status[$dayKey]['steps'] = [];
            }
            $status[$dayKey]['steps'][$data['step']] = ! empty($data['completed']);

            // Nếu đây là bước đầu tiên của ngày mới, đánh dấu started_at
            if (empty($status[$dayKey]['started_at'])) {
                $status[$dayKey]['started_at'] = now()->toIso8601String();
            }
        }

        // Đánh dấu hoàn thành nguyên 1 ngày nếu có
        if (isset($data['completed_day'])) {
            $compDayKey = 'day_'.$data['completed_day'];
            if (isset($status[$compDayKey])) {
                $status[$compDayKey]['completed_at'] = now()->toIso8601String();

                // Mở khóa ngày kế tiếp
                $nextDay = $data['completed_day'] + 1;
                if ($nextDay <= 3) {
                    $status['current_day'] = $nextDay;
                    $nextDayKey = 'day_'.$nextDay;
                    if (empty($status[$nextDayKey]['started_at'])) {
                        $status[$nextDayKey]['started_at'] = now()->toIso8601String();
                    }
                }
            }
        }

        $user->forceFill(['onboarding_status' => $status])->save();

        return back()->with('success', 'Đã cập nhật tiến trình hướng dẫn.');
    }

    /**
     * Reset toàn bộ tiến độ Onboarding.
     */
    public function resetProgress(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return back()->with('error', 'Chưa đăng nhập.');
        }

        $status = [
            'current_day' => 1,
            'day_1' => ['started_at' => now()->toIso8601String(), 'completed_at' => null, 'steps' => []],
            'day_2' => ['started_at' => null, 'completed_at' => null, 'steps' => []],
            'day_3' => ['started_at' => null, 'completed_at' => null, 'steps' => []],
        ];

        $user->forceFill(['onboarding_status' => $status])->save();

        return back()->with('success', 'Đã thiết lập lại hệ thống hướng dẫn tương tác.');
    }

    // ─────────────────────────────────────────
    //  Sandbox & Demo Data
    // ─────────────────────────────────────────

    /**
     * Trang cài đặt Sandbox Mode.
     */
    public function sandboxPage(Request $request): Response
    {
        $restaurant = $request->user()?->restaurant;

        return Inertia::render('settings/Sandbox', [
            'sandbox' => $restaurant ? [
                'sandbox_mode' => (bool) $restaurant->sandbox_mode,
                'sandbox_template' => $restaurant->sandbox_template,
                'sandbox_seeded_at' => $restaurant->sandbox_seeded_at?->toIso8601String(),
            ] : null,
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Bật / tắt Sandbox Mode.
     */
    public function toggleSandbox(Request $request): RedirectResponse
    {
        $restaurant = $request->user()?->restaurant;
        if (! $restaurant) {
            return back()->with('error', 'Không tìm thấy thông tin nhà hàng.');
        }

        $restaurant->forceFill([
            'sandbox_mode' => ! $restaurant->sandbox_mode,
        ])->save();

        $state = $restaurant->sandbox_mode ? 'bật' : 'tắt';

        return back()->with('status', "Đã {$state} Chế độ Thử nghiệm.");
    }

    /**
     * Nạp dữ liệu mẫu theo template được chọn.
     */
    public function seedDemo(Request $request, DemoDataSeederService $seeder): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string', 'in:bbq,cafe,bubble_tea'],
        ]);

        $restaurant = $request->user()?->restaurant;
        if (! $restaurant) {
            return back()->with('error', 'Không tìm thấy thông tin nhà hàng.');
        }

        if ($restaurant->sandbox_seeded_at) {
            return back()->with('error', 'Đã có dữ liệu mẫu. Vui lòng xóa dữ liệu cũ trước khi nạp mới.');
        }

        try {
            $seeder->seed($restaurant, $data['template']);
        } catch (\Throwable $e) {
            Log::error('DemoDataSeeder failed', [
                'error' => $e->getMessage(),
                'restaurant_id' => $restaurant->id,
            ]);

            return back()->with('error', 'Có lỗi xảy ra khi nạp dữ liệu mẫu: '.$e->getMessage());
        }

        $templateNames = [
            'bbq' => 'Nhà hàng Nướng 🔥',
            'cafe' => 'Quán Cafe ☕',
            'bubble_tea' => 'Tiệm Trà sữa 🧋',
        ];

        return back()->with('status', '✅ Đã nạp dữ liệu mẫu "'.$templateNames[$data['template']].'". Hãy khám phá Dashboard và màn hình POS nhé!');
    }

    /**
     * Xóa toàn bộ dữ liệu mẫu — đưa hệ thống về trạng thái Production.
     */
    public function resetDemo(Request $request, DemoDataSeederService $seeder): RedirectResponse
    {
        $restaurant = $request->user()?->restaurant;
        if (! $restaurant) {
            return back()->with('error', 'Không tìm thấy thông tin nhà hàng.');
        }

        try {
            $seeder->reset($restaurant);
        } catch (\Throwable $e) {
            Log::error('DemoDataSeeder reset failed', [
                'error' => $e->getMessage(),
                'restaurant_id' => $restaurant->id,
            ]);

            return back()->with('error', 'Có lỗi xảy ra khi xóa dữ liệu mẫu: '.$e->getMessage());
        }

        return back()->with('status', '🗑️ Đã xóa toàn bộ dữ liệu mẫu. Hệ thống sẵn sàng cho môi trường thực tế.');
    }
}
