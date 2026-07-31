<?php

namespace App\Http\Middleware;

use App\Models\RestaurantBranch;
use App\Support\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public static bool $enforceShiftLockInTests = false;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->status !== 'active') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ quản lý.']);
        }

        if ($user && $user->status === 'active') {
            if (!$request->is('logout') && !$request->routeIs('logout')) {
                if ($user->restaurant_id && !$user->isExemptFromShiftLock()) {
                    if (!app()->runningUnitTests() || self::$enforceShiftLockInTests) {
                        // Fallback check to populate session data for pre-existing sessions or test settings
                        if (!session()->has('employee_id') || !session()->has('shift_allowed_until')) {
                            $employee = $user->employee;
                            if ($employee) {
                                session([
                                    'employee_id' => $employee->id,
                                    'shift_allowed_until' => $employee->getShiftAllowedUntil(),
                                ]);
                            }
                        }

                        $employeeId = session('employee_id');
                        $allowedUntil = session('shift_allowed_until');

                        if (!$employeeId || is_null($allowedUntil) || now()->timestamp > $allowedUntil) {
                            if ($request->expectsJson() || $request->header('X-Inertia')) {
                                return response()->json([
                                    'error' => 'SHIFT_EXPIRED',
                                    'message' => 'Ca làm việc của bạn đã kết thúc. Vui lòng hoàn thành các hóa đơn dở dang.'
                                ], 403);
                            }

                            auth()->logout();
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();
                            return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn chỉ được phép truy cập trong khung giờ ca làm việc được xếp.']);
                        }
                    }
                }
            }
        }

        $tenantContext = app(TenantContext::class);
        $tenantContext->beginRequest($user?->restaurant_id);

        if ($user && $user->restaurant_id) {
            $tenantContext->setRestaurantId($user->restaurant_id);
            $assignedBranchId = $user->assignedBranchId();
            $requestedBranchId = session('active_branch_id');

            if ($user->canViewAllBranches()) {
                // Owner/super-admin may explicitly choose a branch or "all".
                $validBranchId = $this->validActiveBranchId($requestedBranchId, $user->restaurant_id);
                if ($validBranchId !== null) {
                    $tenantContext->setActiveBranchId($validBranchId);
                } else {
                    session()->forget('active_branch_id');
                    session(['active_branch_scope' => TenantContext::SCOPE_ALL]);
                    $tenantContext->setAllBranches();
                }
            } else {
                // Every non-owner tenant user is permanently branch-scoped.
                $validBranchId = $this->validActiveBranchId($assignedBranchId, $user->restaurant_id);
                if ($validBranchId === null) {
                    session()->forget('active_branch_id');
                    session()->forget('active_branch_scope');
                    $tenantContext->setUnassigned();
                } else {
                    session([
                        'active_branch_id' => $validBranchId,
                        'active_branch_scope' => TenantContext::SCOPE_BRANCH,
                    ]);
                    $tenantContext->setActiveBranchId($validBranchId);
                }
            }
        } else {
            $tenantContext->setAllBranches();
        }

        return $next($request);
    }

    private function validActiveBranchId(mixed $branchId, int $restaurantId): ?int
    {
        if (! is_numeric($branchId) || (int) $branchId <= 0) {
            return null;
        }

        $id = (int) $branchId;

        return RestaurantBranch::query()
            ->where('restaurant_id', $restaurantId)
            ->whereKey($id)
            ->where('status', 'active')
            ->value('id');
    }
}
