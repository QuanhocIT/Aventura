<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    /**
     * Hiển thị bảng chấm công và lịch xếp ca.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');

        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature'       => 'hr_timekeeping',
                'feature_label' => 'Chấm công & Lịch làm việc',
                'plan_name'     => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        // 1. Nếu là Chủ hoặc Quản lý: Xem toàn cục
        if ($user->hasAnyRole(['owner', 'manager'])) {
            $selectedDate = $request->input('date', today()->toDateString());
            
            // Lấy danh sách lịch xếp ca trong ngày được chọn
            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereDate('scheduled_date', $selectedDate)
                ->with(['employee:id,full_name,employee_code,job_title', 'shift'])
                ->get()
                ->map(function ($a) {
                    $duration = null;
                    if ($a->check_in_at) {
                        $end = $a->check_out_at ? Carbon::parse($a->check_out_at) : now();
                        $diff = Carbon::parse($a->check_in_at)->diff($end);
                        $duration = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);
                    }

                    return [
                        'id' => $a->id,
                        'employee_id' => $a->employee_id,
                        'employee_name' => $a->employee?->full_name ?? 'Không rõ',
                        'employee_code' => $a->employee?->employee_code ?? '—',
                        'job_title' => $a->employee?->job_title ?? '—',
                        'shift_id' => $a->shift_id,
                        'shift_name' => $a->shift?->name ?? '—',
                        'shift_time' => $a->shift ? substr($a->shift->start_time, 0, 5) . ' - ' . substr($a->shift->end_time, 0, 5) : '—',
                        'scheduled_date' => $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString(),
                        'check_in_at' => $a->check_in_at ? Carbon::parse($a->check_in_at)->format('H:i:s d/m/Y') : null,
                        'check_out_at' => $a->check_out_at ? Carbon::parse($a->check_out_at)->format('H:i:s d/m/Y') : null,
                        'status' => $a->status,
                        'is_shift_leader' => (bool) $a->is_shift_leader,
                        'duration' => $duration,
                        'notes' => $a->notes,
                        'check_in_photo_path' => $a->check_in_photo_path ? asset('storage/' . $a->check_in_photo_path) : null,
                    ];
                });

            // Tổng hợp thống kê chấm công hôm nay
            $stats = [
                'scheduled' => $assignments->where('status', 'scheduled')->count(),
                'working' => $assignments->where('status', 'checked_in')->count(),
                'completed' => $assignments->where('status', 'completed')->count(),
                'absent' => $assignments->where('status', 'absent')->count(),
                'leave' => $assignments->where('status', 'leave_approved')->count(),
                'total' => $assignments->count(),
            ];

            // Roster tuần này của toàn hệ thống
            $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
            $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
            $weeklyAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                ->with(['employee:id,full_name', 'shift:id,name'])
                ->get()
                ->map(fn ($wa) => [
                    'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                    'employee_name' => $wa->employee?->full_name ?? 'Không rõ',
                    'shift_name' => $wa->shift?->name ? explode(' (', $wa->shift->name)[0] : 'Ca Mới',
                ]);

            $shifts = WorkShift::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'start' => substr($s->start_time, 0, 5),
                    'end' => substr($s->end_time, 0, 5),
                ]);

            $employees = Employee::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->get(['id', 'full_name', 'job_title', 'employee_code']);

            // ── AI Staffing Suggestions dựa trên peak hours ──────────────────
            $peakHours = \Illuminate\Support\Facades\DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->selectRaw('HOUR(completed_at) as hour, COUNT(*) as order_count, SUM(total_amount) as revenue')
                ->groupBy(\Illuminate\Support\Facades\DB::raw('HOUR(completed_at)'))
                ->orderByDesc('revenue')
                ->get();

            $totalRevenuePeak = $peakHours->sum('revenue');
            $staffingTips = [];

            if ($peakHours->count() && $totalRevenuePeak > 0) {
                foreach ($shifts as $shift) {
                    // Xác định giờ trong ca
                    $startH = (int) substr($shift['start'], 0, 2);
                    $endH   = (int) substr($shift['end'],   0, 2);
                    if ($endH <= $startH) $endH += 24; // overnight

                    $shiftRevenue = $peakHours
                        ->filter(fn ($r) => $r->hour >= $startH && $r->hour < $endH)
                        ->sum('revenue');
                    $pct = round($shiftRevenue / $totalRevenuePeak * 100, 1);

                    $currentStaff = $assignments
                        ->where('shift_name', $shift['name'])
                        ->whereIn('status', ['scheduled', 'checked_in'])
                        ->count();

                    if ($pct >= 35 && $currentStaff < 3) {
                        $staffingTips[] = [
                            'shift'   => $shift['name'],
                            'pct'     => $pct,
                            'message' => "Ca <strong>{$shift['name']}</strong> chiếm {$pct}% doanh thu — hiện chỉ có {$currentStaff} nhân viên. Nên bố trí ít nhất 3 người.",
                            'level'   => 'warning',
                        ];
                    } elseif ($pct < 10 && $currentStaff > 2) {
                        $staffingTips[] = [
                            'shift'   => $shift['name'],
                            'pct'     => $pct,
                            'message' => "Ca <strong>{$shift['name']}</strong> chỉ chiếm {$pct}% doanh thu — {$currentStaff} nhân viên có thể hơi nhiều. Cân nhắc tối ưu chi phí lương.",
                            'level'   => 'info',
                        ];
                    }
                }
            }

            $registrations = ScheduleRegistration::where('restaurant_id', $restaurantId)
                ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                ->with(['employee:id,full_name,employee_code,job_title', 'shift:id,name'])
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'employee_id' => $r->employee_id,
                    'employee_name' => $r->employee?->full_name ?? 'Không rõ',
                    'employee_code' => $r->employee?->employee_code ?? '—',
                    'job_title' => $r->employee?->job_title ?? '—',
                    'shift_id' => $r->shift_id,
                    'shift_name' => $r->shift?->name ?? '—',
                    'scheduled_date' => $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString(),
                    'day' => Carbon::parse($r->scheduled_date)->format('l'),
                ]);

            $allPendingSwaps = ShiftSwap::where('restaurant_id', $restaurantId)
                ->where('status', 'accepted')
                ->with([
                    'requesterAssignment.employee:id,full_name,employee_code',
                    'requesterAssignment.shift:id,name,start_time,end_time',
                    'receiverAssignment.employee:id,full_name,employee_code',
                    'receiverAssignment.shift:id,name,start_time,end_time'
                ])
                ->get()
                ->map(fn ($sw) => [
                    'id' => $sw->id,
                    'status' => $sw->status,
                    'notes' => $sw->notes,
                    'requester_name' => $sw->requesterAssignment?->employee?->full_name ?? '—',
                    'requester_shift' => $sw->requesterAssignment?->shift?->name ?? '—',
                    'requester_date' => $sw->requesterAssignment?->scheduled_date instanceof Carbon ? $sw->requesterAssignment->scheduled_date->toDateString() : Carbon::parse($sw->requesterAssignment?->scheduled_date)->toDateString(),
                    'receiver_name' => $sw->receiverAssignment?->employee?->full_name ?? '—',
                    'receiver_shift' => $sw->receiverAssignment?->shift?->name ?? '—',
                    'receiver_date' => $sw->receiverAssignment?->scheduled_date instanceof Carbon ? $sw->receiverAssignment->scheduled_date->toDateString() : Carbon::parse($sw->receiverAssignment?->scheduled_date)->toDateString(),
                ]);

            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

            $monthlyAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereBetween('scheduled_date', [$startOfMonth, $endOfMonth])
                ->with(['employee:id,full_name,employee_code,job_title,pay_rate,compensation_type,base_salary', 'shift'])
                ->get()
                ->map(function ($a) use ($restaurant) {
                    $durationHours = 0;
                    if ($a->check_in_at && $a->check_out_at) {
                        $diffInSeconds = Carbon::parse($a->check_in_at)->diffInSeconds(Carbon::parse($a->check_out_at));
                        $durationHours = round($diffInSeconds / 3600.0, 2);
                    }

                    $lateMin = null;
                    if ($a->check_in_at && $a->shift) {
                        $shiftStart = Carbon::parse(($a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString()) . ' ' . $a->shift->start_time);
                        $graceEnd = $shiftStart->copy()->addMinutes($restaurant?->grace_period_minutes ?? 10);
                        $checkIn = Carbon::parse($a->check_in_at);
                        if ($checkIn->greaterThan($graceEnd)) {
                            $lateMin = round($checkIn->diffInMinutes($graceEnd));
                        }
                    }

                    return [
                        'id' => $a->id,
                        'employee_id' => $a->employee_id,
                        'employee_name' => $a->employee?->full_name ?? 'Không rõ',
                        'employee_code' => $a->employee?->employee_code ?? '—',
                        'job_title' => $a->employee?->job_title ?? '—',
                        'compensation_type' => $a->employee?->compensation_type ?? 'fixed',
                        'pay_rate' => (float) ($a->employee?->pay_rate ?? 0),
                        'base_salary' => (float) ($a->employee?->base_salary ?? 0),
                        'scheduled_date' => $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString(),
                        'status' => $a->status,
                        'duration_hours' => $durationHours,
                        'late_minutes' => $lateMin,
                        'shift_id' => $a->shift_id,
                        'shift_name' => $a->shift?->name ?? '—',
                    ];
                });

            return Inertia::render('schedules/Index', [
                'isAdmin'          => true,
                'selectedDate'     => $selectedDate,
                'assignments'      => $assignments,
                'stats'            => $stats,
                'weeklyAssignments' => $weeklyAssignments,
                'shifts'           => $shifts,
                'employees'        => $employees,
                'staffingTips'     => $staffingTips,
                'registrations'    => $registrations,
                'allPendingSwaps'  => $allPendingSwaps,
                'monthlyAssignments' => $monthlyAssignments,
                'gpsSettings'      => [
                    'latitude'  => $restaurant?->latitude,
                    'longitude' => $restaurant?->longitude,
                    'radius'    => $restaurant?->checkin_radius_meters ?? 100,
                ],
                'qrSettings'       => [
                    'code'       => $restaurant?->qr_checkin_code,
                    'expires_at' => $restaurant?->qr_checkin_expires_at ? $restaurant->qr_checkin_expires_at->toDateTimeString() : null,
                    'is_expired' => $restaurant?->qr_checkin_expires_at ? now()->greaterThan($restaurant->qr_checkin_expires_at) : true,
                ],
                'restaurantSettings' => [
                    'grace_period_minutes' => $restaurant?->grace_period_minutes ?? 10,
                    'ot_multiplier'        => (float) ($restaurant?->ot_multiplier ?? 1.50),
                ],
            ]);
        }

        // 2. Nếu là Nhân viên thường: Bảng chấm công cá nhân
        $employee = $user->employee;
        if (!$employee) {
            abort(403, 'Bạn chưa được liên kết với hồ sơ nhân sự nào trên hệ thống.');
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        // Lấy lịch xếp ca tuần này của cá nhân
        $myWeeklySchedules = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with('shift')
            ->orderBy('scheduled_date')
            ->get()
            ->map(function ($wa) {
                return [
                    'id' => $wa->id,
                    'date' => Carbon::parse($wa->scheduled_date)->format('d/m/Y'),
                    'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                    'day_vn' => $this->getDayVn(Carbon::parse($wa->scheduled_date)->format('l')),
                    'shift_name' => $wa->shift?->name ?? '—',
                    'shift_time' => $wa->shift ? substr($wa->shift->start_time, 0, 5) . ' - ' . substr($wa->shift->end_time, 0, 5) : '—',
                    'check_in_at' => $wa->check_in_at ? Carbon::parse($wa->check_in_at)->format('H:i:s') : null,
                    'check_out_at' => $wa->check_out_at ? Carbon::parse($wa->check_out_at)->format('H:i:s') : null,
                    'status' => $wa->status,
                    'check_in_photo_path' => $wa->check_in_photo_path ? asset('storage/' . $wa->check_in_photo_path) : null,
                ];
            });

        // Tìm ca trực hiện tại khả dụng để check-in hoặc check-out ngày hôm nay
        $todayActiveAssignment = null;
        $now = now();

        // 1. Kiểm tra xem có ca đang checked_in hay không
        $checkedInAssignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->with('shift')
            ->first();

        if ($checkedInAssignment) {
            $diff = Carbon::parse($checkedInAssignment->check_in_at)->diff($now);
            $durationStr = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);

            $todayActiveAssignment = [
                'id' => $checkedInAssignment->id,
                'shift_name' => $checkedInAssignment->shift?->name ?? '—',
                'shift_time' => $checkedInAssignment->shift ? substr($checkedInAssignment->shift->start_time, 0, 5) . ' - ' . substr($checkedInAssignment->shift->end_time, 0, 5) : '—',
                'check_in_at' => Carbon::parse($checkedInAssignment->check_in_at)->format('H:i:s d/m/Y'),
                'status' => $checkedInAssignment->status,
                'duration' => $durationStr,
                'can_check_in' => false,
                'can_check_out' => true,
            ];
        } else {
            // 2. Tìm ca scheduled hôm nay trong khung giờ cho phép check-in
            $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'scheduled')
                ->with('shift')
                ->get();

            foreach ($scheduledAssignments as $sa) {
                $shift = $sa->shift;
                if (!$shift || $shift->status !== 'active') {
                    continue;
                }

                $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();
                $start = Carbon::parse($dateStr . ' ' . $shift->start_time);
                
                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $end = Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
                } else {
                    $end = Carbon::parse($dateStr . ' ' . $shift->end_time);
                }

                // Khung check-in: Từ 30 phút trước ca trực cho đến khi hết ca trực
                $allowedStart = $start->copy()->subMinutes(30);
                $allowedEnd = $end;

                if ($now->between($allowedStart, $allowedEnd)) {
                    $todayActiveAssignment = [
                        'id' => $sa->id,
                        'shift_name' => $shift->name,
                        'shift_time' => substr($shift->start_time, 0, 5) . ' - ' . substr($shift->end_time, 0, 5),
                        'check_in_at' => null,
                        'status' => $sa->status,
                        'duration' => null,
                        'can_check_in' => true,
                        'can_check_out' => false,
                    ];
                    break;
                }
            }
        }

        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start' => substr($s->start_time, 0, 5),
                'end' => substr($s->end_time, 0, 5),
            ]);

        $myRegistrations = ScheduleRegistration::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get()
            ->map(fn ($r) => [
                'shift_id' => $r->shift_id,
                'date' => $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString(),
            ]);

        $myAssignmentIds = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->pluck('id');

        $pendingSwapRequests = ShiftSwap::where('restaurant_id', $restaurantId)
            ->where(function ($q) use ($myAssignmentIds) {
                $q->whereIn('requester_assignment_id', $myAssignmentIds)
                  ->orWhereIn('receiver_assignment_id', $myAssignmentIds);
            })
            ->with([
                'requesterAssignment.employee:id,full_name,employee_code',
                'requesterAssignment.shift:id,name,start_time,end_time',
                'receiverAssignment.employee:id,full_name,employee_code',
                'receiverAssignment.shift:id,name,start_time,end_time'
            ])
            ->get()
            ->map(fn ($sw) => [
                'id' => $sw->id,
                'status' => $sw->status,
                'notes' => $sw->notes,
                'is_requester' => in_array($sw->requester_assignment_id, $myAssignmentIds->toArray()),
                'requester_name' => $sw->requesterAssignment?->employee?->full_name ?? '—',
                'requester_shift' => $sw->requesterAssignment?->shift?->name ?? '—',
                'requester_date' => $sw->requesterAssignment?->scheduled_date instanceof Carbon ? $sw->requesterAssignment->scheduled_date->toDateString() : Carbon::parse($sw->requesterAssignment?->scheduled_date)->toDateString(),
                'receiver_name' => $sw->receiverAssignment?->employee?->full_name ?? '—',
                'receiver_shift' => $sw->receiverAssignment?->shift?->name ?? '—',
                'receiver_date' => $sw->receiverAssignment?->scheduled_date instanceof Carbon ? $sw->receiverAssignment->scheduled_date->toDateString() : Carbon::parse($sw->receiverAssignment?->scheduled_date)->toDateString(),
            ]);

        $restaurant = \App\Models\Restaurant::find($restaurantId);

        $weeklyAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee:id,full_name,employee_code', 'shift:id,name,start_time,end_time'])
            ->get()
            ->map(fn ($wa) => [
                'id' => $wa->id,
                'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                'date' => Carbon::parse($wa->scheduled_date)->format('d/m/Y'),
                'employee_id' => $wa->employee_id,
                'employee_name' => $wa->employee?->full_name ?? 'Không rõ',
                'employee_code' => $wa->employee?->employee_code ?? '—',
                'shift_id' => $wa->shift_id,
                'shift_name' => $wa->shift?->name ?? '—',
                'shift_time' => $wa->shift ? substr($wa->shift->start_time, 0, 5) . ' - ' . substr($wa->shift->end_time, 0, 5) : '—',
            ]);

        return Inertia::render('schedules/Index', [
            'isAdmin' => false,
            'myWeeklySchedules' => $myWeeklySchedules,
            'todayActiveAssignment' => $todayActiveAssignment,
            'shifts' => $shifts,
            'myRegistrations' => $myRegistrations,
            'pendingSwapRequests' => $pendingSwapRequests,
            'weeklyAssignments' => $weeklyAssignments,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ],
            'gpsSettings' => [
                'latitude' => $restaurant?->latitude,
                'longitude' => $restaurant?->longitude,
                'radius' => $restaurant?->checkin_radius_meters ?? 100,
            ],
            'qrSettings' => [
                'code' => $restaurant?->qr_checkin_code,
                'expires_at' => $restaurant?->qr_checkin_expires_at ? $restaurant->qr_checkin_expires_at->toDateTimeString() : null,
                'is_expired' => $restaurant?->qr_checkin_expires_at ? now()->greaterThan($restaurant->qr_checkin_expires_at) : true,
            ],
        ]);
    }

    /**
     * Nhân viên tự Check-in vào ca.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $restaurant = $employee->restaurant;

        // 1. Geolocation (GPS) Validation
        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
            $clientLat = $request->input('latitude');
            $clientLng = $request->input('longitude');

            if (is_null($clientLat) || is_null($clientLng)) {
                return back()->withErrors(['email' => 'Check-in thất bại: Vui lòng bật vị trí (GPS) và cấp quyền truy cập để chấm công.']);
            }

            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($restaurant->latitude);
            $lonFrom = deg2rad($restaurant->longitude);
            $latTo = deg2rad(floatval($clientLat));
            $lonTo = deg2rad(floatval($clientLng));

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;
            
            $allowedRadius = $restaurant->checkin_radius_meters ?? 100;
            if ($distance > $allowedRadius) {
                return back()->withErrors(['email' => 'Check-in thất bại: Bạn đang ở cách xa nhà hàng (' . round($distance) . 'm). Khoảng cách cho phép là dưới ' . $allowedRadius . 'm.']);
            }
        }

        // 2. QR Code Validation
        if ($restaurant && $restaurant->qr_checkin_code) {
            if ($restaurant->qr_checkin_expires_at && now()->greaterThan($restaurant->qr_checkin_expires_at)) {
                return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công trong ngày đã hết hạn. Hãy yêu cầu quản lý tạo mã QR mới.']);
            }

            $clientQR = $request->input('qr_code');
            if (empty($clientQR) || $clientQR !== $restaurant->qr_checkin_code) {
                return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công không hợp lệ hoặc không khớp.']);
            }
        }

        // Tìm ca được xếp có hiệu lực hiện tại
        $sa = null;
        $now = now();

        $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->with('shift')
            ->get();

        foreach ($scheduledAssignments as $saCandidate) {
            $shift = $saCandidate->shift;
            if (!$shift || $shift->status !== 'active') {
                continue;
            }

            $dateStr = $saCandidate->scheduled_date instanceof Carbon ? $saCandidate->scheduled_date->toDateString() : Carbon::parse($saCandidate->scheduled_date)->toDateString();
            $start = Carbon::parse($dateStr . ' ' . $shift->start_time);
            
            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $end = Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
            } else {
                $end = Carbon::parse($dateStr . ' ' . $shift->end_time);
            }

            $allowedStart = $start->copy()->subMinutes(30);
            $allowedEnd = $end;

            if ($now->between($allowedStart, $allowedEnd)) {
                $sa = $saCandidate;
                break;
            }
        }

        if (!$sa) {
            return back()->withErrors(['email' => 'Hiện tại bạn không có ca trực nào được xếp hoặc chưa đến giờ check-in cho phép.']);
        }

        $photo = $request->input('check_in_photo');
        $photoPath = null;
        if ($photo && preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
            $type = strtolower($matches[1]);
            $data = substr($photo, strpos($photo, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $filename = 'checkin_' . $employee->id . '_' . time() . '_' . Str::random(5) . '.' . $type;
                $photoPath = 'checkins/' . $filename;
                Storage::disk('public')->put($photoPath, $data);
            }
        }

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'check_in_photo_path' => $photoPath,
        ]);

        return back()->with('success', 'Bạn đã CHECK-IN thành công ca trực "' . $sa->shift->name . '". Chúc bạn một ca làm việc vui vẻ!');
    }

    /**
     * Nhân viên tự Check-out khỏi ca.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $sa = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->first();

        if (!$sa) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.']);
        }

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        return back()->with('success', 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!');
    }

    /**
     * Quản lý/Owner Check-in hộ nhân viên.
     */
    public function checkInEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-in hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Đi trễ / Vấn đề vào ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-in hộ kèm vi phạm vào ca');
        }

        return back()->with('success', 'Đã ghi nhận Check-in hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Check-out hộ nhân viên.
     */
    public function checkOutEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-out hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Về sớm / Vấn đề ra ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-out hộ kèm vi phạm ra ca');
        }

        return back()->with('success', 'Đã ghi nhận Check-out hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Báo vắng (Absent) nhân viên.
     */
    public function markAbsentEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'status' => 'absent',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Vắng mặt không lý do',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Vắng mặt', $data['violation_notes'] ?? $data['notes'] ?? 'Báo vắng trực không lý do');
        }

        return back()->with('success', 'Đã ghi nhận báo vắng thành công cho nhân viên.');
    }

    /**
     * Tự động tạo Biên bản vi phạm kỷ luật khi có tuỳ chọn kèm theo.
     */
    private function createAutoViolation(Request $request, ScheduleAssignment $sa, string $violationType, string $description): void
    {
        $user = $request->user();
        $penaltyAmount = (float) ($request->input('penalty_amount') ?? 0);

        \App\Models\ViolationReport::create([
            'restaurant_id'  => $sa->restaurant_id,
            'branch_id'      => $sa->branch_id,
            'employee_id'    => $sa->employee_id,
            'reported_by'    => $user->id,
            'violation_type' => $violationType,
            'severity'       => 'low',
            'description'    => $description,
            'penalty_amount' => $penaltyAmount,
            'occurred_at'    => $sa->scheduled_date ? Carbon::parse($sa->scheduled_date)->toDateString() . ' ' . now()->format('H:i:s') : now(),
            'status'         => 'resolved', // Đã phê duyệt và áp dụng trực tiếp lên bảng lương nháp
            'is_anonymous'   => false,
        ]);
    }

    /**
     * Trả về tên tiếng Việt của thứ trong tuần.
     */
    private function getDayVn(string $day): string
    {
        $days = [
            'Monday' => 'Thứ Hai',
            'Tuesday' => 'Thứ Ba',
            'Wednesday' => 'Thứ Tư',
            'Thursday' => 'Thứ Năm',
            'Friday' => 'Thứ Sáu',
            'Saturday' => 'Thứ Bảy',
            'Sunday' => 'Chủ Nhật',
        ];

        return $days[$day] ?? $day;
    }

    /**
     * Nhân viên đăng ký ca làm việc rảnh trong tuần.
     */
    public function register(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if (!$employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $data = $request->validate([
            'registrations' => ['nullable', 'array'],
            'registrations.*.shift_id' => ['required', 'exists:work_shifts,id'],
            'registrations.*.date' => ['required', 'date_format:Y-m-d'],
        ]);

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        \Illuminate\Support\Facades\DB::transaction(function () use ($employee, $data, $startOfWeek, $endOfWeek) {
            // Xóa toàn bộ đăng ký trong tuần này của nhân viên trước khi lưu mới
            ScheduleRegistration::where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                ->delete();

            if (!empty($data['registrations'])) {
                foreach ($data['registrations'] as $reg) {
                    ScheduleRegistration::create([
                        'restaurant_id' => $employee->restaurant_id,
                        'employee_id' => $employee->id,
                        'shift_id' => $reg['shift_id'],
                        'scheduled_date' => $reg['date'],
                    ]);
                }
            }
        });

        return back()->with('success', 'Đã lưu đăng ký ca làm việc khả dụng thành công!');
    }

    /**
     * Kích hoạt/Hủy vai trò Trưởng ca của phân công lịch trực.
     */
    public function toggleShiftLeader(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        $sa->update([
            'is_shift_leader' => !$sa->is_shift_leader,
        ]);

        $statusMsg = $sa->is_shift_leader ? 'Đã gán vai trò Trưởng ca.' : 'Đã hủy vai trò Trưởng ca.';
        return back()->with('success', $statusMsg);
    }

    /**
     * Cập nhật các thiết lập chấm công của Nhà hàng.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'grace_period_minutes'  => ['required', 'integer', 'min:0', 'max:120'],
            'ot_multiplier'         => ['required', 'numeric', 'min:1.0', 'max:5.0'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'checkin_radius_meters' => ['required', 'integer', 'min:10', 'max:10000'],
        ]);

        $restaurant = \App\Models\Restaurant::find($request->user()->restaurant_id);
        if ($restaurant) {
            $restaurant->update([
                'grace_period_minutes'  => $data['grace_period_minutes'],
                'ot_multiplier'         => (float) $data['ot_multiplier'],
                'latitude'              => $data['latitude'] ? (float) $data['latitude'] : null,
                'longitude'             => $data['longitude'] ? (float) $data['longitude'] : null,
                'checkin_radius_meters' => (int) $data['checkin_radius_meters'],
            ]);
        }

        return back()->with('success', 'Đã lưu cài đặt tham số chấm công thành công.');
    }

    /**
     * Duyệt ca làm việc rảnh của nhân sự thành phân công lịch trực.
     */
    public function approveRegistration(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'registration_id' => ['required', 'exists:schedule_registrations,id'],
        ]);

        $reg = ScheduleRegistration::with('employee')->findOrFail($data['registration_id']);

        // Check if already assigned
        $exists = ScheduleAssignment::where('employee_id', $reg->employee_id)
            ->where('shift_id', $reg->shift_id)
            ->whereDate('scheduled_date', $reg->scheduled_date)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Nhân viên này đã được xếp ca trực này vào ngày tương ứng.']);
        }

        // Auto schedule
        ScheduleAssignment::create([
            'restaurant_id'  => $reg->restaurant_id,
            'branch_id'      => $reg->employee?->branch_id ?? $request->user()->branch_id,
            'employee_id'    => $reg->employee_id,
            'shift_id'       => $reg->shift_id,
            'scheduled_date' => $reg->scheduled_date,
            'status'         => 'scheduled',
        ]);

        return back()->with('success', 'Đã duyệt ca rảnh và xếp lịch trực thành công cho nhân viên ' . ($reg->employee?->full_name ?? '') . '.');
    }

    /**
     * Xuất báo cáo chấm công của ngày được chọn ra file CSV.
     */
    public function export(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurantId = $request->user()->restaurant_id;
        $selectedDate = $request->input('date', today()->toDateString());

        $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereDate('scheduled_date', $selectedDate)
            ->with(['employee:id,full_name,employee_code,job_title', 'shift'])
            ->get();

        $headers = [
            'Content-type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=bao_cao_cham_cong_' . $selectedDate . '.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($assignments, $selectedDate) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM to prevent Excel display issues in Vietnamese
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'Họ và tên',
                'Mã nhân viên',
                'Chức vụ',
                'Ca trực',
                'Khung giờ ca',
                'Ngày trực',
                'Thực tế Vào',
                'Thực tế Ra',
                'Tổng giờ làm',
                'Trạng thái',
                'Trưởng ca',
                'Ghi chú'
            ]);

            $statusLabels = [
                'scheduled'      => 'Chưa vào ca',
                'checked_in'     => 'Đang làm việc',
                'completed'      => 'Đã hoàn thành ca',
                'absent'         => 'Vắng mặt',
                'leave_approved' => 'Nghỉ phép',
            ];

            foreach ($assignments as $a) {
                $duration = '—';
                if ($a->check_in_at) {
                    $end = $a->check_out_at ? Carbon::parse($a->check_out_at) : now();
                    $diff = Carbon::parse($a->check_in_at)->diff($end);
                    $duration = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);
                }

                $shiftTime = $a->shift ? substr($a->shift->start_time, 0, 5) . ' - ' . substr($a->shift->end_time, 0, 5) : '—';
                $checkIn = $a->check_in_at ? Carbon::parse($a->check_in_at)->format('H:i:s d/m/Y') : '—';
                $checkOut = $a->check_out_at ? Carbon::parse($a->check_out_at)->format('H:i:s d/m/Y') : '—';

                fputcsv($file, [
                    $a->employee?->full_name ?? 'Không rõ',
                    $a->employee?->employee_code ?? '—',
                    $a->employee?->job_title ?? '—',
                    $a->shift?->name ?? '—',
                    $shiftTime,
                    $selectedDate,
                    $checkIn,
                    $checkOut,
                    $duration,
                    $statusLabels[$a->status] ?? $a->status,
                    $a->is_shift_leader ? 'Có' : 'Không',
                    $a->notes ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Tạo mã QR chấm công trong ngày.
     */
    public function generateDailyQR(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = \App\Models\Restaurant::find($request->user()->restaurant_id);
        if ($restaurant) {
            $restaurant->update([
                'qr_checkin_code'       => 'QR_' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                'qr_checkin_expires_at' => now()->addHours(16),
            ]);
        }

        return back()->with('success', 'Đã tạo mã QR chấm công mới trong ngày thành công (hiệu lực 16 giờ).');
    }

    /**
     * Gửi yêu cầu đổi ca cho đồng nghiệp.
     */
    public function requestSwap(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ để thực hiện chức năng này.']);
        }

        $data = $request->validate([
            'requester_assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'receiver_assignment_id'  => ['required', 'exists:schedule_assignments,id'],
            'notes'                   => ['nullable', 'string', 'max:250'],
        ]);

        $reqAssignment = ScheduleAssignment::findOrFail($data['requester_assignment_id']);
        $recAssignment = ScheduleAssignment::findOrFail($data['receiver_assignment_id']);

        // Check ownership of requester assignment
        if ($reqAssignment->employee_id !== $employee->id) {
            return back()->withErrors(['error' => 'Bạn chỉ được gửi yêu cầu đổi ca cho ca trực của chính bạn.']);
        }

        // Check if assignments are in the same restaurant
        if ($reqAssignment->restaurant_id !== $employee->restaurant_id || $recAssignment->restaurant_id !== $employee->restaurant_id) {
            return back()->withErrors(['error' => 'Ca trực không hợp lệ.']);
        }

        // Check duplicate swap request
        $exists = ShiftSwap::where('restaurant_id', $employee->restaurant_id)
            ->where('requester_assignment_id', $data['requester_assignment_id'])
            ->where('receiver_assignment_id', $data['receiver_assignment_id'])
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Yêu cầu đổi ca này đang được xử lý, không thể tạo trùng lặp.']);
        }

        $swap = ShiftSwap::create([
            'restaurant_id'           => $employee->restaurant_id,
            'requester_assignment_id' => $data['requester_assignment_id'],
            'receiver_assignment_id'  => $data['receiver_assignment_id'],
            'status'                  => 'pending',
            'notes'                   => $data['notes'] ?? 'Đề xuất đổi ca làm việc',
        ]);

        $receiverUser = $recAssignment->employee?->user;
        if ($receiverUser) {
            $receiverUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'requested',
                "Đồng nghiệp {$employee->full_name} đề xuất đổi ca trực tuần này với bạn."
            ));
        }

        return back()->with('success', 'Đã gửi yêu cầu đổi ca trực thành công đến đồng nghiệp.');
    }

    /**
     * Chấp nhận đổi ca từ đồng nghiệp.
     */
    public function acceptSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ.']);
        }

        abort_if($swap->restaurant_id !== $employee->restaurant_id, 403);
        abort_unless($swap->status === 'pending', 422);

        // Verify that the current employee is the receiver of the swap
        $recAssignment = $swap->receiverAssignment;
        if (!$recAssignment || $recAssignment->employee_id !== $employee->id) {
            return back()->withErrors(['error' => 'Bạn không phải là người nhận của yêu cầu đổi ca này.']);
        }

        $swap->update([
            'status' => 'accepted',
            'notes'  => $swap->notes . "\n[Chấp nhận bởi " . $employee->full_name . "]",
        ]);

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        if ($requesterUser) {
            $requesterUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'accepted',
                "Đồng nghiệp {$employee->full_name} đã đồng ý yêu cầu đổi ca của bạn. Đang chờ Quản lý duyệt."
            ));
        }

        $managers = \App\Models\User::where('restaurant_id', $swap->restaurant_id)
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['owner', 'manager']);
            })
            ->get();
        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'accepted',
                "Yêu cầu đổi ca giữa {$swap->requesterAssignment->employee->full_name} và {$swap->receiverAssignment->employee->full_name} đang chờ bạn phê duyệt."
            ));
        }

        return back()->with('success', 'Bạn đã đồng ý đổi ca. Yêu cầu đã được chuyển đến Quản lý để phê duyệt cuối cùng.');
    }

    /**
     * Hủy yêu cầu đổi ca.
     */
    public function cancelSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ.']);
        }

        abort_if($swap->restaurant_id !== $employee->restaurant_id, 403);
        
        $reqAssignment = $swap->requesterAssignment;
        $recAssignment = $swap->receiverAssignment;

        // Requester or receiver can cancel/reject
        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $isReceiver = $recAssignment && $recAssignment->employee_id === $employee->id;

        if (!$isRequester && !$isReceiver) {
            return back()->withErrors(['error' => 'Bạn không có quyền thực hiện thao tác này.']);
        }

        $swap->update([
            'status' => 'cancelled',
            'notes'  => $swap->notes . "\n[Bị hủy bởi " . $employee->full_name . "]",
        ]);

        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $otherUser = $isRequester 
            ? ($recAssignment?->employee?->user) 
            : ($reqAssignment?->employee?->user);

        if ($otherUser) {
            $actionWord = $isRequester ? 'hủy' : 'từ chối';
            $otherUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'cancelled',
                "Đồng nghiệp {$employee->full_name} đã {$actionWord} yêu cầu đổi ca trực."
            ));
        }

        return back()->with('success', 'Đã hủy yêu cầu đổi ca.');
    }

    /**
     * Lấy gợi ý đổi ca trực thông minh bằng AI.
     */
    public function getSwapSuggestions(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'error' => 'Nhân viên không hợp lệ.'], 403);
        }

        $assignmentId = $request->query('assignment_id');
        $myAssignment = ScheduleAssignment::with('shift')->where('employee_id', $employee->id)->findOrFail($assignmentId);
        $myShift = $myAssignment->shift;

        $startOfWeek = Carbon::parse($myAssignment->scheduled_date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::parse($myAssignment->scheduled_date)->endOfWeek(Carbon::SUNDAY)->toDateString();

        // 1. Lấy tất cả ca trực của đồng nghiệp trong cùng tuần
        $candidates = ScheduleAssignment::where('restaurant_id', $employee->restaurant_id)
            ->where('employee_id', '!=', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee:id,full_name,role_id,job_title', 'shift'])
            ->get();

        // 2. Lấy danh sách đăng ký ca rảnh của nhân viên trong tuần này
        $myRegistrations = ScheduleRegistration::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get();

        // 3. Lấy đăng ký ca rảnh của đồng nghiệp trong tuần này
        $colleagueRegistrations = ScheduleRegistration::where('restaurant_id', $employee->restaurant_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get();

        // 4. Lấy tất cả phép nghỉ của đồng nghiệp và bản thân
        $leaves = \App\Models\LeaveRequest::where('restaurant_id', $employee->restaurant_id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('start_date', [$startOfWeek, $endOfWeek])
                  ->orWhereBetween('end_date', [$startOfWeek, $endOfWeek]);
            })
            ->get();

        $suggestions = [];

        foreach ($candidates as $cand) {
            $colleague = $cand->employee;
            $candShift = $cand->shift;
            if (!$colleague || !$candShift) continue;

            $score = 0;
            $reasons = [];

            // A. Trùng vai trò công việc: +50 điểm
            if ($colleague->role_id === $employee->role_id || $colleague->job_title === $employee->job_title) {
                $score += 50;
                $reasons[] = 'Cùng vai trò công việc';
            }

            // B. Đồng nghiệp rảnh vào ngày của tôi: +30 điểm
            $colleagueFreeOnMyDay = $colleagueRegistrations->where('employee_id', $colleague->id)
                ->where('scheduled_date', $myAssignment->scheduled_date)
                ->where('shift_id', $myShift->id)
                ->isNotEmpty();
            if ($colleagueFreeOnMyDay) {
                $score += 30;
                $reasons[] = 'Đồng nghiệp đăng ký rảnh vào ngày của bạn';
            }

            // C. Tôi rảnh vào ngày của đồng nghiệp: +20 điểm
            $iAmFreeOnColleagueDay = $myRegistrations->where('scheduled_date', $cand->scheduled_date)
                ->where('shift_id', $candShift->id)
                ->isNotEmpty();
            if ($iAmFreeOnColleagueDay) {
                $score += 20;
                $reasons[] = 'Bạn đăng ký rảnh vào ngày của đồng nghiệp';
            }

            // D. Kiểm tra phép nghỉ (Tránh đổi vào ngày nghỉ): Nếu nghỉ thì bỏ qua luôn
            $colleagueOnLeaveOnMyDay = $leaves->where('employee_id', $colleague->id)
                ->filter(fn ($l) => $myAssignment->scheduled_date >= $l->start_date && $myAssignment->scheduled_date <= $l->end_date)
                ->isNotEmpty();
            if ($colleagueOnLeaveOnMyDay) continue;

            $iAmOnLeaveOnColleagueDay = $leaves->where('employee_id', $employee->id)
                ->filter(fn ($l) => $cand->scheduled_date >= $l->start_date && $cand->scheduled_date <= $l->end_date)
                ->isNotEmpty();
            if ($iAmOnLeaveOnColleagueDay) continue;

            if ($score === 0) {
                $reasons[] = 'Khác vị trí (Cần quản lý phê duyệt đặc biệt)';
            }

            $suggestions[] = [
                'id' => $cand->id,
                'employee_name' => $colleague->full_name,
                'shift_name' => $candShift->name,
                'shift_time' => substr($candShift->start_time, 0, 5) . ' - ' . substr($candShift->end_time, 0, 5),
                'day' => $this->getDayVn(Carbon::parse($cand->scheduled_date)->format('l')),
                'date' => Carbon::parse($cand->scheduled_date)->format('d/m/Y'),
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Lấy danh sách thông báo của nhân sự.
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        
        $notifications = $user->unreadNotifications()
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'general',
                    'action' => $n->data['action'] ?? 'info',
                    'message' => $n->data['message'] ?? '',
                    'created_at' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    /**
     * Đánh dấu thông báo đã đọc.
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true
        ]);
    }
}
