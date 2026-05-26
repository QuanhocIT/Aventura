<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        if (!$user) {
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
        if (!empty($data['step'])) {
            $dayKey = 'day_' . $status['current_day'];
            if (!isset($status[$dayKey])) {
                $status[$dayKey] = ['started_at' => now()->toIso8601String(), 'completed_at' => null, 'steps' => []];
            }
            if (!isset($status[$dayKey]['steps'])) {
                $status[$dayKey]['steps'] = [];
            }
            $status[$dayKey]['steps'][$data['step']] = !empty($data['completed']);

            // Nếu đây là bước đầu tiên của ngày mới, đánh dấu started_at
            if (empty($status[$dayKey]['started_at'])) {
                $status[$dayKey]['started_at'] = now()->toIso8601String();
            }
        }

        // Đánh dấu hoàn thành nguyên 1 ngày nếu có
        if (isset($data['completed_day'])) {
            $compDayKey = 'day_' . $data['completed_day'];
            if (isset($status[$compDayKey])) {
                $status[$compDayKey]['completed_at'] = now()->toIso8601String();
                
                // Mở khóa ngày kế tiếp
                $nextDay = $data['completed_day'] + 1;
                if ($nextDay <= 3) {
                    $status['current_day'] = $nextDay;
                    $nextDayKey = 'day_' . $nextDay;
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
        if (!$user) {
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
}
