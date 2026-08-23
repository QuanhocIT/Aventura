<?php

namespace App\Http\Controllers;

use App\Models\RestaurantBranch;
use App\Services\IngredientSpendReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngredientSpendController extends Controller
{
    public function __construct(
        private IngredientSpendReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $user = $request->user();

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable'],
        ]);

        $dateFrom = CarbonImmutable::parse($data['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = CarbonImmutable::parse($data['date_to'] ?? now()->toDateString());
        $selectedBranchId = $this->resolveBranchId($request, $data['branch_id'] ?? null);

        $report = $this->reportService->build(
            (int) $user->restaurant_id,
            $selectedBranchId,
            $dateFrom,
            $dateTo,
        );

        return Inertia::render('ingredient-spend/Index', [
            'report' => $report,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'branch_id' => $selectedBranchId,
            ],
            'branches' => RestaurantBranch::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (RestaurantBranch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->values()
                ->all(),
            'canViewAllBranches' => $user->canViewAllBranches(),
        ]);
    }

    private function resolveBranchId(Request $request, mixed $value): ?int
    {
        $user = $request->user();

        if (! $request->has('branch_id')) {
            if ($user->canViewAllBranches()) {
                return null;
            }

            $assignedBranchId = $user->assignedBranchId();
            abort_if($assignedBranchId === null, 403, 'Tài khoản chưa được gán chi nhánh.');

            return (int) $assignedBranchId;
        }

        if ($value === '' || $value === 'all' || $value === null) {
            abort_unless($user->canViewAllBranches(), 403, 'Bạn không có quyền xem số liệu toàn bộ chi nhánh.');

            return null;
        }

        abort_unless(filter_var($value, FILTER_VALIDATE_INT) !== false, 422, 'Chi nhánh không hợp lệ.');
        $branchId = (int) $value;
        $branch = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail($branchId);
        abort_unless($user->canAccessBranch($branch->id), 403, 'Bạn không có quyền xem chi nhánh này.');

        return $branch->id;
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['owner', 'accountant', 'super_admin'])
                || $user->hasPermissionTo('finance.view'),
            403,
        );
    }
}
