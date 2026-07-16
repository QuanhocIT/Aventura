<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Coupon;
use App\Mail\ChurnEarlyWarningMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CustomerSuccessService
{
    /**
     * Calculate health score, risk level and reasons for a given restaurant.
     */
    public function calculateHealthScore(Restaurant $restaurant): array
    {
        // 1. Calculate Login Inactivity (Max 30 points deduction)
        $latestLogin = User::where('restaurant_id', $restaurant->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['owner', 'manager', 'cashier']);
            })
            ->max('last_login_at');

        if ($latestLogin) {
            $latestLoginTime = Carbon::parse($latestLogin);
            $daysSinceLogin = abs((int) now()->diffInDays($latestLoginTime));
        } else {
            // Fallback to restaurant creation date
            $daysSinceLogin = abs((int) now()->diffInDays($restaurant->created_at));
        }

        $loginDeduction = 0;
        if ($daysSinceLogin > 14) {
            $loginDeduction = 30;
        } elseif ($daysSinceLogin >= 8) {
            $loginDeduction = 20;
        } elseif ($daysSinceLogin >= 3) {
            $loginDeduction = 10;
        }

        // 2. Order Frequency Drop (Max 40 points deduction)
        // Current week (last 7 days)
        $currentWeekOrders = Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Previous 3 weeks (days 8 to 28)
        $prev3WeeksOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [now()->subDays(28), now()->subDays(7)])
            ->count();

        $prevWeeklyAvg = $prev3WeeksOrders / 3.0;
        $dropPercentage = 0.0;
        $orderDeduction = 0;

        if ($prevWeeklyAvg > 0) {
            $dropPercentage = (($prevWeeklyAvg - $currentWeekOrders) / $prevWeeklyAvg) * 100.0;
            if ($dropPercentage < 0) {
                $dropPercentage = 0.0;
            }

            if ($dropPercentage >= 50.0) {
                $orderDeduction = 40;
            } elseif ($dropPercentage >= 30.0) {
                $orderDeduction = 20;
            } elseif ($dropPercentage >= 10.0) {
                $orderDeduction = 10;
            }
        } else {
            // Previous 3 weeks had 0 orders
            if ($currentWeekOrders == 0) {
                // Completely inactive for 4 weeks
                $orderDeduction = 40;
                $dropPercentage = 100.0;
            } else {
                // New activity! Or no previous activity but has orders this week
                $orderDeduction = 0;
                $dropPercentage = 0.0;
            }
        }

        // 3. Unresolved support tickets (Max 30 points deduction)
        $unresolvedTickets = SupportTicket::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['open', 'in_progress', 'waiting_restaurant'])
            ->count();

        $ticketDeduction = 0;
        if ($unresolvedTickets >= 3) {
            $ticketDeduction = 30;
        } elseif ($unresolvedTickets == 2) {
            $ticketDeduction = 20;
        } elseif ($unresolvedTickets == 1) {
            $ticketDeduction = 10;
        }

        // Calculate Final Health Score (0 - 100)
        $healthScore = 100 - ($loginDeduction + $orderDeduction + $ticketDeduction);
        $healthScore = max(0, min(100, $healthScore));

        // Classify Risk Level
        if ($healthScore < 50) {
            $riskLevel = 'high'; // At-risk
        } elseif ($healthScore < 80) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }

        // Build list of reasons
        $reasons = [];
        if ($loginDeduction > 0) {
            $reasons[] = "Đã {$daysSinceLogin} ngày không có quản trị viên/thu ngân đăng nhập.";
        }
        if ($orderDeduction > 0) {
            if ($prevWeeklyAvg == 0 && $currentWeekOrders == 0) {
                $reasons[] = 'Không phát sinh bất kỳ đơn hàng nào trong 4 tuần gần đây.';
            } else {
                $reasons[] = 'Tần suất tạo đơn hàng giảm đột ngột ' . round($dropPercentage) . '% so với trung bình 3 tuần trước.';
            }
        }
        if ($ticketDeduction > 0) {
            $reasons[] = "Có {$unresolvedTickets} ticket hỗ trợ/khiếu nại chưa được giải quyết thỏa đáng.";
        }

        if (empty($reasons)) {
            $reasons[] = 'Mọi chỉ số vận hành đều đạt trạng thái tối ưu.';
        }

        return [
            'health_score' => $healthScore,
            'churn_risk_level' => $riskLevel,
            'churn_risk_reason' => implode(' | ', $reasons),
            'days_since_login' => $daysSinceLogin,
            'current_week_orders' => $currentWeekOrders,
            'prev_weekly_avg' => round($prevWeeklyAvg, 1),
            'unresolved_tickets' => $unresolvedTickets,
            'drop_percentage' => round($dropPercentage, 1),
        ];
    }

    /**
     * Recalculate health for all restaurants and trigger automated notifications.
     */
    public function recalculateAll(): array
    {
        $restaurants = Restaurant::with(['owner', 'plan'])
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        $results = [
            'total' => $restaurants->count(),
            'high_risk' => 0,
            'medium_risk' => 0,
            'low_risk' => 0,
            'emails_sent' => 0,
        ];

        // Ensure support coupon exists
        $coupon = Coupon::firstOrCreate(
            ['code' => 'AVENTURACARE30'],
            [
                'discount_type' => 'percent',
                'discount_value' => 30.00,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
                'max_uses' => null,
                'uses_count' => 0,
                'status' => 'active',
                'description' => 'Mã giảm giá tri ân và chăm sóc khách hàng có nguy cơ rời bỏ',
            ]
        );

        foreach ($restaurants as $restaurant) {
            $metrics = $this->calculateHealthScore($restaurant);

            $oldRiskLevel = $restaurant->churn_risk_level;

            $restaurant->health_score = $metrics['health_score'];
            $restaurant->churn_risk_level = $metrics['churn_risk_level'];
            $restaurant->churn_risk_reason = $metrics['churn_risk_reason'];
            $restaurant->last_health_checked_at = now();

            // Check for High Churn Risk Transition
            $triggered = false;
            if ($metrics['churn_risk_level'] === 'high' && $oldRiskLevel !== 'high') {
                if (is_null($restaurant->churn_risk_flagged_at)) {
                    $restaurant->churn_risk_flagged_at = now();
                    $triggered = true;
                }
            } elseif ($metrics['churn_risk_level'] !== 'high') {
                // Clear risk flagged at if no longer high risk
                $restaurant->churn_risk_flagged_at = null;
            }

            $restaurant->save();

            // Increment stats
            if ($metrics['churn_risk_level'] === 'high') {
                $results['high_risk']++;
            } elseif ($metrics['churn_risk_level'] === 'medium') {
                $results['medium_risk']++;
            } else {
                $results['low_risk']++;
            }

            // Trigger Outreach Email Automation
            if ($triggered) {
                $recipientEmail = $restaurant->owner?->email ?? $restaurant->email;
                $ownerName = $restaurant->owner?->name ?? 'Quý đối tác';

                if ($recipientEmail) {
                    try {
                        $reasonsList = explode(' | ', $metrics['churn_risk_reason']);
                        Mail::to($recipientEmail)->send(new ChurnEarlyWarningMail(
                            $restaurant->name,
                            $ownerName,
                            $coupon->code,
                            $reasonsList
                        ));
                        $results['emails_sent']++;
                    } catch (\Exception $e) {
                        Log::error("Failed to send churn early warning email to {$recipientEmail}: " . $e->getMessage());
                    }
                } else {
                    Log::warning("No owner email found for at-risk restaurant: {$restaurant->name} (Code: {$restaurant->code})");
                }

                // Tự động tạo Support Ticket cho bộ phận chăm sóc khách hàng
                try {
                    SupportTicket::create([
                        'restaurant_id' => $restaurant->id,
                        'title' => '[Tự động] Nhà hàng có nguy cơ rời bỏ cao - ' . $restaurant->name,
                        'description' => "Hệ thống phát hiện nhà hàng {$restaurant->name} ({$restaurant->code}) đang có nguy cơ rời bỏ cao (Health Score: {$restaurant->health_score}).\n\n" .
                                         "Lý do cảnh báo:\n- " . str_replace(' | ', "\n- ", $metrics['churn_risk_reason']) . "\n\n" .
                                         "Đề xuất hành động:\nCSKH liên hệ thăm hỏi tình hình vận hành và gửi mã coupon giảm giá 30% gia hạn dịch vụ: {$coupon->code}.",
                        'status' => 'open',
                        'priority' => 'high',
                        'category' => 'Retention',
                        'created_by' => 1, // System / Admin
                        'sla_due_at' => now()->addDays(2),
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to create automated support ticket for churn risk of restaurant ID {$restaurant->id}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }
}
