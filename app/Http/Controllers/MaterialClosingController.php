<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Notifications\InventoryCountAssignmentNotification;
use App\Services\CentralWarehouseService;
use App\Services\InventoryCountScopeService;
use App\Services\InventoryCountService;
use App\Services\MaterialClosingService;
use App\Services\WarehouseTaskService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialClosingController extends Controller
{
    public function __construct(
        protected MaterialClosingService $closingService,
        protected InventoryCountService $countService,
        protected InventoryCountScopeService $countScope,
        protected WarehouseTaskService $taskService,
        protected CentralWarehouseService $warehouseService,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $centralBranch = $this->assertCentralScope($user);
        $canManage = $this->canManage($user);

        $sessionsQuery = InventoryCountSession::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->where('type', 'material_closing')
            ->with([
                'items.ingredient.unit',
                'items.reconciledBy',
                'branch',
                'countedBy',
                'secondCountedBy',
                'approver',
                'rejectedBy',
                'cancelledBy',
            ])
            ->orderByDesc('id');

        if (! $canManage) {
            $sessionsQuery->where('second_counted_by', $user->id);
        }

        $sessions = $sessionsQuery->get();
        $sessionIds = $sessions->pluck('id');
        $tasks = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('task_type', 'counting')
            ->when($sessionIds->isNotEmpty(), fn ($query) => $query->whereIn('count_session_id', $sessionIds))
            ->when($sessionIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['assignee.employee', 'assigner', 'countSession'])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('inventory/MaterialClosing', [
            'centralBranch' => [
                'id' => (int) $centralBranch->id,
                'name' => $centralBranch->name,
                'code' => $centralBranch->code,
            ],
            'sessions' => $sessions,
            'tasks' => $tasks,
            'counterCandidates' => $canManage ? $this->taskService->getWarehouseStaff($user->restaurant_id) : [],
            'authUserId' => (int) $user->id,
            'canManage' => $canManage,
            'canApprove' => $user->can('inventory.adjust.approve') || $user->hasRole('warehouse_manager') || $user->isOwner() || $user->isSuperAdmin(),
            'isWarehouseStaff' => $user->hasRole('warehouse_staff'),
            'scopeMessage' => 'Kỳ chốt chỉ lấy dữ liệu Kho Tổng; không lấy nhà cung cấp và không mở rộng sang chi nhánh khác.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $centralBranch = $this->assertCentralScope($user);
        abort_unless($this->canManage($user), 403, 'Chỉ Trưởng kho Tổng mới được mở kỳ chốt nguyên liệu.');

        $data = $request->validate([
            'branch_id' => ['required', 'integer', TenantRule::exists('restaurant_branches')],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        abort_unless((int) $data['branch_id'] === (int) $centralBranch->id, 403, 'Kỳ chốt của tài khoản này chỉ được tạo cho Kho Tổng.');

        try {
            $session = $this->closingService->start(
                (int) $user->restaurant_id,
                (int) $centralBranch->id,
                $user,
                $data['from_date'],
                $data['to_date'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã tạo kỳ chốt nguyên liệu và tính tồn hệ thống phải còn.',
                'data' => $session,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->assertCentralScope($user);
        abort_unless($this->canManage($user), 403, 'Chỉ Trưởng kho Tổng mới được giao việc đối chiếu.');

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', TenantRule::exists('users')],
            'priority' => ['nullable', 'string', 'in:normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)
            ->where('type', 'material_closing')
            ->findOrFail($id);

        try {
            $counter = User::where('restaurant_id', $user->restaurant_id)
                ->whereKey((int) $data['assigned_to'])
                ->firstOrFail();

            $updated = DB::transaction(function () use ($session, $user, $counter, $data) {
                $updatedSession = $this->countService->assignSecondCounter($session, $user, $counter);
                $this->taskService->assignCountingTask($user, $updatedSession, $counter, $data);

                return $updatedSession;
            });

            $counter->notify(new InventoryCountAssignmentNotification($updated));

            return response()->json([
                'success' => true,
                'message' => 'Đã giao việc đối chiếu kỳ chốt cho nhân viên Kho Tổng.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submitCounts(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->assertCentralScope($user);

        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)
            ->where('type', 'material_closing')
            ->findOrFail($id);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.counted_quantity' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
        ]);

        try {
            $isSecondCounter = (int) $session->second_counted_by === (int) $user->id;
            $updated = $this->countService->submitCounts($session, $user, $data['items'], $isSecondCounter);

            $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('count_session_id', $session->id)
                ->where('task_type', 'counting')
                ->where('assigned_to', $user->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->first();

            if ($task) {
                if ($task->status === 'assigned') {
                    $this->taskService->updateTaskStatus($task, $user, 'in_progress');
                }
                $this->taskService->updateTaskStatus($task->fresh(), $user, 'completed', 'Đã nộp kết quả đối chiếu kỳ chốt nguyên liệu.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu kết quả đối chiếu nguyên liệu.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function assertCentralScope(User $user)
    {
        $branch = $this->countScope->centralWarehouseFor($user);

        abort_unless(
            $branch && $this->countScope->canAccessBranch($user, (int) $branch->id),
            403,
            'Tài khoản chưa được gán đúng Kho Tổng đang hoạt động.',
        );

        return $branch;
    }

    private function canManage(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasRole('warehouse_manager')
            || $user->can('warehouse.manage');
    }
}
