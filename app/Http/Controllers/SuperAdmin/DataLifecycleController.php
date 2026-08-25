<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DataCleanupRun;
use App\Models\Restaurant;
use App\Services\DataLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DataLifecycleController extends Controller
{
    public function __construct(protected DataLifecycleService $lifecycle) {}

    public function index(): Response
    {
        return Inertia::render('super-admin/DataLifecycle', [
            'summary' => $this->lifecycle->platformSummary(),
            'policies' => [
                'orders' => config('data_lifecycle.orders'),
                'audit' => config('data_lifecycle.audit'),
                'logs' => config('data_lifecycle.logs'),
                'storage' => config('data_lifecycle.storage'),
                'backups' => config('data_lifecycle.backups'),
            ],
            'runs' => DataCleanupRun::query()
                ->with(['requestedBy:id,name', 'approvedBy:id,name'])
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (DataCleanupRun $run) => [
                    'id' => $run->id,
                    'action' => $run->action,
                    'status' => $run->status,
                    'dry_run' => $run->dry_run,
                    'requested_by' => $run->requestedBy?->name ?? 'Scheduler/CLI',
                    'approved_by' => $run->approvedBy?->name,
                    'requested_at' => $run->requested_at?->format('d/m/Y H:i'),
                    'finished_at' => $run->finished_at?->format('d/m/Y H:i'),
                    'result' => $run->result,
                    'error_message' => $run->error_message,
                ])
                ->values(),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:technical,audit,media,backups,snapshots,partitions,orders-purge,all'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
        ]);

        $preview = $this->lifecycle->preview($data['action'], $data['restaurant_id'] ?? null);
        $run = DataCleanupRun::query()->create([
            'action' => $data['action'],
            'status' => 'pending',
            'dry_run' => true,
            'approval_required' => (bool) config('data_lifecycle.require_approval', true),
            'requested_by' => $request->user()->id,
            'parameters' => [
                'action' => $data['action'],
                'restaurant_id' => $data['restaurant_id'] ?? null,
            ],
            'result' => $preview,
            'requested_at' => now(),
        ]);

        return back()->with('success', "Đã tạo dry-run cleanup #{$run->id}. Kiểm tra kết quả rồi mới phê duyệt.");
    }

    public function approve(Request $request, DataCleanupRun $run): RedirectResponse
    {
        if ($run->status !== 'pending') {
            return back()->with('error', "Cleanup run #{$run->id} không còn ở trạng thái chờ phê duyệt.");
        }

        $run->forceFill([
            'status' => 'running',
            'dry_run' => false,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'started_at' => now(),
        ])->save();

        try {
            $result = $this->lifecycle->execute($run);
            $run->forceFill([
                'status' => 'success',
                'result' => $result,
                'finished_at' => now(),
            ])->save();

            return back()->with('success', "Đã thực thi cleanup run #{$run->id}.");
        } catch (\Throwable $e) {
            Log::error('Approved data lifecycle cleanup failed.', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            $run->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            return back()->with('error', "Cleanup run #{$run->id} thất bại: {$e->getMessage()}");
        }
    }

    public function legalHold(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['enabled']) {
            $restaurant->forceFill([
                'data_legal_hold' => true,
                'data_legal_hold_reason' => $data['reason'] ?? 'Data review',
                'data_legal_hold_at' => now(),
                'data_legal_hold_by' => $request->user()->id,
            ])->save();
            $message = "Đã bật legal hold cho {$restaurant->name}.";
        } else {
            $restaurant->forceFill([
                'data_legal_hold' => false,
                'data_legal_hold_reason' => null,
                'data_legal_hold_at' => null,
                'data_legal_hold_by' => null,
            ])->save();
            $message = "Đã tắt legal hold cho {$restaurant->name}.";
        }

        return back()->with('success', $message);
    }
}
