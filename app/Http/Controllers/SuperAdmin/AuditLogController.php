<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\SystemSetting;
use App\Services\SuperAdminAuditStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const DANGEROUS_ACTIONS = [
        'force_logout_user',
        'force_logout_all',
        'api_key_rotate',
        'api_key_revoke',
        'retry_failed_job',
        'retry_webhook_delivery',
        'maintenance_mode_update',
        'update_system_firewall',
        'delete_tenant',
        'impersonate_start',
        'mass_export_data',
    ];

    public function index(Request $request): Response
    {
        $query = $this->filteredQuery($request)
            ->with(['user', 'restaurant'])
            ->latest('created_at');

        $logs = $query->paginate(10)->withQueryString();

        $isSqlite = \DB::connection()->getDriverName() === 'sqlite';
        $afterHoursCount = $isSqlite
            ? AuditLog::whereRaw("cast(strftime('%H', created_at) as integer) >= 23 OR cast(strftime('%H', created_at) as integer) < 5")->count()
            : AuditLog::whereRaw('HOUR(created_at) >= 23 OR HOUR(created_at) < 5')->count();

        $totalToday = AuditLog::whereDate('created_at', now()->toDateString())->count();
        $deletedCount = AuditLog::where('event', 'deleted')->count();
        $createdCount = AuditLog::where('event', 'created')->count();
        $updatedCount = AuditLog::where('event', 'updated')->count();
        $uniqueIpsCount = AuditLog::distinct()->count('ip_address');
        $retentionMonths = max(1, (int) SystemSetting::get('audit_retention_months', 6));
        $dangerousCount = AuditLog::whereIn('action', self::DANGEROUS_ACTIONS)->count();

        $topActions = AuditLog::select('action', \DB::raw('count(*) as total'))
            ->groupBy('action')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'action' => $a->action,
                'count' => $a->total,
            ]);

        return Inertia::render('super-admin/audit-log/Index', [
            'logs' => $logs->through(fn ($l) => [
                'id' => $l->id,
                'restaurant' => $l->restaurant?->name ?? null,
                'user_name' => $l->user?->name ?? 'Hệ thống',
                'user_email' => $l->user?->email,
                'user_role' => $l->user_role,
                'event' => $l->event,
                'action' => $l->action,
                'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null,
                'subject_id' => $l->subject_id,
                'ip_address' => $l->ip_address,
                'old_values' => $l->old_values,
                'new_values' => $l->new_values,
                'created_at' => $l->created_at->format('d/m/Y H:i:s'),
            ]),
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['restaurant_id', 'event', 'action', 'from', 'to']),
            'total' => $logs->total(),
            'stats' => [
                'total_today' => $totalToday,
                'deleted_count' => $deletedCount,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'unique_ips_count' => $uniqueIpsCount,
                'after_hours_count' => $afterHoursCount,
                'dangerous_count' => $dangerousCount,
                'retention_months' => $retentionMonths,
                'retention_cutoff' => now()->subMonths($retentionMonths)->toDateString(),
                'top_actions' => $topActions,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['restaurant_id', 'event', 'action', 'from', 'to']);

        SuperAdminAuditStream::record('export_audit_logs', [
            'filters' => $filters,
        ], AuditLog::class, null, 'warning');

        $query = $this->filteredQuery($request)
            ->with(['user', 'restaurant'])
            ->latest('created_at');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');

            fputcsv($output, [
                'id',
                'created_at',
                'user_name',
                'user_email',
                'user_role',
                'event',
                'action',
                'subject_type',
                'subject_id',
                'restaurant',
                'ip_address',
                'old_values',
                'new_values',
            ]);

            $query->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->user?->name ?? 'Hệ thống',
                        $log->user?->email,
                        $log->user_role,
                        $log->event,
                        $log->action,
                        $log->subject_type ? class_basename($log->subject_type) : null,
                        $log->subject_id,
                        $log->restaurant?->name,
                        $log->ip_address,
                        json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

            fclose($output);
        }, 'superadmin-audit-logs-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'retention_months' => ['required', 'integer', 'min:1', 'max:120'],
        ]);
        $before = (int) SystemSetting::get('audit_retention_months', 6);
        SystemSetting::set('audit_retention_months', (string) $data['retention_months']);

        SuperAdminAuditStream::record('audit_retention_update', [
            'before' => ['retention_months' => $before],
            'after' => ['retention_months' => (int) $data['retention_months']],
        ], AuditLog::class, null, 'critical');

        return back()->with('success', 'Đã cập nhật thời gian lưu Audit Log.');
    }

    public function pruneRetention(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermissionTo('audit.manage'), 403);

        $retentionMonths = max(1, (int) SystemSetting::get('audit_retention_months', 6));
        $cutoff = now()->subMonths($retentionMonths);
        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        SuperAdminAuditStream::record('audit_retention_prune', [
            'retention_months' => $retentionMonths,
            'cutoff' => $cutoff->toIso8601String(),
            'before' => ['eligible_count' => $count],
            'after' => ['deleted_count' => $count],
        ], AuditLog::class, null, 'critical');

        AuditLog::where('created_at', '<', $cutoff)->delete();

        return back()->with('success', "Đã xóa {$count} Audit Log quá thời hạn lưu trữ.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query();

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from.' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }

        return $query;
    }
}
